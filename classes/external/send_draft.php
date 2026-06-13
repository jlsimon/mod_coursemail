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
use mod_coursemail\local\message;
use mod_coursemail\local\recipients;

/**
 * External function: send a previously saved draft to chosen recipients.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_draft extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'draftid' => new external_value(PARAM_INT, 'Draft message id'),
            'subject' => new external_value(PARAM_TEXT, 'Subject'),
            'body' => new external_value(PARAM_RAW, 'Body'),
            'bodyformat' => new external_value(PARAM_INT, 'Body format', VALUE_DEFAULT, FORMAT_MOODLE),
            'requiresresponse' => new external_value(PARAM_BOOL, 'Whether a response is required', VALUE_DEFAULT, false),
            'recipienttype' => new external_value(PARAM_ALPHA, 'users|group|class|staff|staffselected'),
            'recipientids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User id or group id'),
                'Ids',
                VALUE_DEFAULT,
                []
            ),
            'draftitemid' => new external_value(PARAM_INT, 'Attachment draft area item id', VALUE_DEFAULT, 0),
            'requiresmanualcomplete' => new external_value(
                PARAM_BOOL,
                'Whether completion is granted manually by staff, per student',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Updates and sends the draft.
     *
     * @param int $cmid Course module id.
     * @param int $draftid Draft message id.
     * @param string $subject Subject.
     * @param string $body Body.
     * @param int $bodyformat Body format.
     * @param bool $requiresresponse Whether a response is required.
     * @param string $recipienttype Targeting mode.
     * @param int[] $recipientids Ids depending on mode.
     * @param int $draftitemid Attachment draft area item id (0 = none).
     * @param bool $requiresmanualcomplete Whether completion is granted manually by staff.
     * @return array
     */
    public static function execute(
        $cmid,
        $draftid,
        $subject,
        $body,
        $bodyformat,
        $requiresresponse,
        $recipienttype,
        $recipientids,
        $draftitemid = 0,
        $requiresmanualcomplete = false
    ) {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'draftid' => $draftid,
            'subject' => $subject,
            'body' => $body,
            'bodyformat' => $bodyformat,
            'requiresresponse' => $requiresresponse,
            'recipienttype' => $recipienttype,
            'recipientids' => $recipientids,
            'draftitemid' => $draftitemid,
            'requiresmanualcomplete' => $requiresmanualcomplete,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        $draft = new message($params['draftid']);
        if (!$draft->get('draft') || $draft->get('userid') != $USER->id) {
            throw new \moodle_exception('invaliddraft', 'coursemail');
        }
        if (
            !$DB->record_exists(
                'coursemail_conversations',
                ['id' => $draft->get('conversationid'), 'coursemailid' => $cm->instance]
            )
        ) {
            throw new \moodle_exception('invaliddraft', 'coursemail');
        }

        if (in_array($params['recipienttype'], ['staff', 'staffselected'], true)) {
            require_capability('mod/coursemail:reply', $context);
            $params['requiresresponse'] = false;
            $params['requiresmanualcomplete'] = false;
        }

        $recipientids = recipients::resolve(
            $cm,
            $context,
            $USER->id,
            $params['recipienttype'],
            $params['recipientids']
        );
        if (empty($recipientids)) {
            throw new \moodle_exception('norecipients', 'coursemail');
        }

        $mailbox = new mailbox($cm->instance);

        // Persist any edits, set the requires-response flag, then send.
        $mailbox->update_draft($params['draftid'], $params['subject'], $params['body'], $params['bodyformat']);
        $conversation = new \mod_coursemail\local\conversation($draft->get('conversationid'));
        $conversation->set('requiresresponse', $params['requiresresponse'] ? 1 : 0);
        $conversation->set('requiresmanualcomplete', $params['requiresmanualcomplete'] ? 1 : 0);
        $conversation->update();

        $message = $mailbox->send_draft($params['draftid'], $recipientids);
        \mod_coursemail\local\attachments::save_from_draft($params['draftitemid'], $context, $message->get('id'));
        helper::fire_started($context, $message);
        \mod_coursemail\local\completion_updater::update_for_users($cm, $recipientids);
        \mod_coursemail\local\notifier::notify_message($cm, $message, $recipientids, $params['requiresresponse']);

        return [
            'conversationid' => $message->get('conversationid'),
            'messageid' => $message->get('id'),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
            'messageid' => new external_value(PARAM_INT, 'Message id'),
        ]);
    }
}
