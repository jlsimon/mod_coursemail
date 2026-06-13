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
use mod_coursemail\local\completion_updater;
use mod_coursemail\event\message_read;

/**
 * External function: mark several conversations read or unread at once.
 *
 * Operates on the current user's own read state, within a single activity instance.
 * Conversations the user neither takes part in (nor may supervise) are skipped.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_mark extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'conversationids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Conversation id')
            ),
            'read' => new external_value(PARAM_BOOL, 'True to mark read, false to mark unread'),
        ]);
    }

    /**
     * Marks the given conversations read or unread for the current user.
     *
     * @param int $cmid Course module id.
     * @param int[] $conversationids Conversation ids.
     * @param bool $read Whether to mark read (true) or unread (false).
     * @return array
     */
    public static function execute($cmid, $conversationids, $read) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'conversationids' => $conversationids,
            'read' => $read,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        $mailbox = new mailbox($cm->instance);
        $userid = $USER->id;
        $canviewall = has_capability('mod/coursemail:viewall', $context);

        $changed = 0;
        foreach (array_unique(array_map('intval', $params['conversationids'])) as $conversationid) {
            if (!conversation::record_exists($conversationid)) {
                continue;
            }
            $conversation = new conversation($conversationid);
            // Only conversations of this activity, that the user may act on.
            if ($conversation->get('coursemailid') != $cm->instance) {
                continue;
            }
            if (!$canviewall && !$mailbox->user_participates($conversationid, $userid)) {
                continue;
            }

            if ($params['read']) {
                $newlyread = $mailbox->mark_conversation_read($conversationid, $userid);
                foreach ($newlyread as $messageid) {
                    message_read::create([
                        'context' => $context,
                        'objectid' => $messageid,
                        'other' => ['conversationid' => $conversationid],
                    ])->trigger();
                }
                if (!empty($newlyread)) {
                    $changed++;
                }
            } else {
                if (!empty($mailbox->mark_conversation_unread($conversationid, $userid))) {
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            completion_updater::update_for_users($cm, [$userid]);
        }

        return ['count' => $changed];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of conversations whose state changed'),
        ]);
    }
}
