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

/**
 * PHPUnit data generator for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generator class. The base class creates instances via coursemail_add_instance();
 * defaults are provided here so tests can create an instance with no extra data.
 */
class mod_coursemail_generator extends \testing_module_generator {
    /**
     * Creates a new coursemail instance.
     *
     * @param array|stdClass|null $record Instance data overrides.
     * @param array|null $options Generator options.
     * @return stdClass The created instance record.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) $record;

        $defaults = [
            'requireresponsedefault' => 0,
            'completionmail' => 0,
            'completionrequireread' => 1,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($record[$key])) {
                $record[$key] = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
