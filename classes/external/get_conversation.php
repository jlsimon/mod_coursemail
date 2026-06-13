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

namespace mod_coursemail\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use mod_coursemail\local\mailbox;
use mod_coursemail\local\conversation;
use mod_coursemail\event\message_read;

/**
 * External function: fetch a conversation and mark it read for the current user.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_conversation extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
        ]);
    }

    /**
     * Returns the messages of a conversation and records read receipts.
     *
     * @param int $cmid Course module id.
     * @param int $conversationid Conversation id.
     * @return array
     */
    public static function execute($cmid, $conversationid) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'conversationid' => $conversationid,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        $mailbox = new mailbox($cm->instance);
        $userid = $USER->id;

        $conversation = new conversation($params['conversationid']);
        if ($conversation->get('coursemailid') != $cm->instance) {
            throw new \invalid_parameter_exception('Conversation does not belong to this activity.');
        }

        $canviewall = has_capability('mod/coursemail:viewall', $context);
        if (!$canviewall && !$mailbox->user_participates($conversation->get('id'), $userid)) {
            throw new \required_capability_exception($context, 'mod/coursemail:viewall', 'nopermissions', '');
        }

        // Record read receipts for the current user and fire one event per message.
        $newlyread = $mailbox->mark_conversation_read($conversation->get('id'), $userid);
        foreach ($newlyread as $messageid) {
            message_read::create([
                'context' => $context,
                'objectid' => $messageid,
                'other' => ['conversationid' => $conversation->get('id')],
            ])->trigger();
        }
        if (!empty($newlyread)) {
            \mod_coursemail\local\completion_updater::update_for_users($cm, [$userid]);
        }

        $messages = [];
        foreach ($conversation->get_messages() as $message) {
            [$body, $bodyformat] = external_format_text(
                $message->get('body'),
                $message->get('bodyformat'),
                $context,
                'mod_coursemail',
                'message',
                $message->get('id')
            );
            $attachments = \mod_coursemail\local\attachments::message_files($context, $message->get('id'));
            $messages[] = [
                'id' => $message->get('id'),
                'fromid' => $message->get('userid'),
                'fromname' => helper::user_fullname($message->get('userid')),
                'body' => $body,
                'bodyformat' => $bodyformat,
                'time' => $message->get('timesent'),
                'timeformatted' => userdate($message->get('timesent')),
                'isown' => ($message->get('userid') == $userid),
                'starred' => $mailbox->is_starred($message->get('id'), $userid),
                'hasattachments' => !empty($attachments),
                'attachments' => $attachments,
            ];
        }

        // Resolve the thread's addressees (everyone other than the viewer) for the
        // "To:" header, sorted by name so the disclosure list reads naturally.
        // Only staff may see the recipient list: a student who merely received a
        // message must not learn who their co-recipients (classmates) are.
        $recipients = [];
        $requiresmanual = (bool) $conversation->get('requiresmanualcomplete');
        $cansend = has_capability('mod/coursemail:send', $context);
        // Marking manual completion is a teaching action: viewall-only supervisors do not get it.
        $canmanualcomplete = $cansend && $requiresmanual;
        $canseerecipients = $cansend || has_capability('mod/coursemail:viewall', $context);
        if ($canseerecipients) {
            // Per-recipient read/reply status drives the chips' icons and border colour.
            $status = $mailbox->get_recipient_status($conversation->get('id'));
            // Per-recipient manual-completion state (only relevant when flagged).
            $manual = $requiresmanual ? $mailbox->get_manual_completed($conversation->get('id')) : [];
            foreach ($mailbox->get_recipient_userids($conversation->get('id'), $userid) as $recipientid) {
                $rs = isset($status[$recipientid])
                    ? $status[$recipientid]
                    : ['read' => false, 'readtime' => 0, 'replied' => false];
                $completed = isset($manual[$recipientid]);
                $completedinfo = '';
                if ($completed) {
                    $completedinfo = get_string('completedby', 'coursemail', (object) [
                        'user' => helper::user_fullname($manual[$recipientid]['completedby']),
                        'date' => userdate($manual[$recipientid]['timecompleted']),
                    ]);
                }
                $recipients[] = [
                    'userid' => $recipientid,
                    'name' => helper::user_fullname($recipientid),
                    'read' => $rs['read'],
                    'replied' => $rs['replied'],
                    // Replying is the most advanced state and wins over a lingering unread.
                    'borderstate' => $rs['replied'] ? 'replied' : ($rs['read'] ? 'read' : 'unread'),
                    'readtitle' => $rs['read']
                        ? ($rs['readtime']
                            ? get_string('recipientreadon', 'coursemail', userdate($rs['readtime']))
                            : get_string('recipientread', 'coursemail'))
                        : get_string('recipientunread', 'coursemail'),
                    'replytitle' => $rs['replied']
                        ? get_string('recipientreplied', 'coursemail')
                        : get_string('recipientnoreply', 'coursemail'),
                    'completed' => $completed,
                    'completedinfo' => $completedinfo,
                ];
            }
            \core_collator::asort_array_of_arrays_by_key($recipients, 'name');
            $recipients = array_values($recipients);
            // Flag the final entry so the template can comma-separate without a trailing comma.
            $lastindex = count($recipients) - 1;
            foreach ($recipients as $index => $recipient) {
                $recipients[$index]['last'] = ($index === $lastindex);
            }
        }

        return [
            'id' => $conversation->get('id'),
            'subject' => format_string($conversation->get('subject'), true, ['context' => $context]),
            'requiresresponse' => (bool) $conversation->get('requiresresponse'),
            'requiresmanualcomplete' => $requiresmanual,
            // Whether the current user may mark students complete in this conversation.
            'canmanualcomplete' => $canmanualcomplete,
            'recipients' => $recipients,
            'recipientcount' => count($recipients),
            'canreply' => has_capability('mod/coursemail:reply', $context)
                && $mailbox->user_participates($conversation->get('id'), $userid),
            // The user can mark the thread unread only if they actually received messages in it.
            'canmarkunread' => $mailbox->user_receives_in_conversation($conversation->get('id'), $userid),
            // Starring is a personal action on one's own messages: a pure supervisor
            // (viewall, but not a participant) opens the thread read-only.
            'canstar' => $mailbox->user_participates($conversation->get('id'), $userid),
            'messages' => $messages,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Conversation id'),
            'subject' => new external_value(PARAM_TEXT, 'Conversation subject'),
            'requiresresponse' => new external_value(PARAM_BOOL, 'Whether the conversation requires a response'),
            'requiresmanualcomplete' => new external_value(
                PARAM_BOOL,
                'Whether the conversation is completed manually by staff, per student',
                VALUE_DEFAULT,
                false
            ),
            'canmanualcomplete' => new external_value(
                PARAM_BOOL,
                'Whether the current user may mark students complete in this conversation',
                VALUE_DEFAULT,
                false
            ),
            'recipients' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'Recipient user id'),
                    'name' => new external_value(PARAM_NOTAGS, 'Recipient full name'),
                    'read' => new external_value(PARAM_BOOL, 'Whether the recipient has read every message addressed to them'),
                    'replied' => new external_value(PARAM_BOOL, 'Whether the recipient has authored a reply in the thread'),
                    'borderstate' => new external_value(PARAM_ALPHA, 'Chip border state: unread, read or replied'),
                    'readtitle' => new external_value(PARAM_TEXT, 'Tooltip describing the read state'),
                    'replytitle' => new external_value(PARAM_TEXT, 'Tooltip describing the reply state'),
                    'completed' => new external_value(PARAM_BOOL, 'Whether staff have marked this recipient as completed'),
                    'completedinfo' => new external_value(PARAM_TEXT, 'Who marked it completed and when (empty if not)'),
                    'last' => new external_value(PARAM_BOOL, 'Whether this is the final list entry'),
                ]),
                'Addressees of the thread other than the viewer',
                VALUE_DEFAULT,
                []
            ),
            'recipientcount' => new external_value(PARAM_INT, 'Number of addressees', VALUE_DEFAULT, 0),
            'canreply' => new external_value(PARAM_BOOL, 'Whether the current user can reply'),
            'canmarkunread' => new external_value(PARAM_BOOL, 'Whether the user can mark the conversation unread'),
            'canstar' => new external_value(
                PARAM_BOOL,
                'Whether the user can star messages (false for a pure supervisor)',
                VALUE_DEFAULT,
                false
            ),
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Message id'),
                    'fromid' => new external_value(PARAM_INT, 'Author user id'),
                    'fromname' => new external_value(PARAM_NOTAGS, 'Author full name'),
                    'body' => new external_value(PARAM_RAW, 'Formatted message body'),
                    'bodyformat' => new external_value(PARAM_INT, 'Body format'),
                    'time' => new external_value(PARAM_INT, 'Sent timestamp'),
                    'timeformatted' => new external_value(PARAM_NOTAGS, 'Human-readable date'),
                    'isown' => new external_value(PARAM_BOOL, 'Whether the message is authored by the current user'),
                    'starred' => new external_value(PARAM_BOOL, 'Whether the message is starred'),
                    'hasattachments' => new external_value(PARAM_BOOL, 'Whether the message has attachments', VALUE_DEFAULT, false),
                    'attachments' => new external_multiple_structure(
                        new external_single_structure([
                            'filename' => new external_value(PARAM_FILE, 'Attachment file name'),
                            'url' => new external_value(PARAM_URL, 'Download URL'),
                            'size' => new external_value(PARAM_INT, 'Size in bytes'),
                        ]),
                        'Attachments of the message',
                        VALUE_DEFAULT,
                        []
                    ),
                ])
            ),
        ]);
    }
}
