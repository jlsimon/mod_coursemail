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

namespace mod_coursemail\local;

use core\persistent;

/**
 * Persistent representing a single message within a conversation.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message extends persistent {
    /** @var string The associated database table. */
    const TABLE = 'coursemail_messages';

    /**
     * Defines the properties of this persistent.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'conversationid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'body' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'bodyformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_HTML,
            ],
            'draft' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'timesent' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }
}
