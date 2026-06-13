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
use mod_coursemail\local\mailbox;
use mod_coursemail\local\conversation;
use mod_coursemail\local\completion_updater;
use mod_coursemail\event\conversation_completed;

/**
 * External function: a teacher marks (or reopens) a student as completed in a
 * conversation flagged for manual completion.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_recipient_completed extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
            'userid' => new external_value(PARAM_INT, 'Student user id to mark'),
            'completed' => new external_value(PARAM_BOOL, 'True to mark complete, false to reopen'),
        ]);
    }

    /**
     * Records the per-student manual completion state and recomputes completion.
     *
     * @param int $cmid Course module id.
     * @param int $conversationid Conversation id.
     * @param int $userid Student user id.
     * @param bool $completed Whether to mark complete (true) or reopen (false).
     * @return array
     */
    public static function execute($cmid, $conversationid, $userid, $completed) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'conversationid' => $conversationid,
            'userid' => $userid,
            'completed' => $completed,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        // Marking manual completion is a teaching action, gated on the send capability.
        require_capability('mod/coursemail:send', $context);

        $conversation = new conversation($params['conversationid']);
        if ($conversation->get('coursemailid') != $cm->instance) {
            throw new \invalid_parameter_exception('Conversation does not belong to this activity.');
        }
        if (!$conversation->get('requiresmanualcomplete')) {
            throw new \invalid_parameter_exception('Conversation is not set for manual completion.');
        }

        $mailbox = new mailbox($cm->instance);

        // Only an actual recipient (student) of the thread can be marked.
        $recipientids = $mailbox->get_recipient_userids($conversation->get('id'), 0);
        if (!in_array($params['userid'], $recipientids, true)) {
            throw new \invalid_parameter_exception('User is not a recipient of this conversation.');
        }

        $changed = $mailbox->set_manual_completed(
            $conversation->get('id'),
            $params['userid'],
            $params['completed'],
            $USER->id
        );

        if ($changed) {
            completion_updater::update_for_users($cm, [$params['userid']]);
            if ($params['completed']) {
                conversation_completed::create([
                    'context' => $context,
                    'objectid' => $conversation->get('id'),
                    'relateduserid' => $params['userid'],
                ])->trigger();
            }
        }

        // Recompute the chip's "completed by ... on ..." label for the client.
        $completedinfo = '';
        if ($params['completed']) {
            $manual = $mailbox->get_manual_completed($conversation->get('id'));
            if (isset($manual[$params['userid']])) {
                $completedinfo = get_string('completedby', 'coursemail', (object) [
                    'user' => helper::user_fullname($manual[$params['userid']]['completedby']),
                    'date' => userdate($manual[$params['userid']]['timecompleted']),
                ]);
            }
        }

        return [
            'completed' => (bool) $params['completed'],
            'completedinfo' => $completedinfo,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'completed' => new external_value(PARAM_BOOL, 'The resulting completed state'),
            'completedinfo' => new external_value(PARAM_TEXT, 'Who marked it completed and when (empty if reopened)'),
        ]);
    }
}
