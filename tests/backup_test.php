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

namespace mod_coursemail;

use mod_coursemail\external\start_conversation;
use mod_coursemail\external\reply;
use mod_coursemail\local\mailbox;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_coursemail_activity_structure_step
 * @covers     \restore_coursemail_activity_structure_step
 */
class backup_test extends \advanced_testcase {
    /**
     * Backs up a whole course (with user data) and restores it into a brand new course.
     *
     * @param \stdClass $course The source course.
     * @return int The id of the newly restored course.
     */
    protected function backup_and_restore($course, $userdata = true) {
        global $CFG, $USER;

        $coursecontext = \context_course::instance($course->id);

        // Run the backup.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        if (!$userdata) {
            // Keep course-level user data on (so the backup file is produced) but turn off
            // the coursemail activity's own userinfo, exercising the no-userinfo backup path.
            foreach ($bc->get_plan()->get_settings() as $setting) {
                $name = $setting->get_name();
                if (substr($name, 0, 11) === 'coursemail_' && substr($name, -9) === '_userinfo') {
                    $setting->set_status(\backup_setting::NOT_LOCKED);
                    $setting->set_value(false);
                }
            }
        }
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Extract the produced backup file into the temp area the restore expects.
        $fs = get_file_storage();
        $files = $fs->get_area_files($coursecontext->id, 'backup', 'course', false, 'id ASC');
        $backupfile = reset($files);
        $path = $CFG->tempdir . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $backupid;
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($backupfile, $path);

        // Restore into a brand new course.
        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_copy',
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // The backup/restore controllers raise the PHP time limit; reset it so PHPUnit
        // does not flag the test as risky for leaving max_execution_time changed.
        set_time_limit(0);

        return $newcourseid;
    }

    /**
     * A full instance with conversations, messages and per-user state survives a
     * backup/restore cycle into a new course.
     */
    public function test_backup_restore_with_userdata(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('coursemail', [
            'course' => $course->id,
            'requireresponsedefault' => 1,
            'requiremanualcompletedefault' => 1,
            'completionmail' => 1,
            'completionrequireread' => 1,
        ]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');

        // Teacher opens a manual-completion conversation that requires a response; the
        // student replies, and the teacher marks the student as completed.
        $this->setUser($teacher);
        $result = start_conversation::execute(
            $instance->cmid,
            'Subject',
            'Hello body',
            FORMAT_MOODLE,
            true,
            'users',
            [$student->id],
            0,
            true
        );
        $conversationid = $result['conversationid'];

        $this->setUser($student);
        reply::execute($instance->cmid, $conversationid, 'Student answer');

        (new mailbox($instance->id))->set_manual_completed($conversationid, $student->id, true, $teacher->id);

        // Capture source counts.
        $convcount = $DB->count_records('coursemail_conversations', ['coursemailid' => $instance->id]);
        $this->assertEquals(1, $convcount);
        $srcmessages = $DB->get_records_sql(
            "SELECT m.id FROM {coursemail_messages} m
               JOIN {coursemail_conversations} c ON c.id = m.conversationid
              WHERE c.coursemailid = ?",
            [$instance->id]
        );
        $this->assertCount(2, $srcmessages);

        // Backup and restore into a new course.
        $this->setAdminUser();
        $newcourseid = $this->backup_and_restore($course);

        // Exactly one coursemail instance restored.
        $newinstances = $DB->get_records('coursemail', ['course' => $newcourseid]);
        $this->assertCount(1, $newinstances);
        $newinstance = reset($newinstances);

        // Settings preserved.
        $this->assertEquals(1, $newinstance->requireresponsedefault);
        $this->assertEquals(1, $newinstance->requiremanualcompletedefault);
        $this->assertEquals(1, $newinstance->completionmail);
        $this->assertEquals(1, $newinstance->completionrequireread);

        // Conversations preserved with their flags and creator mapping.
        $newconvs = $DB->get_records('coursemail_conversations', ['coursemailid' => $newinstance->id]);
        $this->assertCount(1, $newconvs);
        $newconv = reset($newconvs);
        $this->assertEquals('Subject', $newconv->subject);
        $this->assertEquals(1, $newconv->requiresresponse);
        $this->assertEquals(1, $newconv->requiresmanualcomplete);
        $this->assertEquals($teacher->id, $newconv->creatorid);

        // The per-student manual completion row is restored, with users remapped.
        $newmc = $DB->get_records('coursemail_manualcomplete', ['conversationid' => $newconv->id]);
        $this->assertCount(1, $newmc);
        $mc = reset($newmc);
        $this->assertEquals($student->id, $mc->userid);
        $this->assertEquals($teacher->id, $mc->completedby);

        // Two messages preserved (original + reply) authored by the right users.
        $newmessages = $DB->get_records('coursemail_messages', ['conversationid' => $newconv->id], 'timecreated ASC');
        $this->assertCount(2, $newmessages);
        $authors = array_map(function ($m) {
            return (int) $m->userid;
        }, array_values($newmessages));
        $this->assertContains((int) $teacher->id, $authors);
        $this->assertContains((int) $student->id, $authors);

        // Per-user state rows preserved (author + recipient per message).
        [$insql, $params] = $DB->get_in_or_equal(array_keys($newmessages));
        $newstate = $DB->count_records_select('coursemail_message_users', "messageid $insql", $params);
        $this->assertGreaterThanOrEqual(2, $newstate);

        // The restored mailbox is independent and queryable; the student sees the thread.
        $mailbox = new mailbox($newinstance->id);
        $this->assertEquals($newinstance->id, $mailbox->get_coursemailid());
        $this->assertCount(1, $mailbox->get_inbox_conversations($student->id));
    }

    /**
     * Without user data, the instance and its settings restore but no conversations do.
     */
    public function test_backup_restore_without_userdata(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('coursemail', [
            'course' => $course->id,
            'completionmail' => 1,
        ]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        start_conversation::execute(
            $instance->cmid,
            'Subject',
            'Body',
            FORMAT_MOODLE,
            true,
            'users',
            [$student->id]
        );

        // Backup excluding user information, then restore.
        $this->setAdminUser();
        $newcourseid = $this->backup_and_restore($course, false);

        $newinstances = $DB->get_records('coursemail', ['course' => $newcourseid]);
        $this->assertCount(1, $newinstances);
        $newinstance = reset($newinstances);
        $this->assertEquals(1, $newinstance->completionmail);

        // No conversations should have been restored.
        $this->assertEquals(0, $DB->count_records(
            'coursemail_conversations',
            ['coursemailid' => $newinstance->id]
        ));
    }
}
