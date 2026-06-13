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

use mod_coursemail\local\testcourse_generator;

/**
 * Tests for the demo course generator.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\testcourse_generator
 */
class testcourse_test extends \advanced_testcase {
    /**
     * The full build produces the course, accounts, groups, activities and messages.
     *
     * @return void
     */
    public function test_create_builds_full_course() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = testcourse_generator::create();

        // Course.
        $this->assertEquals(testcourse_generator::COURSE_SHORTNAME, $course->shortname);

        // Accounts exist.
        foreach (array_keys(testcourse_generator::teachers()) as $username) {
            $this->assertTrue($DB->record_exists('user', ['username' => $username]));
        }
        foreach (testcourse_generator::students() as $student) {
            $this->assertTrue($DB->record_exists('user', ['username' => $student[0]]));
        }

        // Enrolment: 8 students + 2 teachers.
        $context = \context_course::instance($course->id);
        $this->assertCount(10, get_enrolled_users($context));

        // Two groups, four members each.
        $groups = groups_get_all_groups($course->id);
        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            $this->assertCount(4, groups_get_members($group->id));
        }

        // Three coursemail instances.
        $instances = $DB->get_records('coursemail', ['course' => $course->id]);
        $this->assertCount(3, $instances);

        // The welcome mailbox has one class-wide conversation with eight recipients.
        $welcome = $DB->get_record(
            'coursemail',
            ['course' => $course->id, 'name' => get_string('testcoursemail1name', 'coursemail')]
        );
        $this->assertEquals(1, $this->count_conversations($welcome->id));
        $this->assertEquals(8, $this->count_recipients($welcome->id));
        $this->assertEquals(1, $this->count_requiresresponse($welcome->id));

        // The pre-exam mailbox has eight personalised conversations, one recipient each.
        $exam = $DB->get_record(
            'coursemail',
            ['course' => $course->id, 'name' => get_string('testcoursemail3name', 'coursemail')]
        );
        $this->assertEquals(8, $this->count_conversations($exam->id));
        $this->assertEquals(8, $this->count_recipients($exam->id));
        $this->assertEquals(8, $this->count_requiresresponse($exam->id));
    }

    /**
     * The mailboxes track completion by reply and later content is gated on them.
     *
     * @return void
     */
    public function test_create_sets_completion_and_gating() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = testcourse_generator::create();

        $mail1 = $DB->get_record(
            'coursemail',
            ['course' => $course->id, 'name' => get_string('testcoursemail1name', 'coursemail')]
        );
        $mail2 = $DB->get_record(
            'coursemail',
            ['course' => $course->id, 'name' => get_string('testcoursemail2name', 'coursemail')]
        );
        $cm1 = get_coursemodule_from_instance('coursemail', $mail1->id);
        $cm2 = get_coursemodule_from_instance('coursemail', $mail2->id);

        // The first mailbox completes automatically (must reply).
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, $cm1->completion);
        $this->assertEquals(1, $mail1->completionmail);

        // The second mailbox is unavailable until the first is complete.
        $availability = $DB->get_field('course_modules', 'availability', ['id' => $cm2->id]);
        $this->assertNotEmpty($availability);
        $this->assertStringContainsString('"type":"completion"', $availability);
        $this->assertStringContainsString('"cm":' . $cm1->id, $availability);
    }

    /**
     * A second run aborts because the demo course already exists.
     *
     * @return void
     */
    public function test_create_aborts_if_course_exists() {
        $this->resetAfterTest();
        $this->setAdminUser();

        testcourse_generator::create();

        $this->expectException(\moodle_exception::class);
        testcourse_generator::create();
    }

    /**
     * Counts non-draft conversations in a coursemail instance.
     *
     * @param int $coursemailid Instance id.
     * @return int
     */
    protected function count_conversations($coursemailid) {
        global $DB;
        return $DB->count_records('coursemail_conversations', ['coursemailid' => $coursemailid]);
    }

    /**
     * Counts conversations marked as requiring a response.
     *
     * @param int $coursemailid Instance id.
     * @return int
     */
    protected function count_requiresresponse($coursemailid) {
        global $DB;
        return $DB->count_records(
            'coursemail_conversations',
            ['coursemailid' => $coursemailid, 'requiresresponse' => 1]
        );
    }

    /**
     * Counts recipient rows (role = 1) across all messages of an instance.
     *
     * @param int $coursemailid Instance id.
     * @return int
     */
    protected function count_recipients($coursemailid) {
        global $DB;
        $sql = "SELECT COUNT(mu.id)
                  FROM {coursemail_message_users} mu
                  JOIN {coursemail_messages} m ON m.id = mu.messageid
                  JOIN {coursemail_conversations} c ON c.id = m.conversationid
                 WHERE c.coursemailid = :cmid AND mu.role = 1";
        return $DB->count_records_sql($sql, ['cmid' => $coursemailid]);
    }
}
