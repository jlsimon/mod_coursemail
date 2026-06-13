<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_coursemail\local;

/**
 * Computes the custom completion state of a coursemail instance for a user.
 *
 * Definitions (agreed in CLAUDE.md):
 *  - "read"  : the student has read every message authored by staff
 *              (mod/coursemail:send) that they received. With no staff messages
 *              received, the rule is considered NOT met (no engagement yet).
 *  - "reply" : "read" is met AND, in every conversation that requires a response
 *              and contains a staff message in which the student takes part, the
 *              student has authored at least one (non-draft) message.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_calculator {
    /** @var int The coursemail instance id. */
    protected $coursemailid;

    /** @var \context_module The module context. */
    protected $context;

    /**
     * Constructor.
     *
     * @param int $coursemailid Instance id.
     * @param \context_module $context Module context.
     */
    public function __construct($coursemailid, $context) {
        $this->coursemailid = (int) $coursemailid;
        $this->context = $context;
    }

    /**
     * Returns the user ids that hold the send capability (staff).
     *
     * @return int[]
     */
    public function staff_ids() {
        return array_map(
            'intval',
            array_keys(get_enrolled_users($this->context, 'mod/coursemail:send', 0, 'u.id', null, 0, 0, true))
        );
    }

    /**
     * Whether the user has read every staff message they received.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function is_read_complete($userid) {
        global $DB;

        $staff = $this->staff_ids();
        if (empty($staff)) {
            return false;
        }
        [$insql, $params] = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED, 'st');
        $params['cid'] = $this->coursemailid;
        $params['uid'] = $userid;
        $params['role'] = mailbox::ROLE_TO;

        $sql = "SELECT COUNT(mu.id) AS total,
                       SUM(CASE WHEN mu.unread = 1 THEN 1 ELSE 0 END) AS unread
                  FROM {coursemail_message_users} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                  JOIN {coursemail_conversations} c ON c.id = m.conversationid
                 WHERE c.coursemailid = :cid
                   AND mu.userid = :uid
                   AND mu.role = :role
                   AND m.draft = 0
                   AND m.userid $insql";
        $rec = $DB->get_record_sql($sql, $params);

        return $rec->total > 0 && (int) $rec->unread === 0;
    }

    /**
     * Whether the user has replied in every conversation that requires a response.
     *
     * This is the reply obligation on its own, with NO read baseline: it only
     * checks the per-conversation requires-response flag. Met vacuously when no
     * such conversation applies to the user.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function replies_satisfied($userid) {
        return $this->count_pending_responses($userid) === 0;
    }

    /**
     * Counts the conversations that still require a response from the user.
     *
     * A conversation counts when it requires a response, contains a staff message,
     * the user takes part in it, and the user has not yet authored a sent message.
     *
     * @param int $userid User id.
     * @return int Number of conversations awaiting the user's reply.
     */
    public function count_pending_responses($userid) {
        global $DB;

        $staff = $this->staff_ids();
        if (empty($staff)) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED, 'st');
        $params['cid'] = $this->coursemailid;
        $params['uidpart'] = $userid;
        $params['uidreply'] = $userid;

        // Conversations that require a response, contain a staff message, the user
        // takes part in, and where the user has not yet authored a sent message.
        $sql = "SELECT COUNT(DISTINCT c.id)
                  FROM {coursemail_conversations} c
                 WHERE c.coursemailid = :cid
                   AND c.requiresresponse = 1
                   AND EXISTS (SELECT 1 FROM {coursemail_messages} sm
                                WHERE sm.conversationid = c.id AND sm.draft = 0 AND sm.userid $insql)
                   AND EXISTS (SELECT 1 FROM {coursemail_message_users} pmu
                                JOIN {coursemail_messages} pm ON pm.id = pmu.messageid
                                WHERE pm.conversationid = c.id AND pmu.userid = :uidpart)
                   AND NOT EXISTS (SELECT 1 FROM {coursemail_messages} mr
                                WHERE mr.conversationid = c.id AND mr.userid = :uidreply AND mr.draft = 0)";

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Whether the single coursemail completion rule is met for the user.
     *
     * The rule combines the per-conversation obligations (reply where a response
     * is required, manual mark where flagged) with an optional read baseline:
     *  - $requireread = true: the student must also have read every staff message.
     *  - $requireread = false: only the per-conversation obligations are enforced.
     *
     * @param int $userid User id.
     * @param bool $requireread Whether reading all staff messages is required.
     * @return bool
     */
    public function is_complete($userid, $requireread = true) {
        if ($requireread && !$this->is_read_complete($userid)) {
            return false;
        }

        return $this->replies_satisfied($userid) && $this->is_manual_complete($userid);
    }

    /**
     * Whether the user has read all staff messages and replied where required.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function is_reply_complete($userid) {
        return $this->is_read_complete($userid) && $this->replies_satisfied($userid);
    }

    /**
     * Whether staff have manually marked the student as completed in every
     * conversation that requires it.
     *
     * Independent of read/reply: the sole gate is the teacher's per-student action.
     * Met vacuously when the student takes part in no such conversation.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function is_manual_complete($userid) {
        global $DB;

        $staff = $this->staff_ids();
        if (empty($staff)) {
            // With no staff there can be no staff-driven conversation to gate on.
            return true;
        }
        [$insql, $params] = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED, 'st');
        $params['cid'] = $this->coursemailid;
        $params['uidpart'] = $userid;
        $params['uidmc'] = $userid;

        // Conversations flagged for manual completion, containing a staff message,
        // the student takes part in, and where staff have NOT yet marked them done.
        $sql = "SELECT COUNT(DISTINCT c.id)
                  FROM {coursemail_conversations} c
                 WHERE c.coursemailid = :cid
                   AND c.requiresmanualcomplete = 1
                   AND EXISTS (SELECT 1 FROM {coursemail_messages} sm
                                WHERE sm.conversationid = c.id AND sm.draft = 0 AND sm.userid $insql)
                   AND EXISTS (SELECT 1 FROM {coursemail_message_users} pmu
                                JOIN {coursemail_messages} pm ON pm.id = pmu.messageid
                                WHERE pm.conversationid = c.id AND pmu.userid = :uidpart)
                   AND NOT EXISTS (SELECT 1 FROM {coursemail_manualcomplete} mc
                                WHERE mc.conversationid = c.id AND mc.userid = :uidmc)";
        $outstanding = (int) $DB->count_records_sql($sql, $params);

        return $outstanding === 0;
    }
}
