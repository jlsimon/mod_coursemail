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
use mod_coursemail\local\completion_updater;

/**
 * Tests for the mod_coursemail custom completion rules.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\completion_calculator
 * @covers     \mod_coursemail\completion\custom_completion
 * @covers     \mod_coursemail\local\completion_updater
 */
class completion_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student;

    /**
     * Sets up a completion-enabled course with a teacher and a student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['enablecompletion' => 1]);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * Creates a coursemail instance with the given completion rules enabled.
     *
     * @param array $rules e.g. ['completionmail' => 1, 'completionrequireread' => 1]
     * @return array [\stdClass $instance, \stdClass $cm, mailbox $mailbox]
     */
    protected function create_instance(array $rules) {
        $data = array_merge([
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
        ], $rules);
        $instance = $this->getDataGenerator()->create_module('coursemail', $data);
        $cm = get_coursemodule_from_instance('coursemail', $instance->id, $this->course->id, false, MUST_EXIST);
        return [$instance, $cm, new mailbox($instance->id)];
    }

    /**
     * Returns the stored completion state for the student.
     *
     * @param \stdClass $cm Course module record.
     * @return int
     */
    protected function student_state($cm) {
        return $this->user_state($cm, $this->student->id);
    }

    /**
     * Returns the stored completion state for an arbitrary user.
     *
     * @param \stdClass $cm Course module record.
     * @param int $userid User id.
     * @return int
     */
    protected function user_state($cm, $userid) {
        $completion = new \completion_info($this->course);
        completion_updater::update_for_users($cm, [$userid]);
        $data = $completion->get_data($cm, false, $userid);
        return (int) $data->completionstate;
    }

    /**
     * "Read all" completes once the student reads the staff message and reopens on new mail.
     */
    public function test_completion_read(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 1]);

        // No messages yet: not complete.
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // Staff message received but unread: not complete.
        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Hi',
            false,
            [$this->student->id],
            'Hello'
        );
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // After reading: complete.
        $mailbox->mark_conversation_read($message->get('conversationid'), $this->student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));

        // A new staff message reopens completion.
        $mailbox->start_conversation($this->teacher->id, 'Again', false, [$this->student->id], 'More');
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));
    }

    /**
     * A reply by the student (not staff) does not satisfy the read rule by itself.
     */
    public function test_completion_read_ignores_student_messages(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 1]);

        // Student starts a thread to staff: no staff message received, so not complete.
        $mailbox->start_conversation($this->student->id, 'Doubt', false, [$this->teacher->id], 'Question');
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));
    }

    /**
     * "Reply all" requires reading and replying in required conversations.
     */
    public function test_completion_reply(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 1]);

        // Staff message requiring a response, unread: not complete.
        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Please answer',
            true,
            [$this->student->id],
            'Question'
        );
        $conversationid = $message->get('conversationid');
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // Read but not replied: still not complete.
        $mailbox->mark_conversation_read($conversationid, $this->student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // Replied: complete.
        $mailbox->reply($conversationid, $this->student->id, 'My answer');
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));
    }

    /**
     * The read rule considers every staff member: messages from all senders must be read.
     */
    public function test_completion_read_requires_all_staff_messages(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 1]);
        $teacher2 = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $first = $mailbox->start_conversation($this->teacher->id, 'A', false, [$this->student->id], 'From t1');
        $second = $mailbox->start_conversation($teacher2->id, 'B', false, [$this->student->id], 'From t2');

        // Reading only one staff member's message is not enough.
        $mailbox->mark_conversation_read($first->get('conversationid'), $this->student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // Once both staff messages are read, the rule is satisfied.
        $mailbox->mark_conversation_read($second->get('conversationid'), $this->student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));
    }

    /**
     * When no conversation requires a response, reading is enough for the reply rule.
     */
    public function test_completion_reply_without_required_conversations(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 1]);

        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'FYI',
            false,
            [$this->student->id],
            'No answer needed'
        );
        $mailbox->mark_conversation_read($message->get('conversationid'), $this->student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));
    }

    /**
     * Regression: with manual completion tracking, update_for_users() must no-op.
     *
     * Our custom rules are automatic, so sending a message recomputes them. But if
     * the teacher set the activity to manual completion, calling update_state() with
     * COMPLETION_UNKNOWN is rejected by Moodle as an invalid manual state and throws
     * err_system. The updater must skip non-automatic tracking instead.
     */
    public function test_update_for_users_noop_on_manual_completion(): void {
        [, $cm, $mailbox] = $this->create_instance(['completion' => COMPLETION_TRACKING_MANUAL]);

        $mailbox->start_conversation(
            $this->teacher->id,
            'Hi class',
            false,
            [$this->student->id],
            'Hello'
        );

        // Must not throw; the manual state stays untouched (not manually marked yet).
        completion_updater::update_for_users($cm, [$this->student->id]);

        $completion = new \completion_info($this->course);
        $data = $completion->get_data($cm, false, $this->student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, (int) $data->completionstate);
    }

    /**
     * "Teacher marks as completed": a flagged conversation stays incomplete until
     * staff mark the student done, and reopens when they undo it.
     */
    public function test_completion_manual(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 0]);

        // A conversation flagged for manual completion, not yet marked: incomplete.
        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Tutorial',
            false,
            [$this->student->id],
            'Work to assess',
            FORMAT_HTML,
            true
        );
        $cid = $message->get('conversationid');
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // Reading does not satisfy the manual rule on its own.
        $mailbox->mark_conversation_read($cid, $this->student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // The teacher marks the student as completed: complete.
        $mailbox->set_manual_completed($cid, $this->student->id, true, $this->teacher->id);
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));

        // Reopening (undo) reverts completion.
        $mailbox->set_manual_completed($cid, $this->student->id, false, $this->teacher->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));
    }

    /**
     * A conversation that is NOT flagged for manual completion never gates the rule;
     * with no flagged conversation the rule is met vacuously.
     */
    public function test_completion_manual_only_gates_flagged_conversations(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 0]);

        // Unflagged conversation: the manual rule is vacuously satisfied.
        $mailbox->start_conversation(
            $this->teacher->id,
            'FYI',
            false,
            [$this->student->id],
            'No manual gate'
        );
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));
    }

    /**
     * Manual completion is per student: marking one recipient done does not complete
     * the others sharing the same conversation.
     */
    public function test_completion_manual_is_per_student(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 0]);
        $student2 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Group task',
            false,
            [$this->student->id, $student2->id],
            'Assess each of you',
            FORMAT_HTML,
            true
        );
        $cid = $message->get('conversationid');

        // Both start incomplete.
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->user_state($cm, $student2->id));

        // Marking only the first student leaves the second one incomplete.
        $mailbox->set_manual_completed($cid, $this->student->id, true, $this->teacher->id);
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->user_state($cm, $student2->id));
    }

    /**
     * Marking a conversation unread removes the read receipt and reopens "read".
     */
    public function test_mark_unread_reopens_read_completion(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 1]);

        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Hello',
            false,
            [$this->student->id],
            'Please read this'
        );
        $cid = $message->get('conversationid');

        // Reading the only staff message completes the rule.
        $mailbox->mark_conversation_read($cid, $this->student->id);
        $this->assertEquals(0, $mailbox->count_unread_in_conversation($cid, $this->student->id));
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));

        // Marking it unread again clears the receipt and reopens completion.
        $marked = $mailbox->mark_conversation_unread($cid, $this->student->id);
        $this->assertNotEmpty($marked);
        $this->assertEquals(1, $mailbox->count_unread_in_conversation($cid, $this->student->id));
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));
    }

    /**
     * With the read baseline OFF, replying to a required conversation completes the
     * activity even if the student never opened the staff message.
     */
    public function test_completionrequireread_off_skips_read_baseline(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 0]);

        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Please answer',
            true,
            [$this->student->id],
            'Question'
        );

        // Not read and not replied: the reply obligation is still outstanding.
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // Replying (without ever reading) satisfies the rule: no read baseline.
        $mailbox->reply($message->get('conversationid'), $this->student->id, 'My answer');
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));
    }

    /**
     * With the read baseline ON, replying is not enough: the staff message must also
     * have been read.
     */
    public function test_completionrequireread_on_still_needs_read(): void {
        [, $cm, $mailbox] = $this->create_instance(['completionmail' => 1, 'completionrequireread' => 1]);

        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Please answer',
            true,
            [$this->student->id],
            'Question'
        );
        $cid = $message->get('conversationid');

        // Replying without reading leaves the read baseline unmet.
        $mailbox->reply($cid, $this->student->id, 'My answer');
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->student_state($cm));

        // Reading the staff message then completes the activity.
        $mailbox->mark_conversation_read($cid, $this->student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $this->student_state($cm));
    }
}
