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

namespace mod_coursemail\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_coursemail\local\mailbox;

/**
 * Tests for the mod_coursemail privacy provider.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\privacy\provider
 */
class provider_test extends \advanced_testcase {
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

    /** @var int */
    protected $conversationid;

    /**
     * Sets up a populated instance.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->instance = $generator->create_module('coursemail', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('coursemail', $this->instance->cmid, 0, false, MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->student = $generator->create_and_enrol($course, 'student');
        $this->mailbox = new mailbox($this->instance->id);

        // Teacher writes a manual-completion thread to the student; the student replies
        // and the teacher marks the student as completed.
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Hello',
            true,
            [$this->student->id],
            'Welcome',
            FORMAT_HTML,
            true
        );
        $this->conversationid = $message->get('conversationid');
        $this->mailbox->reply($this->conversationid, $this->student->id, 'Thanks');
        $this->mailbox->set_manual_completed($this->conversationid, $this->student->id, true, $this->teacher->id);
    }

    /**
     * Both participants have the module context in their context list.
     */
    public function test_get_contexts_for_userid(): void {
        $teachercontexts = provider::get_contexts_for_userid($this->teacher->id)->get_contextids();
        $studentcontexts = provider::get_contexts_for_userid($this->student->id)->get_contextids();

        // Context ids come back as strings; compare loosely to the integer context id.
        $this->assertContainsEquals($this->context->id, $teachercontexts);
        $this->assertContainsEquals($this->context->id, $studentcontexts);
    }

    /**
     * The context reports both participants.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->context, 'mod_coursemail');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int) $this->teacher->id, $userids);
        $this->assertContains((int) $this->student->id, $userids);
    }

    /**
     * Exporting writes data for the user in the module context.
     */
    public function test_export_user_data(): void {
        writer::reset();

        $contextlist = new approved_contextlist($this->student, 'mod_coursemail', [$this->context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Exporting includes the user's own drafts, which have no per-message-user row.
     */
    public function test_export_includes_drafts(): void {
        writer::reset();

        // The teacher saves a draft (no recipients yet, so no message_users row exists).
        $this->mailbox->save_draft($this->teacher->id, 'My draft subject', 'Draft body');

        $contextlist = new approved_contextlist($this->teacher, 'mod_coursemail', [$this->context->id]);
        provider::export_user_data($contextlist);

        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'coursemail')]);
        $subjects = array_map(function ($conversation) {
            return $conversation->subject;
        }, $data->conversations);
        $this->assertContains('My draft subject', $subjects);
    }

    /**
     * Deleting all users in the context wipes the instance data.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(0, $this->count_conversations());
        $this->assertEquals(0, $this->count_messages());
        $this->assertEquals(0, $this->count_message_users());
        $this->assertEquals(0, $this->count_manualcomplete());
    }

    /**
     * Deleting one user removes their authored messages and personal state only.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $contextlist = new approved_contextlist($this->student, 'mod_coursemail', [$this->context->id]);
        provider::delete_data_for_user($contextlist);

        // The student's authored (reply) message is gone.
        $this->assertFalse($DB->record_exists('coursemail_messages', ['userid' => $this->student->id]));
        // The student has no per-user rows left.
        $this->assertFalse($DB->record_exists('coursemail_message_users', ['userid' => $this->student->id]));
        // The student's own manual-completion row is gone.
        $this->assertFalse($DB->record_exists('coursemail_manualcomplete', ['userid' => $this->student->id]));
        // The teacher's original message survives.
        $this->assertTrue($DB->record_exists('coursemail_messages', ['userid' => $this->teacher->id]));
    }

    /**
     * Erasing the teacher keeps the student's completion but anonymises the recorder.
     */
    public function test_delete_teacher_anonymises_completedby(): void {
        global $DB;

        $contextlist = new approved_contextlist($this->teacher, 'mod_coursemail', [$this->context->id]);
        provider::delete_data_for_user($contextlist);

        // The student stays marked as completed, but completedby is cleared to 0.
        $row = $DB->get_record('coursemail_manualcomplete', ['userid' => $this->student->id]);
        $this->assertNotFalse($row);
        $this->assertEquals(0, $row->completedby);
    }

    /**
     * Deleting an approved userlist removes only those users' data.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $approved = new approved_userlist($this->context, 'mod_coursemail', [$this->student->id]);
        provider::delete_data_for_users($approved);

        $this->assertFalse($DB->record_exists('coursemail_message_users', ['userid' => $this->student->id]));
        $this->assertTrue($DB->record_exists('coursemail_messages', ['userid' => $this->teacher->id]));
    }

    /**
     * Counts the conversations in the test instance.
     *
     * @return int
     */
    protected function count_conversations() {
        global $DB;
        return $DB->count_records('coursemail_conversations', ['coursemailid' => $this->instance->id]);
    }

    /**
     * Counts the messages in the test instance.
     *
     * @return int
     */
    protected function count_messages() {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(m.id) FROM {coursemail_messages} m
               JOIN {coursemail_conversations} c ON c.id = m.conversationid
              WHERE c.coursemailid = ?",
            [$this->instance->id]
        );
    }

    /**
     * Counts the per-user message state rows in the test instance.
     *
     * @return int
     */
    protected function count_message_users() {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(mu.id) FROM {coursemail_message_users} mu
               JOIN {coursemail_messages} m ON m.id = mu.messageid
               JOIN {coursemail_conversations} c ON c.id = m.conversationid
              WHERE c.coursemailid = ?",
            [$this->instance->id]
        );
    }

    /**
     * Counts the manual-completion rows in the test instance.
     *
     * @return int
     */
    protected function count_manualcomplete() {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(mc.id) FROM {coursemail_manualcomplete} mc
               JOIN {coursemail_conversations} c ON c.id = mc.conversationid
              WHERE c.coursemailid = ?",
            [$this->instance->id]
        );
    }
}
