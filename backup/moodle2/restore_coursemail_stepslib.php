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
 * Restore structure step for mod_coursemail.
 *
 * @package    mod_coursemail
 * @category   backup
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the structure step to restore one coursemail activity.
 */
class restore_coursemail_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the structure to be restored.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('coursemail', '/activity/coursemail');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'coursemail_conversation',
                '/activity/coursemail/conversations/conversation'
            );
            $paths[] = new restore_path_element(
                'coursemail_manualcomplete',
                '/activity/coursemail/conversations/conversation/manualcompletions/manualcomplete'
            );
            $paths[] = new restore_path_element(
                'coursemail_message',
                '/activity/coursemail/conversations/conversation/messages/message'
            );
            $paths[] = new restore_path_element(
                'coursemail_message_user',
                '/activity/coursemail/conversations/conversation/messages/message/message_users/message_user'
            );
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores a coursemail instance.
     *
     * @param array $data Parsed element data.
     */
    protected function process_coursemail($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Backwards compatibility: backups taken before the completion rules were
        // collapsed carry the legacy completionread/reply/manual flags. Map them
        // onto the single rule, keeping the read baseline only when reading was
        // previously required (read or reply). See db/upgrade.php for the same map.
        if (!isset($data->completionmail) && isset($data->completionread)) {
            $data->completionmail = (!empty($data->completionread)
                || !empty($data->completionreply) || !empty($data->completionmanual)) ? 1 : 0;
            $data->completionrequireread = (!empty($data->completionread)
                || !empty($data->completionreply)) ? 1 : 0;
        }
        unset($data->completionread, $data->completionreply, $data->completionmanual);

        $newitemid = $DB->insert_record('coursemail', $data);

        // Immediately after inserting the record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores a conversation.
     *
     * @param array $data Parsed element data.
     */
    protected function process_coursemail_conversation($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->coursemailid = $this->get_new_parentid('coursemail');
        $data->creatorid = $this->get_mappingid('user', $data->creatorid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('coursemail_conversations', $data);
        $this->set_mapping('coursemail_conversation', $oldid, $newitemid);
    }

    /**
     * Restores a per-student manual completion row.
     *
     * @param array $data Parsed element data.
     */
    protected function process_coursemail_manualcomplete($data) {
        global $DB;

        $data = (object) $data;

        $data->conversationid = $this->get_new_parentid('coursemail_conversation');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->completedby = $this->get_mappingid('user', $data->completedby);
        $data->timecompleted = $this->apply_date_offset($data->timecompleted);

        $DB->insert_record('coursemail_manualcomplete', $data);
    }

    /**
     * Restores a message.
     *
     * @param array $data Parsed element data.
     */
    protected function process_coursemail_message($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->conversationid = $this->get_new_parentid('coursemail_conversation');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timesent = $this->apply_date_offset($data->timesent);

        $newitemid = $DB->insert_record('coursemail_messages', $data);
        $this->set_mapping('coursemail_message', $oldid, $newitemid);
    }

    /**
     * Restores the per-user state of a message.
     *
     * @param array $data Parsed element data.
     */
    protected function process_coursemail_message_user($data) {
        global $DB;

        $data = (object) $data;

        $data->messageid = $this->get_new_parentid('coursemail_message');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timeread = $this->apply_date_offset($data->timeread);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $DB->insert_record('coursemail_message_users', $data);
    }

    /**
     * Restores files belonging to the activity once the structure is in place.
     */
    protected function after_execute() {
        // Add coursemail related files (intro field has no itemid).
        $this->add_related_files('mod_coursemail', 'intro', null);
        // Message attachments (itemid = message id, mapped via 'coursemail_message').
        $this->add_related_files('mod_coursemail', 'attachment', 'coursemail_message');
    }
}
