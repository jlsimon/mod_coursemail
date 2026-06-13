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
use mod_coursemail\external\get_folder;

/**
 * Tests for the quick filters (unread inbox, awaiting-reply supervision).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\mailbox::get_all_conversations
 * @covers     \mod_coursemail\local\mailbox::get_inbox_conversations
 */
class filter_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $instance;
    /** @var \stdClass */
    protected $cm;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;

    /**
     * Sets up a mailbox with an answered and an unanswered required conversation.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->instance = $gen->create_module('coursemail', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('coursemail', $this->instance->id);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');

        // Supervision (mod/coursemail:viewall) is a managers-only archetype default;
        // grant it explicitly to the teaching role so the supervision filter is testable.
        global $DB;
        $editingteacherid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(
            'mod/coursemail:viewall',
            CAP_ALLOW,
            $editingteacherid,
            \context_course::instance($this->course->id)->id
        );

        $mailbox = new mailbox($this->instance->id);
        // Required conversation to student1, who replies -> answered.
        $answered = $mailbox->start_conversation(
            $this->teacher->id,
            'Respondida',
            true,
            [$this->student1->id],
            'Hola'
        );
        $mailbox->reply($answered->get('conversationid'), $this->student1->id, 'Vale');
        // Required conversation to student2, no reply -> awaiting reply.
        $mailbox->start_conversation(
            $this->teacher->id,
            'Pendiente',
            true,
            [$this->student2->id],
            'Hola'
        );
        // Non-required conversation, no reply -> never "awaiting reply".
        $mailbox->start_conversation(
            $this->teacher->id,
            'Informativa',
            false,
            [$this->student1->id],
            'FYI'
        );
    }

    /**
     * The supervision awaiting-reply filter returns only unanswered required threads.
     *
     * @return void
     */
    public function test_unanswered_filter() {
        $mailbox = new mailbox($this->instance->id);
        $this->assertCount(3, $mailbox->get_all_conversations());
        $unanswered = $mailbox->get_all_conversations(0, 0, true);
        $this->assertCount(1, $unanswered);
        $only = reset($unanswered);
        $this->assertEquals('Pendiente', $only->get('subject'));
    }

    /**
     * The inbox unread filter returns only conversations with an unread message.
     *
     * @return void
     */
    public function test_unread_filter() {
        $mailbox = new mailbox($this->instance->id);
        // Student2 has one unread received conversation ("Pendiente").
        $this->assertCount(1, $mailbox->get_inbox_conversations($this->student2->id, 0, 0, true));

        // Reading it clears the unread filter result.
        $inbox = $mailbox->get_inbox_conversations($this->student2->id);
        $convid = reset($inbox)->get('id');
        $mailbox->mark_conversation_read($convid, $this->student2->id);
        $this->assertCount(0, $mailbox->get_inbox_conversations($this->student2->id, 0, 0, true));
    }

    /**
     * The external exposes the filter for supervision (awaiting reply).
     *
     * @return void
     */
    public function test_external_supervision_filter() {
        $this->setUser($this->teacher);
        $result = get_folder::execute($this->cm->id, 'all', 0, 0, 'activity', 'unanswered');
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Pendiente', $result['items'][0]['subject']);
    }
}
