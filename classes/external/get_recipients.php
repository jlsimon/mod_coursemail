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
use mod_coursemail\local\recipients;

/**
 * External function: list the recipient options available to the composer.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_recipients extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Returns the recipient options for the current user.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute($cmid) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        return recipients::composer_options($cm, $context, $USER->id);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'cansend' => new external_value(PARAM_BOOL, 'Whether the user can target individuals/class/groups'),
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User id'),
                    'name' => new external_value(PARAM_NOTAGS, 'Full name'),
                ])
            ),
            'groups' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Group id'),
                    'name' => new external_value(PARAM_TEXT, 'Group name'),
                ])
            ),
            'single' => new external_value(PARAM_BOOL, 'Student case: exactly one teacher is available'),
            'recipientname' => new external_value(PARAM_NOTAGS, 'Name of the single teacher, when applicable'),
            'norecipients' => new external_value(PARAM_BOOL, 'Student case: no teacher is available to write to'),
            'requiresresponsedefault' => new external_value(
                PARAM_BOOL,
                'Whether the requires-response switch should start ticked',
                VALUE_DEFAULT,
                false
            ),
            'requiremanualcompletedefault' => new external_value(
                PARAM_BOOL,
                'Whether the manual-completion switch should start ticked',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }
}
