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
use mod_coursemail\local\conversation;

/**
 * External function: star or unstar a message for the current user.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_starred extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'messageid' => new external_value(PARAM_INT, 'Message id'),
            'starred' => new external_value(PARAM_BOOL, 'Whether the message should be starred'),
        ]);
    }

    /**
     * Sets or clears the starred flag for the current user.
     *
     * @param int $cmid Course module id.
     * @param int $messageid Message id.
     * @param bool $starred Whether to star the message.
     * @return array
     */
    public static function execute($cmid, $messageid, $starred) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'messageid' => $messageid,
            'starred' => $starred,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        // Ensure the message belongs to this activity instance, not another one the
        // user also participates in.
        $message = new message($params['messageid']);
        $conversation = new conversation($message->get('conversationid'));
        if ($conversation->get('coursemailid') != $cm->instance) {
            throw new \invalid_parameter_exception('Message does not belong to this activity.');
        }

        $mailbox = new mailbox($cm->instance);
        $success = $mailbox->set_starred($params['messageid'], $USER->id, $params['starred']);

        return [
            'success' => $success,
            'starred' => $success ? (bool) $params['starred'] : false,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the state was updated'),
            'starred' => new external_value(PARAM_BOOL, 'The resulting starred state'),
        ]);
    }
}
