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
 * Service layer for a single coursemail instance: creating conversations and
 * messages, managing read receipts, starring and folder listings.
 *
 * This is the data layer only; capability checks, recipient expansion (whole
 * class / groups) and event emission are handled by higher layers in later
 * phases. Recipients are passed here as explicit user id arrays.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mailbox {
    /** @var int Per-message role: the author/sender. */
    const ROLE_FROM = 0;

    /** @var int Per-message role: a recipient. */
    const ROLE_TO = 1;

    /** @var string The message_users state table. */
    const TABLE_USERS = 'coursemail_message_users';

    /** @var string The per-student manual-completion table. */
    const TABLE_MANUAL = 'coursemail_manualcomplete';

    /** @var int The coursemail instance id this mailbox writes to. */
    protected $coursemailid;

    /** @var int[] The instance ids folder listings are read from (defaults to the
     * primary one; widened to the whole course in the unified view). */
    protected $coursemailids;

    /**
     * Constructor.
     *
     * @param int $coursemailid The coursemail instance id.
     */
    public function __construct($coursemailid) {
        $this->coursemailid = (int) $coursemailid;
        $this->coursemailids = [$this->coursemailid];
    }

    /**
     * Returns the coursemail instance id.
     *
     * @return int
     */
    public function get_coursemailid() {
        return $this->coursemailid;
    }

    /**
     * Widens the read scope of the folder listings to several instances (the unified
     * course view). Writes stay bound to the primary instance. The caller must pass
     * instance ids it has already access-checked (see {@see scope::course_instances()}).
     *
     * @param int[] $coursemailids Instance ids to read from.
     */
    public function set_read_instances(array $coursemailids) {
        $ids = array_values(array_unique(array_map('intval', $coursemailids)));
        $this->coursemailids = empty($ids) ? [$this->coursemailid] : $ids;
    }

    /**
     * Starts a new conversation and sends its first message.
     *
     * @param int $creatorid Author user id.
     * @param string $subject Conversation subject.
     * @param bool $requiresresponse Whether the conversation requires a response.
     * @param int[] $recipientids Recipient user ids (the author is ignored if present).
     * @param string $body Message body.
     * @param int $bodyformat Body format (FORMAT_* constant).
     * @param bool $requiresmanualcomplete Whether completion is granted manually by staff.
     * @return message The created (sent) message.
     */
    public function start_conversation(
        $creatorid,
        $subject,
        $requiresresponse,
        array $recipientids,
        $body,
        $bodyformat = FORMAT_HTML,
        $requiresmanualcomplete = false
    ) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $conversation = new conversation(0, (object) [
            'coursemailid' => $this->coursemailid,
            'subject' => $subject,
            'creatorid' => $creatorid,
            'requiresresponse' => $requiresresponse ? 1 : 0,
            'requiresmanualcomplete' => $requiresmanualcomplete ? 1 : 0,
        ]);
        $conversation->create();

        $message = $this->add_message($conversation->get('id'), $creatorid, $body, $bodyformat, false);
        $this->attach_recipients($message->get('id'), $creatorid, $recipientids);

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Saves a draft message authored by a user.
     *
     * If $conversationid is 0 a new draft conversation shell is created; otherwise
     * the draft is added as a (not yet sent) reply within an existing conversation.
     *
     * @param int $creatorid Author user id.
     * @param string $subject Subject (used only when creating a new conversation).
     * @param string $body Message body.
     * @param int $bodyformat Body format (FORMAT_* constant).
     * @param int $conversationid Existing conversation id, or 0 to create one.
     * @return message The created draft message.
     */
    public function save_draft($creatorid, $subject, $body, $bodyformat = FORMAT_HTML, $conversationid = 0) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        if (empty($conversationid)) {
            $conversation = new conversation(0, (object) [
                'coursemailid' => $this->coursemailid,
                'subject' => $subject,
                'creatorid' => $creatorid,
                'requiresresponse' => 0,
            ]);
            $conversation->create();
            $conversationid = $conversation->get('id');
        }

        $message = $this->add_message($conversationid, $creatorid, $body, $bodyformat, true);

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Updates the subject and body of an existing draft message.
     *
     * @param int $messageid Draft message id.
     * @param string $subject New subject (applied to the conversation).
     * @param string $body New body.
     * @param int $bodyformat Body format.
     * @return message The updated draft message.
     */
    public function update_draft($messageid, $subject, $body, $bodyformat = FORMAT_HTML) {
        global $DB;

        $message = new message($messageid);
        if (!$message->get('draft')) {
            throw new \coding_exception('Message ' . $messageid . ' is not a draft.');
        }

        $transaction = $DB->start_delegated_transaction();

        $message->set('body', $body);
        $message->set('bodyformat', $bodyformat);
        $message->update();

        $conversation = new conversation($message->get('conversationid'));
        $conversation->set('subject', $subject);
        $conversation->update();

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Sends a previously saved draft message to the given recipients.
     *
     * @param int $messageid Draft message id.
     * @param int[] $recipientids Recipient user ids.
     * @return message The sent message.
     */
    public function send_draft($messageid, array $recipientids) {
        global $DB;

        $message = new message($messageid);
        if (!$message->get('draft')) {
            throw new \coding_exception('Message ' . $messageid . ' is not a draft.');
        }
        if (empty($recipientids)) {
            throw new \coding_exception('send_draft requires at least one recipient.');
        }

        $transaction = $DB->start_delegated_transaction();

        $now = time();
        $message->set('draft', false);
        $message->set('timesent', $now);
        $message->update();

        $this->attach_recipients($messageid, $message->get('userid'), $recipientids);
        $this->touch_conversation($message->get('conversationid'), $now);

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Adds a sent reply to an existing conversation.
     *
     * Recipients are all other participants of the conversation.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid Author user id.
     * @param string $body Message body.
     * @param int $bodyformat Body format (FORMAT_* constant).
     * @return message The created (sent) message.
     */
    public function reply($conversationid, $userid, $body, $bodyformat = FORMAT_HTML) {
        global $DB;

        if (!conversation::get_record(['id' => $conversationid, 'coursemailid' => $this->coursemailid])) {
            throw new \coding_exception('Conversation ' . $conversationid . ' not found in this mailbox.');
        }

        $transaction = $DB->start_delegated_transaction();

        $recipientids = array_diff($this->get_participant_ids($conversationid), [$userid]);
        $message = $this->add_message($conversationid, $userid, $body, $bodyformat, false);
        $this->attach_recipients($message->get('id'), $userid, $recipientids);

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Marks a message as read for a recipient (records a read receipt).
     *
     * @param int $messageid Message id.
     * @param int $userid Recipient user id.
     * @return bool True if the message was unread and is now marked read.
     */
    public function mark_read($messageid, $userid) {
        global $DB;

        $row = $DB->get_record(self::TABLE_USERS, [
            'messageid' => $messageid,
            'userid' => $userid,
            'role' => self::ROLE_TO,
        ]);
        if (!$row || !$row->unread) {
            return false;
        }

        $row->unread = 0;
        $row->timeread = time();
        $DB->update_record(self::TABLE_USERS, $row);

        return true;
    }

    /**
     * Sets or clears the starred flag of a message for a user.
     *
     * @param int $messageid Message id.
     * @param int $userid User id (author or recipient).
     * @param bool $starred Whether the message should be starred.
     * @return bool True if a state row was updated.
     */
    public function set_starred($messageid, $userid, $starred) {
        global $DB;

        $row = $DB->get_record(self::TABLE_USERS, [
            'messageid' => $messageid,
            'userid' => $userid,
        ]);
        if (!$row) {
            return false;
        }

        $row->starred = $starred ? 1 : 0;
        $DB->update_record(self::TABLE_USERS, $row);

        return true;
    }

    /**
     * Returns the distinct participant user ids of a conversation.
     *
     * @param int $conversationid Conversation id.
     * @return int[]
     */
    public function get_participant_ids($conversationid) {
        global $DB;

        $sql = "SELECT DISTINCT mu.userid
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid = :conversationid";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['conversationid' => $conversationid]));
    }

    /**
     * Returns the conversations a user has received messages in (Inbox).
     *
     * @param int $userid User id.
     * @param int $limitfrom Offset for pagination (0 = from the start).
     * @param int $limitnum Maximum number of rows (0 = no limit).
     * @param bool $onlyunread Only conversations with at least one unread received message.
     * @return conversation[]
     */
    public function get_inbox_conversations($userid, $limitfrom = 0, $limitnum = 0, $onlyunread = false) {
        return $this->get_conversations_for_role($userid, self::ROLE_TO, $limitfrom, $limitnum, $onlyunread);
    }

    /**
     * Returns the conversations a user has sent messages in (Sent).
     *
     * @param int $userid User id.
     * @param int $limitfrom Offset for pagination (0 = from the start).
     * @param int $limitnum Maximum number of rows (0 = no limit).
     * @return conversation[]
     */
    public function get_sent_conversations($userid, $limitfrom = 0, $limitnum = 0) {
        return $this->get_conversations_for_role($userid, self::ROLE_FROM, $limitfrom, $limitnum);
    }

    /**
     * Returns every conversation in this mailbox's instance(s), regardless of who
     * takes part (supervision view). Only conversations with at least one sent
     * (non-draft) message are listed. Caller must enforce mod/coursemail:viewall.
     *
     * @param int $limitfrom Offset for pagination (0 = from the start).
     * @param int $limitnum Maximum number of rows (0 = no limit).
     * @param bool $onlyunanswered Only conversations that require a response and where
     *                             at least one recipient has not replied yet.
     * @return conversation[]
     */
    public function get_all_conversations($limitfrom = 0, $limitnum = 0, $onlyunanswered = false) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($this->coursemailids, SQL_PARAMS_NAMED, 'cm');
        $where = '';
        if ($onlyunanswered) {
            // Requires a response and some recipient (role = TO) has authored no message.
            $where = "AND c.requiresresponse = 1
                      AND EXISTS (
                          SELECT 1
                            FROM {" . self::TABLE_USERS . "} tmu
                            JOIN {coursemail_messages} tm ON tm.id = tmu.messageid AND tm.draft = 0
                           WHERE tm.conversationid = c.id
                             AND tmu.role = :roleto
                             AND NOT EXISTS (
                                 SELECT 1 FROM {coursemail_messages} rr
                                  WHERE rr.conversationid = c.id
                                    AND rr.userid = tmu.userid
                                    AND rr.draft = 0))";
            $inparams['roleto'] = self::ROLE_TO;
        }
        $sql = "SELECT DISTINCT c.*
                  FROM {coursemail_conversations} c
                  JOIN {coursemail_messages} m ON m.conversationid = c.id
                 WHERE c.coursemailid $insql
                   AND m.draft = 0
                   $where
              ORDER BY c.timemodified DESC, c.id DESC";
        $records = $DB->get_records_sql($sql, $inparams, $limitfrom, $limitnum);

        return array_map(function ($record) {
            return new conversation(0, $record);
        }, $records);
    }

    /**
     * Searches conversations by subject or message body within this mailbox's instance(s).
     *
     * Matches the conversation subject or the body of any of its sent (non-draft)
     * messages. When $includeall is false, only conversations the user takes part in
     * are returned; when true (supervision), every matching conversation is returned.
     *
     * @param int $userid User id whose participation bounds the search (ignored if $includeall).
     * @param string $query Search text (matched as a substring, case-insensitive).
     * @param int $limitfrom Offset for pagination (0 = from the start).
     * @param int $limitnum Maximum number of rows (0 = no limit).
     * @param bool $includeall Whether to search every conversation (viewall) vs only the user's.
     * @return conversation[]
     */
    public function search_conversations($userid, $query, $limitfrom = 0, $limitnum = 0, $includeall = false) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($this->coursemailids, SQL_PARAMS_NAMED, 'cm');
        $like = '%' . $DB->sql_like_escape($query) . '%';
        $subjectlike = $DB->sql_like('c.subject', ':qsubject', false);
        $bodylike = $DB->sql_like('m.body', ':qbody', false);
        $params = $inparams + ['qsubject' => $like, 'qbody' => $like];

        $participation = '';
        if (!$includeall) {
            $participation = "AND EXISTS (
                SELECT 1
                  FROM {" . self::TABLE_USERS . "} pmu
                  JOIN {coursemail_messages} pm ON pm.id = pmu.messageid
                 WHERE pm.conversationid = c.id
                   AND pmu.userid = :puserid)";
            $params['puserid'] = $userid;
        }

        $sql = "SELECT DISTINCT c.*
                  FROM {coursemail_conversations} c
                  JOIN {coursemail_messages} m ON m.conversationid = c.id AND m.draft = 0
                 WHERE c.coursemailid $insql
                   AND ($subjectlike OR $bodylike)
                   $participation
              ORDER BY c.timemodified DESC, c.id DESC";
        $records = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);

        return array_map(function ($record) {
            return new conversation(0, $record);
        }, $records);
    }

    /**
     * Returns the draft messages authored by a user in this instance.
     *
     * @param int $userid User id.
     * @param int $limitfrom Offset for pagination (0 = from the start).
     * @param int $limitnum Maximum number of rows (0 = no limit).
     * @return message[]
     */
    public function get_draft_messages($userid, $limitfrom = 0, $limitnum = 0) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($this->coursemailids, SQL_PARAMS_NAMED, 'cm');
        $sql = "SELECT m.*
                  FROM {coursemail_messages} m
                  JOIN {coursemail_conversations} c ON c.id = m.conversationid
                 WHERE c.coursemailid $insql
                   AND m.userid = :userid
                   AND m.draft = 1
              ORDER BY m.timemodified DESC, m.id DESC";
        $records = $DB->get_records_sql($sql, $inparams + [
            'userid' => $userid,
        ], $limitfrom, $limitnum);

        return array_map(function ($record) {
            return new message(0, $record);
        }, $records);
    }

    /**
     * Returns the messages a user has starred in this instance.
     *
     * @param int $userid User id.
     * @param int $limitfrom Offset for pagination (0 = from the start).
     * @param int $limitnum Maximum number of rows (0 = no limit).
     * @return message[]
     */
    public function get_starred_messages($userid, $limitfrom = 0, $limitnum = 0) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($this->coursemailids, SQL_PARAMS_NAMED, 'cm');
        $sql = "SELECT m.*
                  FROM {coursemail_messages} m
                  JOIN {coursemail_conversations} c ON c.id = m.conversationid
                  JOIN {" . self::TABLE_USERS . "} mu ON mu.messageid = m.id
                 WHERE c.coursemailid $insql
                   AND mu.userid = :userid
                   AND mu.starred = 1
              ORDER BY m.timecreated DESC, m.id DESC";
        $records = $DB->get_records_sql($sql, $inparams + [
            'userid' => $userid,
        ], $limitfrom, $limitnum);

        return array_map(function ($record) {
            return new message(0, $record);
        }, $records);
    }

    /**
     * Counts the unread messages of a user in this instance.
     *
     * @param int $userid User id.
     * @return int
     */
    public function count_unread($userid) {
        global $DB;

        $sql = "SELECT COUNT(mu.id)
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                  JOIN {coursemail_conversations} c ON c.id = m.conversationid
                 WHERE c.coursemailid = :coursemailid
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND mu.unread = 1
                   AND m.draft = 0";
        return (int) $DB->count_records_sql($sql, [
            'coursemailid' => $this->coursemailid,
            'userid' => $userid,
            'role' => self::ROLE_TO,
        ]);
    }

    /**
     * Counts the unread messages of a user authored by someone outside a given set.
     *
     * Used for the staff course-page badge ("new messages from students"): pass the
     * staff user ids to exclude their authored messages. With an empty exclusion set
     * the result equals {@see self::count_unread()}.
     *
     * @param int $userid User id.
     * @param int[] $excludeauthorids Author ids to exclude (e.g. staff).
     * @return int
     */
    public function count_unread_from_students($userid, array $excludeauthorids) {
        global $DB;

        $params = [
            'coursemailid' => $this->coursemailid,
            'userid' => (int) $userid,
            'role' => self::ROLE_TO,
        ];
        $excludesql = '';
        $exclude = array_values(array_unique(array_map('intval', $excludeauthorids)));
        if (!empty($exclude)) {
            [$notin, $exparams] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);
            $excludesql = " AND m.userid $notin";
            $params += $exparams;
        }

        $sql = "SELECT COUNT(mu.id)
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                  JOIN {coursemail_conversations} c ON c.id = m.conversationid
                 WHERE c.coursemailid = :coursemailid
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND mu.unread = 1
                   AND m.draft = 0" . $excludesql;

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns whether a user takes part in a conversation.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid User id.
     * @return bool
     */
    public function user_participates($conversationid, $userid) {
        global $DB;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {" . self::TABLE_USERS . "} mu
               JOIN {coursemail_messages} m ON m.id = mu.messageid
              WHERE m.conversationid = :conversationid
                AND mu.userid = :userid",
            ['conversationid' => $conversationid, 'userid' => (int) $userid]
        );
    }

    /**
     * Counts the unread messages a user has within a single conversation.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid User id.
     * @return int
     */
    public function count_unread_in_conversation($conversationid, $userid) {
        global $DB;

        $sql = "SELECT COUNT(mu.id)
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid = :conversationid
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND mu.unread = 1
                   AND m.draft = 0";
        return (int) $DB->count_records_sql($sql, [
            'conversationid' => $conversationid,
            'userid' => $userid,
            'role' => self::ROLE_TO,
        ]);
    }

    /**
     * Marks every unread message a user has received in a conversation as read.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid User id.
     * @return int[] Ids of the messages that were newly marked as read.
     */
    public function mark_conversation_read($conversationid, $userid) {
        global $DB;

        $sql = "SELECT mu.id, mu.messageid
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid = :conversationid
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND mu.unread = 1
                   AND m.draft = 0";
        $rows = $DB->get_records_sql($sql, [
            'conversationid' => $conversationid,
            'userid' => $userid,
            'role' => self::ROLE_TO,
        ]);

        if (empty($rows)) {
            return [];
        }

        $now = time();
        $messageids = [];
        foreach ($rows as $row) {
            $DB->update_record(self::TABLE_USERS, (object) [
                'id' => $row->id,
                'unread' => 0,
                'timeread' => $now,
            ]);
            $messageids[] = (int) $row->messageid;
        }

        return $messageids;
    }

    /**
     * Marks every message a user has received in a conversation as unread again.
     *
     * Symmetric to mark_conversation_read(): it clears the read receipts so the
     * conversation reappears as unread. Completion ("read") is recomputed by the
     * caller, since removing receipts may reopen it.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid User id.
     * @return int[] Ids of the messages that were marked unread.
     */
    public function mark_conversation_unread($conversationid, $userid) {
        global $DB;

        $sql = "SELECT mu.id, mu.messageid
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid = :conversationid
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND mu.unread = 0
                   AND m.draft = 0";
        $rows = $DB->get_records_sql($sql, [
            'conversationid' => $conversationid,
            'userid' => $userid,
            'role' => self::ROLE_TO,
        ]);

        if (empty($rows)) {
            return [];
        }

        $messageids = [];
        foreach ($rows as $row) {
            // Column timeread is NOT NULL (0 == unread), matching how unread rows are created.
            $DB->update_record(self::TABLE_USERS, (object) [
                'id' => $row->id,
                'unread' => 1,
                'timeread' => 0,
            ]);
            $messageids[] = (int) $row->messageid;
        }

        return $messageids;
    }

    /**
     * Whether a user has received any (non-draft) message in a conversation.
     *
     * Used to decide whether the "mark as unread" action applies to this user.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid User id.
     * @return bool
     */
    public function user_receives_in_conversation($conversationid, $userid) {
        global $DB;

        $sql = "SELECT 1
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid = :conversationid
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND m.draft = 0";
        return $DB->record_exists_sql($sql, [
            'conversationid' => $conversationid,
            'userid' => $userid,
            'role' => self::ROLE_TO,
        ]);
    }

    /**
     * Returns the latest sent message of a conversation, or null if none.
     *
     * @param int $conversationid Conversation id.
     * @return message|null
     */
    public function get_last_message($conversationid) {
        $messages = message::get_records(
            ['conversationid' => $conversationid, 'draft' => 0],
            'timecreated',
            'DESC',
            0,
            1
        );
        return $messages ? reset($messages) : null;
    }

    /**
     * Returns whether a message is starred by a user.
     *
     * @param int $messageid Message id.
     * @param int $userid User id.
     * @return bool
     */
    public function is_starred($messageid, $userid) {
        global $DB;
        return $DB->record_exists(self::TABLE_USERS, [
            'messageid' => $messageid,
            'userid' => $userid,
            'starred' => 1,
        ]);
    }

    /**
     * Creates a message record within a conversation.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid Author user id.
     * @param string $body Message body.
     * @param int $bodyformat Body format.
     * @param bool $draft Whether the message is a draft.
     * @return message
     */
    protected function add_message($conversationid, $userid, $body, $bodyformat, $draft) {
        $now = time();

        $message = new message(0, (object) [
            'conversationid' => $conversationid,
            'userid' => $userid,
            'body' => $body,
            'bodyformat' => $bodyformat,
            'draft' => $draft ? 1 : 0,
            'timesent' => $draft ? 0 : $now,
        ]);
        $message->create();

        // The author always gets a state row (drives the Sent / Drafts folders).
        $this->insert_user_row($message->get('id'), $userid, self::ROLE_FROM, false);

        if (!$draft) {
            $this->touch_conversation($conversationid, $now);
        }

        return $message;
    }

    /**
     * Inserts recipient state rows for a sent message, skipping the author and duplicates.
     *
     * @param int $messageid Message id.
     * @param int $authorid Author user id (never added as a recipient).
     * @param int[] $recipientids Recipient user ids.
     */
    protected function attach_recipients($messageid, $authorid, array $recipientids) {
        global $DB;

        $existing = $DB->get_fieldset_select(self::TABLE_USERS, 'userid', 'messageid = ?', [$messageid]);
        $existing = array_map('intval', $existing);

        foreach (array_unique(array_map('intval', $recipientids)) as $recipientid) {
            if ($recipientid === (int) $authorid || in_array($recipientid, $existing, true)) {
                continue;
            }
            $this->insert_user_row($messageid, $recipientid, self::ROLE_TO, true);
        }
    }

    /**
     * Inserts a single message_users state row.
     *
     * @param int $messageid Message id.
     * @param int $userid User id.
     * @param int $role One of the ROLE_* constants.
     * @param bool $unread Whether the message starts unread for this user.
     */
    protected function insert_user_row($messageid, $userid, $role, $unread) {
        global $DB;

        $now = time();
        $DB->insert_record(self::TABLE_USERS, (object) [
            'messageid' => $messageid,
            'userid' => $userid,
            'role' => $role,
            'unread' => $unread ? 1 : 0,
            'timeread' => $unread ? 0 : $now,
            'starred' => 0,
            'timecreated' => $now,
        ]);
    }

    /**
     * Updates a conversation's last-activity timestamp.
     *
     * @param int $conversationid Conversation id.
     * @param int $time Timestamp.
     */
    protected function touch_conversation($conversationid, $time) {
        $conversation = new conversation($conversationid);
        $conversation->set('timemodified', $time);
        $conversation->update();
    }

    /**
     * Returns the conversations in which a user holds the given role on a sent message.
     *
     * @param int $userid User id.
     * @param int $role One of the ROLE_* constants.
     * @param int $limitfrom Offset for pagination (0 = from the start).
     * @param int $limitnum Maximum number of rows (0 = no limit).
     * @param bool $onlyunread Only conversations with an unread message in this role.
     * @return conversation[]
     */
    protected function get_conversations_for_role($userid, $role, $limitfrom = 0, $limitnum = 0, $onlyunread = false) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($this->coursemailids, SQL_PARAMS_NAMED, 'cm');
        $unread = $onlyunread ? 'AND mu.unread = 1' : '';
        $sql = "SELECT DISTINCT c.*
                  FROM {coursemail_conversations} c
                  JOIN {coursemail_messages} m ON m.conversationid = c.id
                  JOIN {" . self::TABLE_USERS . "} mu ON mu.messageid = m.id
                 WHERE c.coursemailid $insql
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND m.draft = 0
                   $unread
              ORDER BY c.timemodified DESC, c.id DESC";
        $records = $DB->get_records_sql($sql, $inparams + [
            'userid' => $userid,
            'role' => $role,
        ], $limitfrom, $limitnum);

        return array_map(function ($record) {
            return new conversation(0, $record);
        }, $records);
    }

    /**
     * Returns the latest sent message of each given conversation in a single query.
     *
     * Avoids the N+1 of calling get_last_message() per conversation when listing folders.
     *
     * @param int[] $conversationids Conversation ids.
     * @return message[] Keyed by conversation id (conversations with no sent message are omitted).
     */
    public function get_last_messages(array $conversationids) {
        global $DB;

        if (empty($conversationids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($conversationids, SQL_PARAMS_NAMED, 'c');

        // Pick, per conversation, the non-draft message with no later (timecreated, id) sibling.
        $sql = "SELECT m.*
                  FROM {coursemail_messages} m
                 WHERE m.draft = 0
                   AND m.conversationid $insql
                   AND NOT EXISTS (
                       SELECT 1 FROM {coursemail_messages} m2
                        WHERE m2.conversationid = m.conversationid
                          AND m2.draft = 0
                          AND (m2.timecreated > m.timecreated
                               OR (m2.timecreated = m.timecreated AND m2.id > m.id)))";
        $records = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($records as $record) {
            $result[(int) $record->conversationid] = new message(0, $record);
        }
        return $result;
    }

    /**
     * Counts the unread messages a user has in each given conversation, in a single query.
     *
     * @param int[] $conversationids Conversation ids.
     * @param int $userid User id.
     * @return int[] Keyed by conversation id (conversations with no unread are omitted).
     */
    public function count_unread_for_conversations(array $conversationids, $userid) {
        global $DB;

        if (empty($conversationids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($conversationids, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = $userid;
        $params['role'] = self::ROLE_TO;

        $sql = "SELECT m.conversationid, COUNT(mu.id) AS cnt
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid $insql
                   AND mu.userid = :userid
                   AND mu.role = :role
                   AND mu.unread = 1
                   AND m.draft = 0
              GROUP BY m.conversationid";
        $rows = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->conversationid] = (int) $row->cnt;
        }
        return $result;
    }

    /**
     * Returns the subset of the given message ids that are starred by the user, in one query.
     *
     * @param int[] $messageids Message ids.
     * @param int $userid User id.
     * @return int[] The message ids that are starred (as a set of values).
     */
    public function get_starred_message_ids(array $messageids, $userid) {
        global $DB;

        if (empty($messageids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($messageids, SQL_PARAMS_NAMED, 'm');
        $params['userid'] = $userid;

        $sql = "SELECT messageid
                  FROM {" . self::TABLE_USERS . "}
                 WHERE messageid $insql AND userid = :userid AND starred = 1";
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Summarises, per conversation, who its recipients are (everyone other than the
     * viewer who received a message in the thread), in a single query.
     *
     * Used to label the Sent folder with the addressees rather than the last author.
     * Returns a count plus one representative recipient id so the list can show
     * "Name +N" without resolving every recipient's name up front.
     *
     * @param int[] $conversationids Conversation ids.
     * @param int $userid The viewing user (excluded from the recipients).
     * @return array Keyed by conversation id => ['count' => int, 'sampleid' => int].
     */
    public function get_recipient_summaries(array $conversationids, $userid) {
        global $DB;

        if (empty($conversationids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($conversationids, SQL_PARAMS_NAMED, 'c');
        $params['role'] = self::ROLE_TO;
        $params['me'] = $userid;

        $sql = "SELECT m.conversationid AS cid,
                       COUNT(DISTINCT mu.userid) AS cnt,
                       MIN(mu.userid) AS sampleid
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid $insql
                   AND mu.role = :role
                   AND mu.userid <> :me
                   AND m.draft = 0
              GROUP BY m.conversationid";
        $rows = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->cid] = [
                'count' => (int) $row->cnt,
                'sampleid' => (int) $row->sampleid,
            ];
        }
        return $result;
    }

    /**
     * Returns the distinct recipient user ids of a conversation (everyone other than
     * the viewer who received a message in the thread).
     *
     * @param int $conversationid Conversation id.
     * @param int $userid The viewing user (excluded from the result).
     * @return int[] Distinct recipient user ids.
     */
    public function get_recipient_userids($conversationid, $userid) {
        global $DB;

        $sql = "SELECT DISTINCT mu.userid
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid = :cid
                   AND mu.role = :role
                   AND mu.userid <> :me
                   AND m.draft = 0";
        $ids = $DB->get_fieldset_sql($sql, [
            'cid' => $conversationid,
            'role' => self::ROLE_TO,
            'me' => $userid,
        ]);
        return array_map('intval', $ids);
    }

    /**
     * Returns per-recipient read and reply status for a conversation.
     *
     * For each recipient (role = ROLE_TO) the result reports whether they have read
     * every message addressed to them in the thread, the timestamp of their latest
     * read receipt, and whether they have authored any sent (non-draft) message in
     * the thread (i.e. replied). Intended for the staff-only "To:" header, so callers
     * must already have gated this behind the recipient-visibility capability check.
     *
     * @param int $conversationid Conversation id.
     * @return array<int, array{read: bool, readtime: int, replied: bool}> Keyed by user id.
     */
    public function get_recipient_status($conversationid) {
        global $DB;

        // Read aggregates per recipient: any still-unread message means "not read";
        // the latest read receipt drives the "read on" tooltip.
        $readsql = "SELECT mu.userid,
                           SUM(CASE WHEN mu.unread = 1 THEN 1 ELSE 0 END) AS unreadcount,
                           MAX(mu.timeread) AS lastread
                      FROM {" . self::TABLE_USERS . "} mu
                      JOIN {coursemail_messages} m ON m.id = mu.messageid
                     WHERE m.conversationid = :cid
                       AND mu.role = :role
                       AND m.draft = 0
                  GROUP BY mu.userid";
        $readrows = $DB->get_records_sql($readsql, [
            'cid' => $conversationid,
            'role' => self::ROLE_TO,
        ]);

        // Anyone who authored a sent message in the thread has replied.
        $repliersql = "SELECT DISTINCT m.userid
                         FROM {coursemail_messages} m
                        WHERE m.conversationid = :cid
                          AND m.draft = 0";
        $repliers = array_flip(array_map(
            'intval',
            $DB->get_fieldset_sql($repliersql, ['cid' => $conversationid])
        ));

        $status = [];
        foreach ($readrows as $row) {
            $uid = (int) $row->userid;
            $status[$uid] = [
                'read' => ((int) $row->unreadcount === 0),
                'readtime' => (int) $row->lastread,
                'replied' => isset($repliers[$uid]),
            ];
        }
        return $status;
    }

    /**
     * Returns the distinct user ids that take part in a conversation (author and
     * every recipient of any sent message in the thread).
     *
     * @param int $conversationid Conversation id.
     * @return int[] Distinct participant user ids.
     */
    public function conversation_participant_userids($conversationid) {
        global $DB;

        $sql = "SELECT DISTINCT mu.userid
                  FROM {" . self::TABLE_USERS . "} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                 WHERE m.conversationid = :cid
                   AND m.draft = 0";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['cid' => $conversationid]));
    }

    /**
     * Marks (or unmarks) a single student as manually completed in a conversation.
     *
     * @param int $conversationid Conversation id.
     * @param int $userid The student being marked.
     * @param bool $completed True to mark complete, false to reopen.
     * @param int $markedby The staff user id performing the action.
     * @return bool True if the stored state changed.
     */
    public function set_manual_completed($conversationid, $userid, $completed, $markedby) {
        global $DB;

        $existing = $DB->get_record(self::TABLE_MANUAL, [
            'conversationid' => $conversationid,
            'userid' => $userid,
        ]);

        if ($completed) {
            if ($existing) {
                return false;
            }
            $DB->insert_record(self::TABLE_MANUAL, (object) [
                'conversationid' => $conversationid,
                'userid' => $userid,
                'completedby' => $markedby,
                'timecompleted' => time(),
            ]);
            return true;
        }

        if (!$existing) {
            return false;
        }
        $DB->delete_records(self::TABLE_MANUAL, [
            'conversationid' => $conversationid,
            'userid' => $userid,
        ]);
        return true;
    }

    /**
     * Returns the manual-completion state of each student in a conversation.
     *
     * @param int $conversationid Conversation id.
     * @return array<int, array{completedby: int, timecompleted: int}> Keyed by student user id.
     */
    public function get_manual_completed($conversationid) {
        global $DB;

        $rows = $DB->get_records(self::TABLE_MANUAL, ['conversationid' => $conversationid]);
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->userid] = [
                'completedby' => (int) $row->completedby,
                'timecompleted' => (int) $row->timecompleted,
            ];
        }
        return $result;
    }
}
