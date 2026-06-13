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
use mod_coursemail\external\helper;
use mod_coursemail\external\get_folder;
use mod_coursemail\external\get_conversation;
use mod_coursemail\external\toggle_starred;
use mod_coursemail\external\mark_unread;

/**
 * Tests for the mod_coursemail external functions.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\external\get_folder
 * @covers     \mod_coursemail\external\get_conversation
 * @covers     \mod_coursemail\external\toggle_starred
 */
class external_test extends \advanced_testcase {
    /** @var \stdClass The coursemail instance (has ->cmid). */
    protected $instance;

    /** @var \stdClass Teacher user. */
    protected $teacher;

    /** @var \stdClass First student. */
    protected $student1;

    /** @var \stdClass Second student. */
    protected $student2;

    /** @var mailbox The service. */
    protected $mailbox;

    /**
     * Sets up shared fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // The full-name cache is static; clear it so reused user ids never return stale names.
        helper::reset_caches();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->instance = $generator->create_module('coursemail', ['course' => $course->id]);
        // Explicit, distinct names: the generator cycles a fixed name list, so without
        // this two users could share a name and break the recipient assertions.
        $this->teacher = $generator->create_and_enrol(
            $course,
            'editingteacher',
            ['firstname' => 'Teacher', 'lastname' => 'Uno']
        );
        $this->student1 = $generator->create_and_enrol(
            $course,
            'student',
            ['firstname' => 'Student', 'lastname' => 'Uno']
        );
        $this->student2 = $generator->create_and_enrol(
            $course,
            'student',
            ['firstname' => 'Student', 'lastname' => 'Dos']
        );
        $this->mailbox = new mailbox($this->instance->id);
    }

    /**
     * Inbox folder shows received conversations for the recipient only.
     */
    public function test_get_folder_inbox(): void {
        $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->student1);
        $result = get_folder::execute($this->instance->cmid, 'inbox');
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Welcome', $result['items'][0]['subject']);
        $this->assertTrue($result['items'][0]['unread']);

        $this->setUser($this->student2);
        $result = get_folder::execute($this->instance->cmid, 'inbox');
        $this->assertCount(0, $result['items']);
    }

    /**
     * The inbox folder paginates and reports whether more items remain.
     */
    public function test_get_folder_pagination(): void {
        // Three separate conversations received by student1.
        foreach (['One', 'Two', 'Three'] as $subject) {
            $this->mailbox->start_conversation(
                $this->teacher->id,
                $subject,
                false,
                [$this->student1->id],
                'Body ' . $subject
            );
        }

        $this->setUser($this->student1);

        $page0 = get_folder::execute($this->instance->cmid, 'inbox', 0, 2);
        $this->assertCount(2, $page0['items']);
        $this->assertTrue($page0['hasmore']);
        $this->assertEquals(0, $page0['page']);

        $page1 = get_folder::execute($this->instance->cmid, 'inbox', 1, 2);
        $this->assertCount(1, $page1['items']);
        $this->assertFalse($page1['hasmore']);

        // The two pages together cover all three conversations with no overlap.
        $ids = array_merge(
            array_column($page0['items'], 'conversationid'),
            array_column($page1['items'], 'conversationid')
        );
        $this->assertCount(3, array_unique($ids));
    }

    /**
     * The global perpage setting drives the default page size when none is given.
     */
    public function test_get_folder_respects_perpage_setting(): void {
        set_config('perpage', 2, 'mod_coursemail');

        foreach (['One', 'Two', 'Three'] as $subject) {
            $this->mailbox->start_conversation(
                $this->teacher->id,
                $subject,
                false,
                [$this->student1->id],
                'Body ' . $subject
            );
        }

        $this->setUser($this->student1);
        $result = get_folder::execute($this->instance->cmid, 'inbox');
        $this->assertCount(2, $result['items']);
        $this->assertTrue($result['hasmore']);
    }

    /**
     * Sent folder shows the author's conversations; drafts show only to author.
     */
    public function test_get_folder_sent_and_drafts(): void {
        $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );
        $this->mailbox->save_draft($this->student1->id, 'My draft', 'Pending');

        $this->setUser($this->teacher);
        $sent = get_folder::execute($this->instance->cmid, 'sent');
        $this->assertCount(1, $sent['items']);

        $this->setUser($this->student1);
        $drafts = get_folder::execute($this->instance->cmid, 'drafts');
        $this->assertCount(1, $drafts['items']);
        $this->assertTrue($drafts['items'][0]['draft']);
    }

    /**
     * Opening a conversation returns its messages, marks it read and fires events.
     */
    public function test_get_conversation_marks_read_and_fires_event(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            true,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->student1);
        $this->assertEquals(1, $this->mailbox->count_unread($this->student1->id));

        $sink = $this->redirectEvents();
        $result = get_conversation::execute($this->instance->cmid, $message->get('conversationid'));
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $result['messages']);
        $this->assertTrue($result['requiresresponse']);
        $this->assertEquals(0, $this->mailbox->count_unread($this->student1->id));

        $read = array_filter($events, function ($e) {
            return $e instanceof \mod_coursemail\event\message_read;
        });
        $this->assertCount(1, $read);
    }

    /**
     * A non-participant without viewall cannot open a conversation.
     */
    public function test_get_conversation_denied_for_nonparticipant(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->student2);
        $this->expectException(\required_capability_exception::class);
        get_conversation::execute($this->instance->cmid, $message->get('conversationid'));
    }

    /**
     * Starring a message makes it appear in the Starred folder.
     */
    public function test_toggle_starred(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->student1);
        $result = toggle_starred::execute($this->instance->cmid, $message->get('id'), true);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['starred']);

        $starred = get_folder::execute($this->instance->cmid, 'starred');
        $this->assertCount(1, $starred['items']);
    }

    /**
     * Starring a message through another instance's cmid is rejected.
     */
    public function test_toggle_starred_rejects_cross_instance(): void {
        $generator = $this->getDataGenerator();
        $other = $generator->create_module('coursemail', ['course' => $this->instance->course]);

        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->student1);
        $this->expectException(\invalid_parameter_exception::class);
        toggle_starred::execute($other->cmid, $message->get('id'), true);
    }

    /**
     * Reading a conversation and then marking it unread restores the unread state.
     */
    public function test_mark_unread(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );
        $cid = $message->get('conversationid');

        $this->setUser($this->student1);
        // Reading clears the unread flag.
        get_conversation::execute($this->instance->cmid, $cid);
        $this->assertEquals(0, $this->mailbox->count_unread_in_conversation($cid, $this->student1->id));

        // Marking unread restores it.
        $result = mark_unread::execute($this->instance->cmid, $cid);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $this->mailbox->count_unread_in_conversation($cid, $this->student1->id));
    }

    /**
     * Marking a conversation unread through another instance's cmid is rejected.
     */
    public function test_mark_unread_rejects_cross_instance(): void {
        $generator = $this->getDataGenerator();
        $other = $generator->create_module('coursemail', ['course' => $this->instance->course]);

        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->student1);
        $this->expectException(\invalid_parameter_exception::class);
        mark_unread::execute($other->cmid, $message->get('conversationid'));
    }

    /**
     * The Sent folder labels each item with its addressee (not the last author): a
     * single recipient shows their name with no extras.
     */
    public function test_get_folder_sent_shows_single_recipient(): void {
        $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->teacher);
        $sent = get_folder::execute($this->instance->cmid, 'sent');
        $this->assertCount(1, $sent['items']);
        $this->assertEquals(fullname($this->student1), $sent['items'][0]['recipientname']);
        $this->assertEquals(0, $sent['items'][0]['recipientextra']);
    }

    /**
     * With several addressees the Sent item shows one representative name plus the
     * count of the remaining recipients ("Name +N").
     */
    public function test_get_folder_sent_shows_multiple_recipients(): void {
        $this->mailbox->start_conversation(
            $this->teacher->id,
            'To the class',
            false,
            [$this->student1->id, $this->student2->id],
            'Hello'
        );

        $this->setUser($this->teacher);
        $sent = get_folder::execute($this->instance->cmid, 'sent');
        $this->assertCount(1, $sent['items']);
        // Two recipients: one is named, one is folded into the "+N" extra count.
        $this->assertNotEmpty($sent['items'][0]['recipientname']);
        $this->assertEquals(1, $sent['items'][0]['recipientextra']);
    }

    /**
     * The inbox keeps labelling items with the author, leaving the recipient fields empty.
     */
    public function test_get_folder_inbox_has_no_recipient_label(): void {
        $this->mailbox->start_conversation(
            $this->teacher->id,
            'Welcome',
            false,
            [$this->student1->id],
            'Hello'
        );

        $this->setUser($this->student1);
        $inbox = get_folder::execute($this->instance->cmid, 'inbox');
        $this->assertEquals(fullname($this->teacher), $inbox['items'][0]['fromname']);
        $this->assertSame('', $inbox['items'][0]['recipientname']);
        $this->assertEquals(0, $inbox['items'][0]['recipientextra']);
    }

    /**
     * get_conversation exposes the thread's addressees to staff (everyone but the
     * viewer), sorted by name with the final entry flagged for comma joining.
     */
    public function test_get_conversation_returns_recipients(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'To the class',
            false,
            [$this->student1->id, $this->student2->id],
            'Hello'
        );

        // As the author, the recipients are both students (not the author).
        $this->setUser($this->teacher);
        $conversation = get_conversation::execute($this->instance->cmid, $message->get('conversationid'));
        $this->assertEquals(2, $conversation['recipientcount']);
        $names = array_column($conversation['recipients'], 'name');
        $this->assertContains(fullname($this->student1), $names);
        $this->assertContains(fullname($this->student2), $names);
        $this->assertNotContains(fullname($this->teacher), $names);
        // Only the last entry is flagged as last.
        $lastflags = array_column($conversation['recipients'], 'last');
        $this->assertEquals([false, true], $lastflags);

        // A student recipient must NOT learn who the other recipients are: the
        // recipient list is withheld entirely (not just hidden client-side).
        $this->setUser($this->student1);
        $conversation = get_conversation::execute($this->instance->cmid, $message->get('conversationid'));
        $this->assertEquals(0, $conversation['recipientcount']);
        $this->assertSame([], $conversation['recipients']);
    }

    /**
     * A student who started a thread to staff likewise does not get a recipient
     * list back: only users with the send/viewall capability do. (That staff can
     * see addressees is covered by the teacher perspective above.)
     */
    public function test_get_conversation_hides_recipients_from_students(): void {
        $message = $this->mailbox->start_conversation(
            $this->student1->id,
            'Doubt',
            false,
            [$this->teacher->id],
            'Question'
        );

        $this->setUser($this->student1);
        $conversation = get_conversation::execute($this->instance->cmid, $message->get('conversationid'));
        $this->assertEquals(0, $conversation['recipientcount']);
        $this->assertSame([], $conversation['recipients']);
    }

    /**
     * Each recipient chip carries that recipient's read/reply status and the matching
     * border state, so staff can see at a glance who has read and who has answered.
     */
    public function test_get_conversation_recipients_carry_read_and_reply_status(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'To the class',
            false,
            [$this->student1->id, $this->student2->id],
            'Hello'
        );
        $cid = $message->get('conversationid');

        // Student 1 reads the thread and then replies; student 2 does nothing.
        $this->mailbox->mark_conversation_read($cid, $this->student1->id);
        $this->mailbox->reply($cid, $this->student1->id, 'My answer');

        $this->setUser($this->teacher);
        $conversation = get_conversation::execute($this->instance->cmid, $cid);
        $byname = [];
        foreach ($conversation['recipients'] as $recipient) {
            $byname[$recipient['name']] = $recipient;
        }

        // Student 1: read and replied -> green border, both flags true.
        $s1 = $byname[fullname($this->student1)];
        $this->assertTrue($s1['read']);
        $this->assertTrue($s1['replied']);
        $this->assertSame('replied', $s1['borderstate']);

        // Student 2: untouched -> grey border, both flags false.
        $s2 = $byname[fullname($this->student2)];
        $this->assertFalse($s2['read']);
        $this->assertFalse($s2['replied']);
        $this->assertSame('unread', $s2['borderstate']);
    }

    /**
     * A recipient who has read but not answered gets the intermediate "read" border.
     */
    public function test_get_conversation_recipient_read_but_unanswered(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'To the class',
            false,
            [$this->student1->id],
            'Hello'
        );
        $cid = $message->get('conversationid');

        $this->mailbox->mark_conversation_read($cid, $this->student1->id);

        $this->setUser($this->teacher);
        $conversation = get_conversation::execute($this->instance->cmid, $cid);
        $s1 = $conversation['recipients'][0];
        $this->assertTrue($s1['read']);
        $this->assertFalse($s1['replied']);
        $this->assertSame('read', $s1['borderstate']);
    }
}
