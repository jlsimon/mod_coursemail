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

use mod_coursemail\local\mailbox;
use mod_coursemail\local\scope;
use mod_coursemail\external\helper;
use mod_coursemail\external\get_folder;

/**
 * Tests for the unified course mailbox scope.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\scope
 * @covers     \mod_coursemail\external\get_folder
 */
class scope_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass First (visible) instance. */
    protected $maila;
    /** @var \stdClass Second (visible) instance. */
    protected $mailb;
    /** @var \stdClass Third instance, hidden from students. */
    protected $mailc;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student;

    /**
     * Sets up a course with three coursemail activities (two visible, one hidden).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        helper::reset_caches();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->maila = $generator->create_module('coursemail', ['course' => $this->course->id, 'name' => 'Mail A']);
        $this->mailb = $generator->create_module('coursemail', ['course' => $this->course->id, 'name' => 'Mail B']);
        $this->mailc = $generator->create_module(
            'coursemail',
            ['course' => $this->course->id, 'name' => 'Mail C', 'visible' => 0]
        );
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * Returns the mailbox service for an instance record.
     *
     * @param \stdClass $instance The coursemail instance.
     * @return mailbox
     */
    protected function mailbox($instance): mailbox {
        return new mailbox($instance->id);
    }

    /**
     * A student sees only the visible instances; a teacher also sees the hidden one.
     */
    public function test_course_instances_respects_visibility(): void {
        $forstudent = scope::course_instances($this->course->id, $this->student->id);
        $this->assertEqualsCanonicalizing(
            [(int) $this->maila->id, (int) $this->mailb->id],
            array_keys($forstudent)
        );
        // The map carries the source cmid and the activity name for each instance.
        $this->assertEquals((int) $this->maila->cmid, $forstudent[$this->maila->id]->cmid);
        $this->assertEquals('Mail A', $forstudent[$this->maila->id]->name);

        $forteacher = scope::course_instances($this->course->id, $this->teacher->id);
        $this->assertEqualsCanonicalizing(
            [(int) $this->maila->id, (int) $this->mailb->id, (int) $this->mailc->id],
            array_keys($forteacher)
        );
    }

    /**
     * Composable instances are those the user may send or reply in.
     */
    public function test_composable_instances(): void {
        // The student can reply in the two visible instances.
        $targets = scope::composable_instances($this->course->id, $this->student->id);
        $this->assertEqualsCanonicalizing(
            [(int) $this->maila->cmid, (int) $this->mailb->cmid],
            array_column($targets, 'cmid')
        );
    }

    /**
     * Course scope aggregates the inbox across the visible instances, excluding the
     * hidden one, and labels each item with its source activity and cmid.
     */
    public function test_get_folder_course_scope_aggregates(): void {
        $this->mailbox($this->maila)->start_conversation(
            $this->teacher->id,
            'From A',
            false,
            [$this->student->id],
            'Hello A'
        );
        $this->mailbox($this->mailb)->start_conversation(
            $this->teacher->id,
            'From B',
            false,
            [$this->student->id],
            'Hello B'
        );
        // A message in the hidden instance must not surface for the student.
        $this->mailbox($this->mailc)->start_conversation(
            $this->teacher->id,
            'From C',
            false,
            [$this->student->id],
            'Hello C'
        );

        $this->setUser($this->student);
        $result = get_folder::execute($this->maila->cmid, 'inbox', 0, 0, 'course');

        $subjects = array_column($result['items'], 'subject');
        $this->assertEqualsCanonicalizing(['From A', 'From B'], $subjects);

        // Each item routes to its own activity and is labelled with its name.
        $bysubject = [];
        foreach ($result['items'] as $item) {
            $bysubject[$item['subject']] = $item;
        }
        $this->assertEquals((int) $this->maila->cmid, $bysubject['From A']['sourcecmid']);
        $this->assertEquals('Mail A', $bysubject['From A']['activityname']);
        $this->assertEquals((int) $this->mailb->cmid, $bysubject['From B']['sourcecmid']);
        $this->assertEquals('Mail B', $bysubject['From B']['activityname']);
    }

    /**
     * Activity scope keeps to the current instance and adds no activity badge; the
     * source cmid is the page activity so write actions still route correctly.
     */
    public function test_get_folder_activity_scope_is_single_instance(): void {
        $this->mailbox($this->maila)->start_conversation(
            $this->teacher->id,
            'From A',
            false,
            [$this->student->id],
            'Hello A'
        );
        $this->mailbox($this->mailb)->start_conversation(
            $this->teacher->id,
            'From B',
            false,
            [$this->student->id],
            'Hello B'
        );

        $this->setUser($this->student);
        $result = get_folder::execute($this->maila->cmid, 'inbox', 0, 0, 'activity');

        $this->assertCount(1, $result['items']);
        $this->assertEquals('From A', $result['items'][0]['subject']);
        $this->assertEquals((int) $this->maila->cmid, $result['items'][0]['sourcecmid']);
        $this->assertSame('', $result['items'][0]['activityname']);
    }

    /**
     * Course scope also aggregates the Sent folder (message-based) across instances.
     */
    public function test_get_folder_course_scope_sent(): void {
        $this->mailbox($this->maila)->start_conversation(
            $this->teacher->id,
            'A class',
            false,
            [$this->student->id],
            'Hello A'
        );
        $this->mailbox($this->mailb)->start_conversation(
            $this->teacher->id,
            'B class',
            false,
            [$this->student->id],
            'Hello B'
        );

        $this->setUser($this->teacher);
        $result = get_folder::execute($this->maila->cmid, 'sent', 0, 0, 'course');

        $this->assertEqualsCanonicalizing(['A class', 'B class'], array_column($result['items'], 'subject'));
        foreach ($result['items'] as $item) {
            $this->assertNotEmpty($item['activityname']);
            $this->assertGreaterThan(0, $item['sourcecmid']);
        }
    }
}
