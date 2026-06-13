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

namespace mod_coursemail\completion;

use core_completion\activity_custom_completion;
use mod_coursemail\local\completion_calculator;

/**
 * Custom completion rules for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns the completion state for the given rule.
     *
     * @param string $rule The completion rule (one of the defined custom rules).
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $context = \context_module::instance($this->cm->id);
        $calculator = new completion_calculator($this->cm->instance, $context);

        switch ($rule) {
            case 'completionmail':
                $requireread = (bool) $DB->get_field(
                    'coursemail',
                    'completionrequireread',
                    ['id' => $this->cm->instance],
                    MUST_EXIST
                );
                $complete = $calculator->is_complete($this->userid, $requireread);
                break;
            default:
                $complete = false;
        }

        return $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Returns the custom completion rules defined by this module.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionmail',
        ];
    }

    /**
     * Returns the human-readable description of each defined custom rule.
     *
     * @return array<string, string>
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionmail' => get_string('completionmail', 'coursemail'),
        ];
    }

    /**
     * Returns the sort order of the completion rules for display.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionmail',
        ];
    }
}
