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

/**
 * External function: search conversations by subject or body.
 *
 * Returns the same item shape as {@see get_folder} so the message-list template
 * can render the results. A user searches their own conversations; a user with
 * mod/coursemail:viewall searches every conversation in scope (supervision).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_messages extends external_api {
    /** @var int Minimum query length (shorter queries return no results). */
    const MIN_QUERY = 2;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'query' => new external_value(PARAM_TEXT, 'Search text (subject or body substring)'),
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
            'scope' => new external_value(
                PARAM_ALPHA,
                'Read scope: "activity" (this instance) or "course" (every coursemail of the course)',
                VALUE_DEFAULT,
                'activity'
            ),
        ]);
    }

    /**
     * Returns the conversations matching the query for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $query Search text.
     * @param int $page Zero-based page number.
     * @param string $scope Read scope: "activity" or "course".
     * @return array
     */
    public static function execute($cmid, $query, $page = 0, $scope = 'activity') {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'query' => $query,
            'page' => $page,
            'scope' => $scope,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        $needle = trim($params['query']);
        if (\core_text::strlen($needle) < self::MIN_QUERY) {
            return ['items' => [], 'hasmore' => false, 'page' => 0];
        }

        $mailbox = new mailbox($cm->instance);
        $userid = $USER->id;
        // A supervisor searches every conversation in scope; everyone else their own.
        $includeall = has_capability('mod/coursemail:viewall', $context);

        // In course scope aggregate the readable (or supervisable) instances, as folders do.
        $instances = null;
        if ($params['scope'] === 'course') {
            $instances = $includeall
                ? \mod_coursemail\local\scope::supervisable_instances($cm->course, $userid)
                : \mod_coursemail\local\scope::course_instances($cm->course, $userid);
            $mailbox->set_read_instances(array_keys($instances));
        }

        $perpage = (int) get_config('mod_coursemail', 'perpage');
        if ($perpage <= 0) {
            $perpage = get_folder::PERPAGE_DEFAULT;
        }
        $page = max(0, $params['page']);
        $limitfrom = $page * $perpage;
        $limitnum = $perpage + 1;

        $records = $mailbox->search_conversations($userid, $needle, $limitfrom, $limitnum, $includeall);
        [$records, $hasmore] = get_folder::trim($records, $perpage);
        $items = get_folder::conversation_items(
            $mailbox,
            $records,
            $userid,
            $context,
            false,
            (int) $cm->id,
            $instances
        );

        return ['items' => $items, 'hasmore' => $hasmore, 'page' => $page];
    }

    /**
     * Describes the return value (identical to get_folder).
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return get_folder::execute_returns();
    }
}
