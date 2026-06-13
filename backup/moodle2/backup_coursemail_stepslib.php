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
 * Backup structure step for mod_coursemail.
 *
 * @package    mod_coursemail
 * @category   backup
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete coursemail structure for backup, with file and id annotations.
 */
class backup_coursemail_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure of the module.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Whether the user requested to include user information in the backup.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $coursemail = new backup_nested_element('coursemail', ['id'], [
            'name', 'intro', 'introformat', 'requireresponsedefault', 'requiremanualcompletedefault',
            'completionmail', 'completionrequireread', 'timecreated', 'timemodified']);

        $conversations = new backup_nested_element('conversations');

        $conversation = new backup_nested_element('conversation', ['id'], [
            'subject', 'creatorid', 'requiresresponse', 'requiresmanualcomplete',
            'timecreated', 'timemodified', 'usermodified']);

        $manualcompletions = new backup_nested_element('manualcompletions');

        $manualcomplete = new backup_nested_element('manualcomplete', ['id'], [
            'userid', 'completedby', 'timecompleted']);

        $messages = new backup_nested_element('messages');

        $message = new backup_nested_element('message', ['id'], [
            'userid', 'body', 'bodyformat', 'draft',
            'timecreated', 'timemodified', 'timesent', 'usermodified']);

        $messageusers = new backup_nested_element('message_users');

        $messageuser = new backup_nested_element('message_user', ['id'], [
            'userid', 'role', 'unread', 'timeread', 'starred', 'timecreated']);

        // Build the tree.
        $coursemail->add_child($conversations);
        $conversations->add_child($conversation);

        $conversation->add_child($manualcompletions);
        $manualcompletions->add_child($manualcomplete);

        $conversation->add_child($messages);
        $messages->add_child($message);

        $message->add_child($messageusers);
        $messageusers->add_child($messageuser);

        // Define sources.
        $coursemail->set_source_table('coursemail', ['id' => backup::VAR_ACTIVITYID]);

        // Conversations, messages and per-user state are all user-generated data.
        if ($userinfo) {
            $conversation->set_source_table(
                'coursemail_conversations',
                ['coursemailid' => backup::VAR_PARENTID],
                'id ASC'
            );

            $manualcomplete->set_source_table(
                'coursemail_manualcomplete',
                ['conversationid' => backup::VAR_PARENTID],
                'id ASC'
            );

            $message->set_source_table(
                'coursemail_messages',
                ['conversationid' => backup::VAR_PARENTID],
                'id ASC'
            );

            $messageuser->set_source_table(
                'coursemail_message_users',
                ['messageid' => backup::VAR_PARENTID],
                'id ASC'
            );
        }

        // Define id annotations.
        $conversation->annotate_ids('user', 'creatorid');
        $conversation->annotate_ids('user', 'usermodified');
        $manualcomplete->annotate_ids('user', 'userid');
        $manualcomplete->annotate_ids('user', 'completedby');
        $message->annotate_ids('user', 'userid');
        $message->annotate_ids('user', 'usermodified');
        $messageuser->annotate_ids('user', 'userid');

        // Define file annotations (intro has no itemid; attachments use the message id).
        $coursemail->annotate_files('mod_coursemail', 'intro', null);
        $message->annotate_files('mod_coursemail', 'attachment', 'id');

        // Return the root element (coursemail), wrapped into standard activity structure.
        return $this->prepare_activity_structure($coursemail);
    }
}
