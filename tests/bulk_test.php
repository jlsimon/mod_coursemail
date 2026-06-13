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
use mod_coursemail\external\bulk_mark;

/**
 * Tests for bulk mark read/unread.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\external\bulk_mark
 */
class bulk_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $maila;
    /** @var \stdClass */
    protected $mailb;
    /** @var \stdClass */
    protected $cma;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;

    /**
     * Sets up two mailboxes, a teacher and two students.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->maila = $gen->create_module('coursemail', ['course' => $this->course->id]);
        $this->mailb = $gen->create_module('coursemail', ['course' => $this->course->id]);
        $this->cma = get_coursemodule_from_instance('coursemail', $this->maila->id);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
    }

    /**
     * Bulk marking toggles the current user's read state.
     *
     * @return void
     */
    public function test_bulk_mark_read_and_unread() {
        $mailbox = new mailbox($this->maila->id);
        $msg = $mailbox->start_conversation(
            $this->teacher->id,
            'Aviso',
            false,
            [$this->student1->id, $this->student2->id],
            'Hola'
        );
        $convid = $msg->get('conversationid');

        $this->assertEquals(1, $mailbox->count_unread_in_conversation($convid, $this->student1->id));

        $this->setUser($this->student1);
        $read = bulk_mark::execute($this->cma->id, [$convid], true);
        $this->assertEquals(1, $read['count']);
        $this->assertEquals(0, $mailbox->count_unread_in_conversation($convid, $this->student1->id));

        $unread = bulk_mark::execute($this->cma->id, [$convid], false);
        $this->assertEquals(1, $unread['count']);
        $this->assertEquals(1, $mailbox->count_unread_in_conversation($convid, $this->student1->id));

        // The other student's state is untouched.
        $this->assertEquals(1, $mailbox->count_unread_in_conversation($convid, $this->student2->id));
    }

    /**
     * Non-participant and cross-instance conversation ids are skipped.
     *
     * @return void
     */
    public function test_bulk_skips_non_participant_and_cross_instance() {
        $mba = new mailbox($this->maila->id);
        $mbb = new mailbox($this->mailb->id);
        $conva = $mba->start_conversation(
            $this->teacher->id,
            'Solo a 1',
            false,
            [$this->student1->id],
            'Hola'
        )->get('conversationid');
        $convb = $mbb->start_conversation(
            $this->teacher->id,
            'En B',
            false,
            [$this->student1->id],
            'Hola'
        )->get('conversationid');

        // Student2 does not take part in conva.
        $this->setUser($this->student2);
        $this->assertEquals(0, bulk_mark::execute($this->cma->id, [$conva], true)['count']);

        // Conversation B belongs to another instance, so it is ignored for cma.
        $this->setUser($this->student1);
        $this->assertEquals(0, bulk_mark::execute($this->cma->id, [$convb], true)['count']);
    }
}
