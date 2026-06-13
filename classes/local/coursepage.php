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
 * Per-user status shown next to the activity on the course page.
 *
 * Produces the counts behind the "at a glance" badges rendered by
 * {@see coursemail_cm_info_view()}:
 *  - Students: messages still unread, and conversations awaiting their reply.
 *  - Staff (mod/coursemail:send): unread messages authored by students.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursepage {
    /**
     * Returns the badge counts for a user on the course page.
     *
     * The returned array always carries a 'role' key ('staff' or 'student'):
     *  - staff:   ['role' => 'staff',   'newfromstudents' => int]
     *  - student: ['role' => 'student', 'unread' => int, 'pendingresponse' => int]
     *
     * @param int $coursemailid The coursemail instance id.
     * @param \context_module $context The module context.
     * @param int $userid User id.
     * @return array Counts keyed as described above.
     */
    public static function badges($coursemailid, \context_module $context, $userid) {
        $coursemailid = (int) $coursemailid;
        $userid = (int) $userid;

        $mailbox = new mailbox($coursemailid);
        $calculator = new completion_calculator($coursemailid, $context);

        if (has_capability('mod/coursemail:send', $context, $userid)) {
            return [
                'role' => 'staff',
                'newfromstudents' => $mailbox->count_unread_from_students($userid, $calculator->staff_ids()),
            ];
        }

        return [
            'role' => 'student',
            'unread' => $mailbox->count_unread($userid),
            'pendingresponse' => $calculator->count_pending_responses($userid),
        ];
    }
}
