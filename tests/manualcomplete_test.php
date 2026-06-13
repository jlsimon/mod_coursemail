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
use mod_coursemail\external\start_conversation;
use mod_coursemail\external\get_conversation;
use mod_coursemail\external\set_recipient_completed;

/**
 * Tests for the teacher-marked manual completion externals.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\external\set_recipient_completed
 * @covers     \mod_coursemail\external\get_conversation
 * @covers     \mod_coursemail\external\start_conversation
 */
class manualcomplete_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $instance;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;
    /** @var mailbox */
    protected $mailbox;

    /**
     * Sets up a course with a teacher and two students.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['enablecompletion' => 1]);
        $this->instance = $generator->create_module('coursemail', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmail' => 1,
            'completionrequireread' => 0,
        ]);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student1 = $generator->create_and_enrol($this->course, 'student');
        $this->student2 = $generator->create_and_enrol($this->course, 'student');
        $this->mailbox = new mailbox($this->instance->id);
    }

    /**
     * Indexes recipients of a get_conversation result by user id.
     *
     * @param array $conversation get_conversation::execute result.
     * @return array<int, array> Recipient rows keyed by user id.
     */
    protected function recipients_by_id($conversation) {
        $byid = [];
        foreach ($conversation['recipients'] as $recipient) {
            $byid[$recipient['userid']] = $recipient;
        }
        return $byid;
    }

    /**
     * The compose flag reaches the conversation; student-initiated threads force it off.
     */
    public function test_start_conversation_stores_manual_flag(): void {
        $this->setUser($this->teacher);
        $result = start_conversation::execute(
            $this->instance->cmid,
            'Tutorial',
            'Body',
            FORMAT_MOODLE,
            false,
            'users',
            [$this->student1->id],
            0,
            true
        );
        $conversation = new \mod_coursemail\local\conversation($result['conversationid']);
        $this->assertEquals(1, $conversation->get('requiresmanualcomplete'));

        // A student writing to staff cannot set the staff-only manual flag.
        $this->setUser($this->student1);
        $studentresult = start_conversation::execute(
            $this->instance->cmid,
            'Doubt',
            'Question',
            FORMAT_MOODLE,
            false,
            'staff',
            [],
            0,
            true
        );
        $studentconv = new \mod_coursemail\local\conversation($studentresult['conversationid']);
        $this->assertEquals(0, $studentconv->get('requiresmanualcomplete'));
    }

    /**
     * get_conversation exposes the manual flag, the staff permission and the per-chip
     * completed state, which flips after a teacher marks a student.
     */
    public function test_get_conversation_exposes_manual_state(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Group task',
            false,
            [$this->student1->id, $this->student2->id],
            'Assess each of you',
            FORMAT_HTML,
            true
        );
        $cid = $message->get('conversationid');

        $this->setUser($this->teacher);
        $conversation = get_conversation::execute($this->instance->cmid, $cid);
        $this->assertTrue($conversation['requiresmanualcomplete']);
        $this->assertTrue($conversation['canmanualcomplete']);
        $byid = $this->recipients_by_id($conversation);
        $this->assertFalse($byid[$this->student1->id]['completed']);
        $this->assertFalse($byid[$this->student2->id]['completed']);

        // Mark the first student as completed.
        $set = set_recipient_completed::execute($this->instance->cmid, $cid, $this->student1->id, true);
        $this->assertTrue($set['completed']);
        $this->assertNotEmpty($set['completedinfo']);

        $conversation = get_conversation::execute($this->instance->cmid, $cid);
        $byid = $this->recipients_by_id($conversation);
        $this->assertTrue($byid[$this->student1->id]['completed']);
        $this->assertNotEmpty($byid[$this->student1->id]['completedinfo']);
        $this->assertFalse($byid[$this->student2->id]['completed']);

        // Reopening clears it.
        $reset = set_recipient_completed::execute($this->instance->cmid, $cid, $this->student1->id, false);
        $this->assertFalse($reset['completed']);
        $conversation = get_conversation::execute($this->instance->cmid, $cid);
        $byid = $this->recipients_by_id($conversation);
        $this->assertFalse($byid[$this->student1->id]['completed']);
    }

    /**
     * Marking fires the conversation_completed event with the student as related user.
     */
    public function test_set_recipient_completed_fires_event(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Tutorial',
            false,
            [$this->student1->id],
            'Work',
            FORMAT_HTML,
            true
        );
        $cid = $message->get('conversationid');

        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();
        set_recipient_completed::execute($this->instance->cmid, $cid, $this->student1->id, true);
        $events = array_filter($sink->get_events(), function ($e) {
            return $e instanceof \mod_coursemail\event\conversation_completed;
        });
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($this->student1->id, $event->relateduserid);
    }

    /**
     * A student (no send capability) cannot mark completion.
     */
    public function test_set_recipient_completed_denied_for_student(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Tutorial',
            false,
            [$this->student1->id],
            'Work',
            FORMAT_HTML,
            true
        );
        $cid = $message->get('conversationid');

        $this->setUser($this->student1);
        $this->expectException(\required_capability_exception::class);
        set_recipient_completed::execute($this->instance->cmid, $cid, $this->student1->id, true);
    }

    /**
     * Marking a user who is not a recipient of the thread is rejected.
     */
    public function test_set_recipient_completed_rejects_non_recipient(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Tutorial',
            false,
            [$this->student1->id],
            'Work',
            FORMAT_HTML,
            true
        );
        $cid = $message->get('conversationid');

        $this->setUser($this->teacher);
        $this->expectException(\invalid_parameter_exception::class);
        set_recipient_completed::execute($this->instance->cmid, $cid, $this->student2->id, true);
    }

    /**
     * A conversation not flagged for manual completion cannot be marked.
     */
    public function test_set_recipient_completed_rejects_unflagged(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Plain',
            false,
            [$this->student1->id],
            'No manual gate'
        );
        $cid = $message->get('conversationid');

        $this->setUser($this->teacher);
        $this->expectException(\invalid_parameter_exception::class);
        set_recipient_completed::execute($this->instance->cmid, $cid, $this->student1->id, true);
    }

    /**
     * A conversation from another instance is rejected.
     */
    public function test_set_recipient_completed_rejects_cross_instance(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Tutorial',
            false,
            [$this->student1->id],
            'Work',
            FORMAT_HTML,
            true
        );
        $cid = $message->get('conversationid');

        $other = $this->getDataGenerator()->create_module('coursemail', ['course' => $this->course->id]);

        $this->setUser($this->teacher);
        $this->expectException(\invalid_parameter_exception::class);
        set_recipient_completed::execute($other->cmid, $cid, $this->student1->id, true);
    }
}
