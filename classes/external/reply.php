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

/**
 * External function: reply within an existing conversation.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reply extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
            'body' => new external_value(PARAM_RAW, 'Message body'),
            'bodyformat' => new external_value(PARAM_INT, 'Body format', VALUE_DEFAULT, FORMAT_MOODLE),
            'draftitemid' => new external_value(PARAM_INT, 'Attachment draft area item id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Adds the reply.
     *
     * @param int $cmid Course module id.
     * @param int $conversationid Conversation id.
     * @param string $body Body.
     * @param int $bodyformat Body format.
     * @param int $draftitemid Attachment draft area item id (0 = none).
     * @return array
     */
    public static function execute($cmid, $conversationid, $body, $bodyformat = FORMAT_MOODLE, $draftitemid = 0) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'conversationid' => $conversationid,
            'body' => $body,
            'bodyformat' => $bodyformat,
            'draftitemid' => $draftitemid,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:reply', $context);

        $mailbox = new mailbox($cm->instance);

        $conversation = new conversation($params['conversationid']);
        if ($conversation->get('coursemailid') != $cm->instance) {
            throw new \invalid_parameter_exception('Conversation does not belong to this activity.');
        }
        if (!$mailbox->user_participates($conversation->get('id'), $USER->id)) {
            throw new \moodle_exception('notparticipant', 'coursemail');
        }

        $message = $mailbox->reply($conversation->get('id'), $USER->id, $params['body'], $params['bodyformat']);
        \mod_coursemail\local\attachments::save_from_draft($params['draftitemid'], $context, $message->get('id'));
        helper::fire_replied($context, $message);

        // A reply changes the replier's reply-state and (if the replier is staff)
        // the recipients' read-state; recompute for all participants.
        $participants = $mailbox->get_participant_ids($conversation->get('id'));
        \mod_coursemail\local\completion_updater::update_for_users($cm, $participants);
        // Notify the other participants of the reply (it does not itself block progress).
        \mod_coursemail\local\notifier::notify_message($cm, $message, $participants, false);

        return ['messageid' => $message->get('id')];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'messageid' => new external_value(PARAM_INT, 'Reply message id'),
        ]);
    }
}
