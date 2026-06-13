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

/**
 * External function: mark a conversation as unread again for the current user.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mark_unread extends external_api {
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
     * Clears the current user's read receipts for the conversation and recomputes
     * completion (removing receipts may reopen the "read" rule).
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

        // Ensure the conversation belongs to this activity instance.
        $conversation = new conversation($params['conversationid']);
        if ($conversation->get('coursemailid') != $cm->instance) {
            throw new \invalid_parameter_exception('Conversation does not belong to this activity.');
        }

        $mailbox = new mailbox($cm->instance);
        $userid = $USER->id;

        $canviewall = has_capability('mod/coursemail:viewall', $context);
        if (!$canviewall && !$mailbox->user_participates($conversation->get('id'), $userid)) {
            throw new \required_capability_exception($context, 'mod/coursemail:viewall', 'nopermissions', '');
        }

        $changed = $mailbox->mark_conversation_unread($conversation->get('id'), $userid);
        if (!empty($changed)) {
            completion_updater::update_for_users($cm, [$userid]);
        }

        return ['success' => !empty($changed)];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether any message was marked unread'),
        ]);
    }
}
