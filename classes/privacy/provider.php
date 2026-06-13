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

namespace mod_coursemail\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\helper as request_helper;
use core_privacy\local\request\transform;

/**
 * Privacy API provider for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the personal data stored by the plugin.
     *
     * @param collection $collection The metadata collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('coursemail_conversations', [
            'creatorid' => 'privacy:metadata:coursemail_conversations:creatorid',
            'subject' => 'privacy:metadata:coursemail_conversations:subject',
            'timecreated' => 'privacy:metadata:coursemail_conversations:timecreated',
        ], 'privacy:metadata:coursemail_conversations');

        $collection->add_database_table('coursemail_messages', [
            'userid' => 'privacy:metadata:coursemail_messages:userid',
            'body' => 'privacy:metadata:coursemail_messages:body',
            'timesent' => 'privacy:metadata:coursemail_messages:timesent',
        ], 'privacy:metadata:coursemail_messages');

        $collection->add_database_table('coursemail_message_users', [
            'userid' => 'privacy:metadata:coursemail_message_users:userid',
            'unread' => 'privacy:metadata:coursemail_message_users:unread',
            'timeread' => 'privacy:metadata:coursemail_message_users:timeread',
            'starred' => 'privacy:metadata:coursemail_message_users:starred',
        ], 'privacy:metadata:coursemail_message_users');

        $collection->add_database_table('coursemail_manualcomplete', [
            'userid' => 'privacy:metadata:coursemail_manualcomplete:userid',
            'completedby' => 'privacy:metadata:coursemail_manualcomplete:completedby',
            'timecompleted' => 'privacy:metadata:coursemail_manualcomplete:timecompleted',
        ], 'privacy:metadata:coursemail_manualcomplete');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:coursemail_attachments');

        return $collection;
    }

    /**
     * Returns the module contexts that contain data for the given user.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {coursemail} cmail
                  JOIN {course_modules} cm ON cm.instance = cmail.id
                  JOIN {modules} md ON md.id = cm.module AND md.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE cmail.id IN (
                     SELECT c.coursemailid FROM {coursemail_conversations} c WHERE c.creatorid = :u1
                     UNION
                     SELECT c2.coursemailid FROM {coursemail_conversations} c2
                       JOIN {coursemail_messages} m ON m.conversationid = c2.id WHERE m.userid = :u2
                     UNION
                     SELECT c3.coursemailid FROM {coursemail_conversations} c3
                       JOIN {coursemail_messages} m2 ON m2.conversationid = c3.id
                       JOIN {coursemail_message_users} mu ON mu.messageid = m2.id WHERE mu.userid = :u3
                     UNION
                     SELECT c4.coursemailid FROM {coursemail_conversations} c4
                       JOIN {coursemail_manualcomplete} mc ON mc.conversationid = c4.id
                      WHERE mc.userid = :u4 OR mc.completedby = :u5
                 )";
        $params = [
            'modname' => 'coursemail',
            'contextlevel' => CONTEXT_MODULE,
            'u1' => $userid,
            'u2' => $userid,
            'u3' => $userid,
            'u4' => $userid,
            'u5' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Returns the users who have data within the given context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('coursemail', $context->instanceid);
        if (!$cm) {
            return;
        }
        $params = ['cid' => $cm->instance];

        $userlist->add_from_sql(
            'creatorid',
            "SELECT creatorid FROM {coursemail_conversations} WHERE coursemailid = :cid",
            $params
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT m.userid
               FROM {coursemail_messages} m
               JOIN {coursemail_conversations} c ON c.id = m.conversationid
              WHERE c.coursemailid = :cid",
            $params
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT mu.userid
               FROM {coursemail_message_users} mu
               JOIN {coursemail_messages} m ON m.id = mu.messageid
               JOIN {coursemail_conversations} c ON c.id = m.conversationid
              WHERE c.coursemailid = :cid",
            $params
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT mc.userid
               FROM {coursemail_manualcomplete} mc
               JOIN {coursemail_conversations} c ON c.id = mc.conversationid
              WHERE c.coursemailid = :cid",
            $params
        );

        $userlist->add_from_sql(
            'completedby',
            "SELECT mc.completedby
               FROM {coursemail_manualcomplete} mc
               JOIN {coursemail_conversations} c ON c.id = mc.conversationid
              WHERE c.coursemailid = :cid AND mc.completedby > 0",
            $params
        );
    }

    /**
     * Exports the personal data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('coursemail', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $sql = "SELECT mu.id AS rowid, mu.role, mu.unread, mu.timeread, mu.starred,
                           m.id AS messageid, m.body, m.bodyformat, m.draft, m.timesent,
                           m.userid AS authorid,
                           c.id AS conversationid, c.subject, c.requiresresponse, c.timecreated
                      FROM {coursemail_message_users} mu
                      JOIN {coursemail_messages} m ON m.id = mu.messageid
                      JOIN {coursemail_conversations} c ON c.id = m.conversationid
                     WHERE c.coursemailid = :cid AND mu.userid = :uid
                  ORDER BY c.id, m.timecreated";
            $rows = $DB->get_records_sql($sql, ['cid' => $cm->instance, 'uid' => $userid]);

            // Drafts have no per-message-user row, so they are fetched separately and merged.
            $draftsql = "SELECT m.id AS messageid, m.body, m.bodyformat, m.timemodified,
                                c.id AS conversationid, c.subject, c.requiresresponse, c.timecreated
                           FROM {coursemail_messages} m
                           JOIN {coursemail_conversations} c ON c.id = m.conversationid
                          WHERE c.coursemailid = :cid AND m.userid = :uid AND m.draft = 1
                       ORDER BY c.id, m.timemodified";
            $drafts = $DB->get_records_sql($draftsql, ['cid' => $cm->instance, 'uid' => $userid]);

            // Manual-completion rows where the user was marked completed by a teacher.
            $mcsql = "SELECT c.id AS conversationid, c.subject, c.timecreated, mc.timecompleted
                        FROM {coursemail_manualcomplete} mc
                        JOIN {coursemail_conversations} c ON c.id = mc.conversationid
                       WHERE c.coursemailid = :cid AND mc.userid = :uid";
            $manualcompletions = $DB->get_records_sql($mcsql, ['cid' => $cm->instance, 'uid' => $userid]);

            if (empty($rows) && empty($drafts) && empty($manualcompletions)) {
                continue;
            }

            $conversations = [];
            foreach ($rows as $row) {
                if (!isset($conversations[$row->conversationid])) {
                    $conversations[$row->conversationid] = (object) [
                        'subject' => $row->subject,
                        'requiresresponse' => transform::yesno($row->requiresresponse),
                        'timecreated' => transform::datetime($row->timecreated),
                        'messages' => [],
                    ];
                }
                $conversations[$row->conversationid]->messages[] = (object) [
                    'authoredbyyou' => transform::yesno($row->authorid == $userid),
                    'draft' => transform::yesno($row->draft),
                    'body' => format_text($row->body, $row->bodyformat, ['context' => $context]),
                    'timesent' => $row->timesent ? transform::datetime($row->timesent) : '-',
                    'unread' => transform::yesno($row->unread),
                    'timeread' => $row->timeread ? transform::datetime($row->timeread) : '-',
                    'starred' => transform::yesno($row->starred),
                ];
            }

            foreach ($drafts as $draft) {
                if (!isset($conversations[$draft->conversationid])) {
                    $conversations[$draft->conversationid] = (object) [
                        'subject' => $draft->subject,
                        'requiresresponse' => transform::yesno($draft->requiresresponse),
                        'timecreated' => transform::datetime($draft->timecreated),
                        'messages' => [],
                    ];
                }
                $conversations[$draft->conversationid]->messages[] = (object) [
                    'authoredbyyou' => transform::yesno(true),
                    'draft' => transform::yesno(true),
                    'body' => format_text($draft->body, $draft->bodyformat, ['context' => $context]),
                    'timesent' => '-',
                    'unread' => '-',
                    'timeread' => '-',
                    'starred' => '-',
                ];
            }

            // Note, per conversation, when the user was manually marked as completed.
            foreach ($manualcompletions as $mc) {
                if (!isset($conversations[$mc->conversationid])) {
                    $conversations[$mc->conversationid] = (object) [
                        'subject' => $mc->subject,
                        'requiresresponse' => '-',
                        'timecreated' => transform::datetime($mc->timecreated),
                        'messages' => [],
                    ];
                }
                $conversations[$mc->conversationid]->markedcompleted = transform::datetime($mc->timecompleted);
            }

            $data = (object) [
                'conversations' => array_values($conversations),
            ];

            $subcontext = [get_string('pluginname', 'coursemail')];
            request_helper::export_context_files($context, $contextlist->get_user());
            writer::with_context($context)->export_data($subcontext, $data);

            // Export each relevant message's attachments under its own sub-folder.
            $messageids = [];
            foreach ($rows as $row) {
                $messageids[$row->messageid] = true;
            }
            foreach ($drafts as $draft) {
                $messageids[$draft->messageid] = true;
            }
            foreach (array_keys($messageids) as $messageid) {
                writer::with_context($context)->export_area_files(
                    array_merge($subcontext, ['message ' . $messageid]),
                    'mod_coursemail',
                    'attachment',
                    $messageid
                );
            }
        }
    }

    /**
     * Deletes all user data for all users in the given context.
     *
     * @param \context $context The context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('coursemail', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_instance_data($cm->instance);
    }

    /**
     * Deletes data for a user across the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('coursemail', $context->instanceid);
            if ($cm) {
                self::delete_users_data($cm->instance, [$userid]);
            }
        }
    }

    /**
     * Deletes data for the approved set of users within one context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('coursemail', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_users_data($cm->instance, $userlist->get_userids());
    }

    /**
     * Removes every conversation, message and per-user row of an instance.
     *
     * @param int $coursemailid The instance id.
     */
    protected static function delete_instance_data($coursemailid) {
        global $DB;

        $conversationids = $DB->get_fieldset_select(
            'coursemail_conversations',
            'id',
            'coursemailid = ?',
            [$coursemailid]
        );
        if (empty($conversationids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($conversationids);

        $DB->delete_records_select('coursemail_manualcomplete', "conversationid $insql", $params);

        $messageids = $DB->get_fieldset_select('coursemail_messages', 'id', "conversationid $insql", $params);
        if (!empty($messageids)) {
            self::delete_attachment_files($coursemailid, $messageids);
            [$minsql, $mparams] = $DB->get_in_or_equal($messageids);
            $DB->delete_records_select('coursemail_message_users', "messageid $minsql", $mparams);
            $DB->delete_records_select('coursemail_messages', "conversationid $insql", $params);
        }
        $DB->delete_records_select('coursemail_conversations', "coursemailid = ?", [$coursemailid]);
    }

    /**
     * Erases the data of the given users within an instance.
     *
     * Removes the users' per-message state, deletes the messages they authored
     * (and all references to them), and removes empty conversations they created.
     *
     * @param int $coursemailid The instance id.
     * @param int[] $userids The user ids to erase.
     */
    protected static function delete_users_data($coursemailid, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }
        [$inusers, $userparams] = $DB->get_in_or_equal($userids);

        // 0. Manual completion: the marked student owns the row, so erase it when the
        // student is erased; when only the marking teacher is erased, keep the student's
        // completion but anonymise the recorder (completedby -> 0).
        $convscope = "conversationid IN (SELECT id FROM {coursemail_conversations} WHERE coursemailid = ?)";
        [$inmc, $mcparams] = $DB->get_in_or_equal($userids);
        $DB->delete_records_select(
            'coursemail_manualcomplete',
            "$convscope AND userid $inmc",
            array_merge([$coursemailid], $mcparams)
        );
        [$inby, $byparams] = $DB->get_in_or_equal($userids);
        $DB->set_field_select(
            'coursemail_manualcomplete',
            'completedby',
            0,
            "$convscope AND completedby $inby",
            array_merge([$coursemailid], $byparams)
        );

        // 1. Remove the users' own per-message state rows across the instance.
        $scope = "messageid IN (SELECT m.id
                                  FROM {coursemail_messages} m
                                  JOIN {coursemail_conversations} c ON c.id = m.conversationid
                                 WHERE c.coursemailid = ?)";
        $DB->delete_records_select(
            'coursemail_message_users',
            "$scope AND userid $inusers",
            array_merge([$coursemailid], $userparams)
        );

        // 2. Delete the messages authored by the users, with all their references.
        [$inusers2, $userparams2] = $DB->get_in_or_equal($userids);
        $authored = $DB->get_fieldset_sql(
            "SELECT m.id
               FROM {coursemail_messages} m
               JOIN {coursemail_conversations} c ON c.id = m.conversationid
              WHERE c.coursemailid = ? AND m.userid $inusers2",
            array_merge([$coursemailid], $userparams2)
        );
        if (!empty($authored)) {
            self::delete_attachment_files($coursemailid, $authored);
            [$inmsg, $msgparams] = $DB->get_in_or_equal($authored);
            $DB->delete_records_select('coursemail_message_users', "messageid $inmsg", $msgparams);
            $DB->delete_records_select('coursemail_messages', "id $inmsg", $msgparams);
        }

        // 3. Remove now-empty conversations created by the users.
        [$inusers3, $userparams3] = $DB->get_in_or_equal($userids);
        $conversationids = $DB->get_fieldset_select(
            'coursemail_conversations',
            'id',
            "coursemailid = ? AND creatorid $inusers3",
            array_merge([$coursemailid], $userparams3)
        );
        foreach ($conversationids as $conversationid) {
            if (!$DB->record_exists('coursemail_messages', ['conversationid' => $conversationid])) {
                $DB->delete_records('coursemail_conversations', ['id' => $conversationid]);
            }
        }
    }

    /**
     * Deletes the stored attachment files of the given messages.
     *
     * @param int $coursemailid The instance id (used to resolve the module context).
     * @param int[] $messageids Message ids whose attachment areas should be cleared.
     */
    protected static function delete_attachment_files($coursemailid, array $messageids) {
        if (empty($messageids)) {
            return;
        }
        $cm = get_coursemodule_from_instance('coursemail', $coursemailid);
        if (!$cm) {
            return;
        }
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        foreach ($messageids as $messageid) {
            $fs->delete_area_files($context->id, 'mod_coursemail', 'attachment', (int) $messageid);
        }
    }
}
