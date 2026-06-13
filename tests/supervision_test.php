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
use mod_coursemail\external\get_folder;
use mod_coursemail\external\get_conversation;

/**
 * Tests for the supervision view (viewall sees every conversation).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\scope
 * @covers     \mod_coursemail\external\get_folder
 */
class supervision_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass First coursemail instance. */
    protected $maila;
    /** @var \stdClass Second coursemail instance. */
    protected $mailb;
    /** @var \stdClass Supervising teacher (viewall, not a participant). */
    protected $supervisor;
    /** @var \stdClass Other teacher who authors the conversations. */
    protected $teacher;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;

    /**
     * Builds a course with two mailboxes and a few conversations the supervisor
     * does not take part in.
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

        $this->supervisor = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student1 = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');

        // Supervision (mod/coursemail:viewall) is a managers-only archetype default;
        // grant it explicitly to the teaching role so these tests exercise the
        // supervision mechanism rather than the role defaults.
        global $DB;
        $editingteacherid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(
            'mod/coursemail:viewall',
            CAP_ALLOW,
            $editingteacherid,
            \context_course::instance($this->course->id)->id
        );

        // In mailbox A: a staff broadcast and a student-to-staff thread; the
        // supervisor takes part in neither.
        $mba = new mailbox($this->maila->id);
        $mba->start_conversation(
            $this->teacher->id,
            'Broadcast A',
            true,
            [$this->student1->id, $this->student2->id],
            'Hello class'
        );
        $mba->start_conversation(
            $this->student1->id,
            'Question A',
            false,
            [$this->teacher->id],
            'A doubt'
        );

        // In mailbox B: one more thread, for the course-scope aggregation.
        $mbb = new mailbox($this->mailb->id);
        $mbb->start_conversation(
            $this->teacher->id,
            'Broadcast B',
            false,
            [$this->student1->id],
            'Hello again'
        );
    }

    /**
     * get_all_conversations lists every thread of the instance, participant or not.
     *
     * @return void
     */
    public function test_get_all_conversations_lists_every_thread() {
        $mba = new mailbox($this->maila->id);
        $this->assertCount(2, $mba->get_all_conversations());
        // The supervisor's own inbox in A is empty (they received nothing).
        $this->assertCount(0, $mba->get_inbox_conversations($this->supervisor->id));
    }

    /**
     * supervisable_instances narrows the course instances to those with viewall.
     *
     * @return void
     */
    public function test_supervisable_instances() {
        $instances = scope::supervisable_instances($this->course->id, $this->supervisor->id);
        $this->assertCount(2, $instances);
        // A student has no viewall, so supervises nothing.
        $this->assertCount(0, scope::supervisable_instances($this->course->id, $this->student1->id));
    }

    /**
     * The supervision folder returns every conversation in the activity.
     *
     * @return void
     */
    public function test_supervision_folder_activity_scope() {
        $this->setUser($this->supervisor);
        $cma = get_coursemodule_from_instance('coursemail', $this->maila->id);

        $result = get_folder::execute($cma->id, 'all', 0, 0, 'activity');
        $this->assertCount(2, $result['items']);

        // The same user's inbox is empty: supervision is not their personal mail.
        $inbox = get_folder::execute($cma->id, 'inbox', 0, 0, 'activity');
        $this->assertCount(0, $inbox['items']);
    }

    /**
     * In course scope the supervision folder aggregates every supervisable mailbox.
     *
     * @return void
     */
    public function test_supervision_folder_course_scope() {
        $this->setUser($this->supervisor);
        $cma = get_coursemodule_from_instance('coursemail', $this->maila->id);

        $result = get_folder::execute($cma->id, 'all', 0, 0, 'course');
        $this->assertCount(3, $result['items']);
    }

    /**
     * A user without viewall cannot open the supervision folder.
     *
     * @return void
     */
    public function test_supervision_folder_denied_without_viewall() {
        $this->setUser($this->student1);
        $cma = get_coursemodule_from_instance('coursemail', $this->maila->id);

        $this->expectException(\required_capability_exception::class);
        get_folder::execute($cma->id, 'all', 0, 0, 'activity');
    }

    /**
     * A pure supervisor opens a thread read-only (no reply, mark-unread or star).
     *
     * @return void
     */
    public function test_supervisor_opens_thread_readonly() {
        $cma = get_coursemodule_from_instance('coursemail', $this->maila->id);
        $mba = new mailbox($this->maila->id);
        $all = $mba->get_all_conversations();
        $conversationid = reset($all)->get('id');

        // The supervisor takes part in no thread: read-only.
        $this->setUser($this->supervisor);
        $asupervisor = get_conversation::execute($cma->id, $conversationid);
        $this->assertFalse($asupervisor['canreply']);
        $this->assertFalse($asupervisor['canmarkunread']);
        $this->assertFalse($asupervisor['canstar']);

        // The authoring teacher does take part: full controls.
        $this->setUser($this->teacher);
        $asauthor = get_conversation::execute($cma->id, $conversationid);
        $this->assertTrue($asauthor['canstar']);
    }
}
