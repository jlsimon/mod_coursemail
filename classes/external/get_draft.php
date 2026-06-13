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
use mod_coursemail\local\message;

/**
 * External function: fetch the editable content of a draft message.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_draft extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'draftid' => new external_value(PARAM_INT, 'Draft message id'),
        ]);
    }

    /**
     * Returns the draft content for the composer.
     *
     * @param int $cmid Course module id.
     * @param int $draftid Draft message id.
     * @return array
     */
    public static function execute($cmid, $draftid) {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'draftid' => $draftid,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        $message = new message($params['draftid']);
        if (!$message->get('draft') || $message->get('userid') != $USER->id) {
            throw new \moodle_exception('invaliddraft', 'coursemail');
        }
        $conversation = $DB->get_record(
            'coursemail_conversations',
            ['id' => $message->get('conversationid'), 'coursemailid' => $cm->instance],
            '*',
            MUST_EXIST
        );

        // Stage the draft's current attachments into a fresh draft area for editing.
        $draftitemid = \mod_coursemail\local\attachments::prepare_draft($context, $message->get('id'));

        return [
            'draftid' => $message->get('id'),
            'conversationid' => $message->get('conversationid'),
            'subject' => $conversation->subject,
            'body' => $message->get('body'),
            'bodyformat' => $message->get('bodyformat'),
            'draftitemid' => $draftitemid,
            'attachments' => \mod_coursemail\local\attachments::draft_files($draftitemid),
        ];
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
            'subject' => new external_value(PARAM_TEXT, 'Subject'),
            'body' => new external_value(PARAM_RAW, 'Raw body for editing'),
            'bodyformat' => new external_value(PARAM_INT, 'Body format'),
            'draftitemid' => new external_value(PARAM_INT, 'Attachment draft area item id', VALUE_DEFAULT, 0),
            'attachments' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'size' => new external_value(PARAM_INT, 'Size in bytes'),
                ]),
                'Staged attachments',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}
