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
 * Persistent representing a conversation (thread) within a coursemail instance.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation extends persistent {
    /** @var string The associated database table. */
    const TABLE = 'coursemail_conversations';

    /**
     * Defines the properties of this persistent.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'coursemailid' => [
                'type' => PARAM_INT,
            ],
            'subject' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'creatorid' => [
                'type' => PARAM_INT,
            ],
            'requiresresponse' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'requiresmanualcomplete' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
        ];
    }

    /**
     * Returns the messages of this conversation ordered chronologically.
     *
     * @param bool $includedrafts Whether to include draft messages.
     * @return message[]
     */
    public function get_messages($includedrafts = false) {
        $filters = ['conversationid' => $this->get('id')];
        if (!$includedrafts) {
            $filters['draft'] = 0;
        }
        return message::get_records($filters, 'timecreated', 'ASC');
    }
}
