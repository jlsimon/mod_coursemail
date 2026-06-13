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
use mod_coursemail\local\coursepage;

/**
 * Tests for the course-page badge counts.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\coursepage
 * @covers     \mod_coursemail\local\mailbox::count_unread_from_students
 * @covers     \mod_coursemail\local\completion_calculator::count_pending_responses
 */
class coursepage_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $instance;
    /** @var \context_module */
    protected $context;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student;
    /** @var mailbox */
    protected $mailbox;

    /**
     * Sets up a course with one coursemail instance, a teacher and a student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->instance = $generator->create_module('coursemail', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('coursemail', $this->instance->id, $this->course->id, false, MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->mailbox = new mailbox($this->instance->id);
    }

    /**
     * Convenience wrapper around the facade for the current instance/context.
     *
     * @param int $userid User id.
     * @return array
     */
    protected function badges($userid) {
        return coursepage::badges($this->instance->id, $this->context, $userid);
    }

    /**
     * With no messages, the student sees zero on both counters.
     */
    public function test_student_no_messages(): void {
        $badges = $this->badges($this->student->id);
        $this->assertSame('student', $badges['role']);
        $this->assertSame(0, $badges['unread']);
        $this->assertSame(0, $badges['pendingresponse']);
    }

    /**
     * An unread staff message raises the student's unread counter; reading clears it.
     */
    public function test_student_unread_counter(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Hi',
            false,
            [$this->student->id],
            'Hello'
        );

        $badges = $this->badges($this->student->id);
        $this->assertSame(1, $badges['unread']);
        $this->assertSame(0, $badges['pendingresponse']);

        $this->mailbox->mark_conversation_read($message->get('conversationid'), $this->student->id);
        $this->assertSame(0, $this->badges($this->student->id)['unread']);
    }

    /**
     * A conversation requiring a response raises the pending-response counter even
     * after reading, and clears once the student replies.
     */
    public function test_student_pending_response_counter(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Please answer',
            true,
            [$this->student->id],
            'Question'
        );
        $cid = $message->get('conversationid');

        // Unread + requires response: both counters at 1.
        $badges = $this->badges($this->student->id);
        $this->assertSame(1, $badges['unread']);
        $this->assertSame(1, $badges['pendingresponse']);

        // Reading clears unread but the response is still pending.
        $this->mailbox->mark_conversation_read($cid, $this->student->id);
        $badges = $this->badges($this->student->id);
        $this->assertSame(0, $badges['unread']);
        $this->assertSame(1, $badges['pendingresponse']);

        // Replying clears the pending response.
        $this->mailbox->reply($cid, $this->student->id, 'My answer');
        $this->assertSame(0, $this->badges($this->student->id)['pendingresponse']);
    }

    /**
     * The teacher counter reflects unread messages authored by students only.
     */
    public function test_teacher_counts_student_messages_only(): void {
        // A message the teacher sends does not count as "new from students".
        $this->mailbox->start_conversation($this->teacher->id, 'Notice', false, [$this->student->id], 'Hi');
        $badges = $this->badges($this->teacher->id);
        $this->assertSame('staff', $badges['role']);
        $this->assertSame(0, $badges['newfromstudents']);

        // The student starts a thread to the teacher: now the teacher has one new.
        $this->mailbox->start_conversation($this->student->id, 'Doubt', false, [$this->teacher->id], 'Question');
        $this->assertSame(1, $this->badges($this->teacher->id)['newfromstudents']);
    }

    /**
     * A co-teacher's message does not feed the "new from students" counter.
     */
    public function test_teacher_counter_excludes_other_staff(): void {
        $coteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        // Co-teacher writes to the teacher: staff author, so it must not count.
        $this->mailbox->start_conversation($coteacher->id, 'Heads up', false, [$this->teacher->id], 'FYI');
        $this->assertSame(0, $this->badges($this->teacher->id)['newfromstudents']);

        // A student writing to the teacher does count.
        $this->mailbox->start_conversation($this->student->id, 'Doubt', false, [$this->teacher->id], 'Question');
        $this->assertSame(1, $this->badges($this->teacher->id)['newfromstudents']);
    }

    /**
     * Returns the after-link HTML the course page would render for a user.
     *
     * @param \stdClass $user User to view as.
     * @return string
     */
    protected function after_link_for($user) {
        $this->setUser($user);
        $modinfo = get_fast_modinfo($this->course, $user->id);
        return (string) $modinfo->get_cm($this->instance->cmid)->afterlink;
    }

    /**
     * The student sees both pills (reply first, then unread) when both apply.
     */
    public function test_cm_info_view_student_pills(): void {
        // No messages: no after-link badges at all.
        $this->assertSame('', $this->after_link_for($this->student));

        // Staff message requiring a response: unread + pending response.
        $this->mailbox->start_conversation(
            $this->teacher->id,
            'Please answer',
            true,
            [$this->student->id],
            'Question'
        );

        $html = $this->after_link_for($this->student);
        $this->assertStringContainsString('coursemail-badge-action', $html);
        $this->assertStringContainsString('coursemail-badge-unread', $html);
        // The actionable pill leads (appears before the informational one).
        $this->assertLessThan(
            strpos($html, 'coursemail-badge-unread'),
            strpos($html, 'coursemail-badge-action')
        );
    }

    /**
     * The staff member sees a single "new from students" pill, and nothing when idle.
     */
    public function test_cm_info_view_staff_pill(): void {
        // The teacher's own message to a student creates no badge for the teacher.
        $this->mailbox->start_conversation($this->teacher->id, 'Notice', false, [$this->student->id], 'Hi');
        $this->assertSame('', $this->after_link_for($this->teacher));

        // A student message to the teacher shows the single action pill.
        $this->mailbox->start_conversation($this->student->id, 'Doubt', false, [$this->teacher->id], 'Question');
        $html = $this->after_link_for($this->teacher);
        $this->assertStringContainsString('coursemail-badge-action', $html);
        $this->assertStringNotContainsString('coursemail-badge-unread', $html);
    }
}
