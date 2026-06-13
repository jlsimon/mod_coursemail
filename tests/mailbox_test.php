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
use mod_coursemail\local\message;

/**
 * Unit tests for the mod_coursemail data layer (mailbox service).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\mailbox
 */
class mailbox_test extends \advanced_testcase {
    /** @var \stdClass The test course. */
    protected $course;

    /** @var \stdClass The coursemail instance. */
    protected $instance;

    /** @var \stdClass Teacher user. */
    protected $teacher;

    /** @var \stdClass First student. */
    protected $student1;

    /** @var \stdClass Second student. */
    protected $student2;

    /** @var mailbox The service under test. */
    protected $mailbox;

    /**
     * Sets up a course, an instance and three users before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->instance = $generator->create_module('coursemail', ['course' => $this->course->id]);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student1 = $generator->create_and_enrol($this->course, 'student');
        $this->student2 = $generator->create_and_enrol($this->course, 'student');
        $this->mailbox = new mailbox($this->instance->id);
    }

    /**
     * Starting a conversation creates the thread, message and per-user state rows.
     */
    public function test_start_conversation(): void {
        global $DB;

        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            true,
            [$this->student1->id, $this->student2->id],
            'Hello class'
        );

        // Conversation persisted with the expected attributes.
        $conversation = $DB->get_record('coursemail_conversations', ['id' => $message->get('conversationid')]);
        $this->assertEquals($this->instance->id, $conversation->coursemailid);
        $this->assertEquals($this->teacher->id, $conversation->creatorid);
        $this->assertEquals(1, $conversation->requiresresponse);
        $this->assertEquals('Welcome', $conversation->subject);

        // Message is sent (not a draft) with a send timestamp.
        $this->assertEquals(0, $message->get('draft'));
        $this->assertGreaterThan(0, $message->get('timesent'));
        $this->assertEquals($this->teacher->id, $message->get('userid'));

        // State rows: author read, two recipients unread.
        $rows = $DB->get_records('coursemail_message_users', ['messageid' => $message->get('id')]);
        $this->assertCount(3, $rows);

        $author = $DB->get_record(
            'coursemail_message_users',
            ['messageid' => $message->get('id'), 'userid' => $this->teacher->id]
        );
        $this->assertEquals(mailbox::ROLE_FROM, $author->role);
        $this->assertEquals(0, $author->unread);

        $recipient = $DB->get_record(
            'coursemail_message_users',
            ['messageid' => $message->get('id'), 'userid' => $this->student1->id]
        );
        $this->assertEquals(mailbox::ROLE_TO, $recipient->role);
        $this->assertEquals(1, $recipient->unread);
    }

    /**
     * The author is never stored as a recipient even if present in the list.
     */
    public function test_author_excluded_from_recipients(): void {
        global $DB;

        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id, $this->teacher->id],
            'Body'
        );

        $authorrows = $DB->get_records(
            'coursemail_message_users',
            ['messageid' => $message->get('id'), 'userid' => $this->teacher->id]
        );
        $this->assertCount(1, $authorrows);
        $this->assertEquals(mailbox::ROLE_FROM, reset($authorrows)->role);
    }

    /**
     * Folder listings reflect inbox / sent membership.
     */
    public function test_inbox_and_sent_folders(): void {
        $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id],
            'Body'
        );

        $this->assertCount(1, $this->mailbox->get_inbox_conversations($this->student1->id));
        $this->assertCount(0, $this->mailbox->get_inbox_conversations($this->student2->id));
        $this->assertCount(1, $this->mailbox->get_sent_conversations($this->teacher->id));
        $this->assertCount(0, $this->mailbox->get_sent_conversations($this->student1->id));
    }

    /**
     * Marking a message read clears the unread flag and records a receipt.
     */
    public function test_mark_read(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id],
            'Body'
        );

        $this->assertEquals(1, $this->mailbox->count_unread($this->student1->id));

        $this->assertTrue($this->mailbox->mark_read($message->get('id'), $this->student1->id));
        $this->assertEquals(0, $this->mailbox->count_unread($this->student1->id));

        // Reading again is a no-op.
        $this->assertFalse($this->mailbox->mark_read($message->get('id'), $this->student1->id));
    }

    /**
     * Replying adds a sent message addressed to every other participant.
     */
    public function test_reply(): void {
        $first = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id, $this->student2->id],
            'Body'
        );
        $conversationid = $first->get('conversationid');

        $reply = $this->mailbox->reply($conversationid, $this->student1->id, 'My answer');

        $this->assertEquals(0, $reply->get('draft'));
        $this->assertEquals($this->student1->id, $reply->get('userid'));

        // The reply reaches the teacher and the other student, but not its author.
        $this->assertEquals(1, $this->mailbox->count_unread($this->teacher->id));

        $participants = $this->mailbox->get_participant_ids($conversationid);
        sort($participants);
        $expected = [$this->teacher->id, $this->student1->id, $this->student2->id];
        sort($expected);
        $this->assertEquals($expected, $participants);
    }

    /**
     * Drafts are listed only for their author and are not delivered.
     */
    public function test_save_and_send_draft(): void {
        $draft = $this->mailbox->save_draft($this->student1->id, 'Question', 'Draft body');

        $this->assertEquals(1, $draft->get('draft'));
        $this->assertCount(1, $this->mailbox->get_draft_messages($this->student1->id));
        // A draft has no recipients yet.
        $this->assertCount(0, $this->mailbox->get_inbox_conversations($this->teacher->id));
        $this->assertCount(0, $this->mailbox->get_sent_conversations($this->student1->id));

        // Sending the draft delivers it.
        $sent = $this->mailbox->send_draft($draft->get('id'), [$this->teacher->id]);
        $this->assertEquals(0, $sent->get('draft'));
        $this->assertGreaterThan(0, $sent->get('timesent'));
        $this->assertCount(0, $this->mailbox->get_draft_messages($this->student1->id));
        $this->assertCount(1, $this->mailbox->get_inbox_conversations($this->teacher->id));
        $this->assertCount(1, $this->mailbox->get_sent_conversations($this->student1->id));
    }

    /**
     * Sending a non-draft message must be rejected.
     */
    public function test_send_draft_rejects_sent_message(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id],
            'Body'
        );

        $this->expectException(\coding_exception::class);
        $this->mailbox->send_draft($message->get('id'), [$this->student2->id]);
    }

    /**
     * Starring toggles the per-user flag and the Starred folder.
     */
    public function test_set_starred(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id],
            'Body'
        );

        $this->assertCount(0, $this->mailbox->get_starred_messages($this->student1->id));

        $this->assertTrue($this->mailbox->set_starred($message->get('id'), $this->student1->id, true));
        $this->assertCount(1, $this->mailbox->get_starred_messages($this->student1->id));

        $this->assertTrue($this->mailbox->set_starred($message->get('id'), $this->student1->id, false));
        $this->assertCount(0, $this->mailbox->get_starred_messages($this->student1->id));
    }

    /**
     * Conversation::get_messages returns sent messages and can include drafts.
     */
    public function test_conversation_get_messages(): void {
        $first = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id],
            'Body'
        );
        $conversation = new local\conversation($first->get('conversationid'));

        $this->mailbox->reply($conversation->get('id'), $this->student1->id, 'Answer');

        $this->assertCount(2, $conversation->get_messages());
    }

    /**
     * Sending a draft with no recipients is rejected at the data layer.
     */
    public function test_send_draft_without_recipients_throws(): void {
        $draft = $this->mailbox->save_draft($this->teacher->id, 'Subject', 'Body');

        $this->expectException(\coding_exception::class);
        $this->mailbox->send_draft($draft->get('id'), []);
    }

    /**
     * Replying to a non-existent conversation is rejected at the data layer.
     */
    public function test_reply_to_missing_conversation_throws(): void {
        $this->expectException(\coding_exception::class);
        $this->mailbox->reply(999999, $this->student1->id, 'Answer');
    }

    /**
     * Resetting the course removes all messages but keeps the activity instance.
     */
    public function test_reset_userdata_clears_messages(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/coursemail/lib.php');

        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id],
            'Body'
        );
        $this->mailbox->reply($message->get('conversationid'), $this->student1->id, 'Answer');
        $this->mailbox->save_draft($this->student1->id, 'A draft', 'Pending');

        $this->assertGreaterThan(0, $DB->count_records('coursemail_conversations', ['coursemailid' => $this->instance->id]));

        $data = (object) ['courseid' => $this->course->id, 'reset_coursemail_all' => 1];
        $status = coursemail_reset_userdata($data);

        $this->assertNotEmpty($status);
        $this->assertFalse($status[0]['error']);
        $this->assertEquals(0, $DB->count_records('coursemail_conversations', ['coursemailid' => $this->instance->id]));
        $this->assertEquals(0, $DB->count_records('coursemail_messages'));
        $this->assertEquals(0, $DB->count_records('coursemail_message_users'));
        // The activity instance itself survives the reset.
        $this->assertTrue($DB->record_exists('coursemail', ['id' => $this->instance->id]));
    }

    /**
     * Without the reset flag set, no data is removed.
     */
    public function test_reset_userdata_noop_without_flag(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/coursemail/lib.php');

        $this->mailbox->start_conversation(
            $this->teacher->id,
            'Subject',
            false,
            [$this->student1->id],
            'Body'
        );

        $status = coursemail_reset_userdata((object) ['courseid' => $this->course->id]);

        $this->assertSame([], $status);
        $this->assertEquals(1, $DB->count_records('coursemail_conversations', ['coursemailid' => $this->instance->id]));
    }
}
