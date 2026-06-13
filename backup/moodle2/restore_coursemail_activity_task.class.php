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
 * Restore task for mod_coursemail.
 *
 * @package    mod_coursemail
 * @category   backup
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/coursemail/backup/moodle2/restore_coursemail_stepslib.php');

/**
 * Restore task that provides the settings and steps to restore one coursemail activity.
 */
class restore_coursemail_activity_task extends restore_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the restore step for the coursemail structure.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_coursemail_activity_structure_step('coursemail_structure', 'coursemail.xml'));
    }

    /**
     * Defines the contents in the activity that must be processed by the link decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('coursemail', ['intro'], 'coursemail');
        $contents[] = new restore_decode_content('coursemail_messages', ['body'], 'coursemail_message');

        return $contents;
    }

    /**
     * Defines the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('COURSEMAILVIEWBYID', '/mod/coursemail/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('COURSEMAILINDEX', '/mod/coursemail/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Defines the restore log rules that will be applied when restoring coursemail logs.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('coursemail', 'add', 'view.php?id={course_module}', '{coursemail}');
        $rules[] = new restore_log_rule('coursemail', 'update', 'view.php?id={course_module}', '{coursemail}');
        $rules[] = new restore_log_rule('coursemail', 'view', 'view.php?id={course_module}', '{coursemail}');

        return $rules;
    }

    /**
     * Defines the restore log rules applied to course logs by the restore final task.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('coursemail', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
