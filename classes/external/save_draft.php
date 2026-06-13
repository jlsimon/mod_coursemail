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
use mod_coursemail\local\message;

/**
 * External function: create or update a (new-thread) draft message.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_draft extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'draftid' => new external_value(PARAM_INT, 'Existing draft message id, or 0 to create', VALUE_DEFAULT, 0),
            'subject' => new external_value(PARAM_TEXT, 'Subject'),
            'body' => new external_value(PARAM_RAW, 'Body'),
            'bodyformat' => new external_value(PARAM_INT, 'Body format', VALUE_DEFAULT, FORMAT_MOODLE),
            'draftitemid' => new external_value(PARAM_INT, 'Attachment draft area item id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Saves the draft.
     *
     * @param int $cmid Course module id.
     * @param int $draftid Draft message id or 0.
     * @param string $subject Subject.
     * @param string $body Body.
     * @param int $bodyformat Body format.
     * @param int $draftitemid Attachment draft area item id (0 = none).
     * @return array
     */
    public static function execute($cmid, $draftid, $subject, $body, $bodyformat = FORMAT_MOODLE, $draftitemid = 0) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'draftid' => $draftid,
            'subject' => $subject,
            'body' => $body,
            'bodyformat' => $bodyformat,
            'draftitemid' => $draftitemid,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        if (
            !has_capability('mod/coursemail:send', $context)
                && !has_capability('mod/coursemail:reply', $context)
        ) {
            throw new \required_capability_exception($context, 'mod/coursemail:reply', 'nopermissions', '');
        }

        $mailbox = new mailbox($cm->instance);

        if (!empty($params['draftid'])) {
            $existing = new message($params['draftid']);
            self::require_own_draft($existing, $cm->instance, $USER->id);
            $message = $mailbox->update_draft(
                $params['draftid'],
                $params['subject'],
                $params['body'],
                $params['bodyformat']
            );
        } else {
            $message = $mailbox->save_draft(
                $USER->id,
                $params['subject'],
                $params['body'],
                $params['bodyformat']
            );
        }

        \mod_coursemail\local\attachments::save_from_draft($params['draftitemid'], $context, $message->get('id'));

        return [
            'draftid' => $message->get('id'),
            'conversationid' => $message->get('conversationid'),
        ];
    }

    /**
     * Ensures the message is a draft owned by the user within this instance.
     *
     * @param message $message The message.
     * @param int $coursemailid Instance id.
     * @param int $userid User id.
     */
    protected static function require_own_draft($message, $coursemailid, $userid) {
        global $DB;

        if (!$message->get('draft') || $message->get('userid') != $userid) {
            throw new \moodle_exception('invaliddraft', 'coursemail');
        }
        $belongs = $DB->record_exists(
            'coursemail_conversations',
            ['id' => $message->get('conversationid'), 'coursemailid' => $coursemailid]
        );
        if (!$belongs) {
            throw new \moodle_exception('invaliddraft', 'coursemail');
        }
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'draftid' => new external_value(PARAM_INT, 'Draft message id'),
            'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
        ]);
    }
}
