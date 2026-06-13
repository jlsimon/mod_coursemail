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

namespace mod_coursemail\event;

/**
 * Event fired when a user reads a message (records a read receipt).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *     Extra information about the event.
 *     - int conversationid: the conversation the message belongs to.
 * }
 */
class message_read extends \core\event\base {
    /**
     * Initialises the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'coursemail_messages';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventmessageread', 'coursemail');
    }

    /**
     * Returns a non-localised description of the event.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' read the message with id '{$this->objectid}' " .
            "in the coursemail activity with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Returns the URL related to the event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/coursemail/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validates the custom event data.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' (message id) must be set.');
        }
        if (!isset($this->other['conversationid'])) {
            throw new \coding_exception('The \'conversationid\' value must be set in other.');
        }
    }

    /**
     * Maps the object id for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'coursemail_messages', 'restore' => 'coursemail_message'];
    }

    /**
     * Maps fields in the 'other' array for backup/restore.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['conversationid' => ['db' => 'coursemail_conversations', 'restore' => 'coursemail_conversation']];
    }
}
