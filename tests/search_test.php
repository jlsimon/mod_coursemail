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
use mod_coursemail\external\search_messages;

/**
 * Tests for conversation search.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\mailbox::search_conversations
 * @covers     \mod_coursemail\external\search_messages
 */
class search_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass First coursemail instance. */
    protected $maila;
    /** @var \stdClass Second coursemail instance. */
    protected $mailb;
    /** @var \stdClass Supervising teacher (viewall). */
    protected $supervisor;
    /** @var \stdClass Authoring teacher. */
    protected $teacher;
    /** @var \stdClass */
    protected $student1;
    /** @var \stdClass */
    protected $student2;

    /**
     * Sets up a course with two mailboxes and three distinct conversations.
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
        // grant it explicitly to the teaching role so the supervisor can search across
        // every mailbox here.
        global $DB;
        $editingteacherid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(
            'mod/coursemail:viewall',
            CAP_ALLOW,
            $editingteacherid,
            \context_course::instance($this->course->id)->id
        );

        $mba = new mailbox($this->maila->id);
        // Conv1: subject match for "matematicas"; body match for "algebra".
        $mba->start_conversation(
            $this->teacher->id,
            'Examen de matematicas',
            true,
            [$this->student1->id, $this->student2->id],
            'Repasa el algebra del bloque 1'
        );
        // Conv2: only student1 + teacher take part.
        $mba->start_conversation(
            $this->student1->id,
            'Duda de historia',
            false,
            [$this->teacher->id],
            'No entiendo la unidad'
        );

        $mbb = new mailbox($this->mailb->id);
        // Conv3: body match for "matematicas", in the second mailbox.
        $mbb->start_conversation(
            $this->teacher->id,
            'Aviso general',
            false,
            [$this->student1->id],
            'Las matematicas avanzadas empiezan ya'
        );
    }

    /**
     * A participant finds their matching conversations by subject and by body.
     *
     * @return void
     */
    public function test_search_matches_subject_and_body_for_participant() {
        $mba = new mailbox($this->maila->id);

        // Subject match.
        $bysubject = $mba->search_conversations($this->student1->id, 'matematicas');
        $this->assertCount(1, $bysubject);

        // Body match.
        $bybody = $mba->search_conversations($this->student1->id, 'algebra');
        $this->assertCount(1, $bybody);
    }

    /**
     * A non-participant does not see others' conversations in a normal search.
     *
     * @return void
     */
    public function test_search_excludes_non_participant() {
        $mba = new mailbox($this->maila->id);
        // Student2 does not take part in the "historia" thread.
        $this->assertCount(0, $mba->search_conversations($this->student2->id, 'historia'));
        // The supervisor (includeall) does see it.
        $this->assertCount(1, $mba->search_conversations($this->supervisor->id, 'historia', 0, 0, true));
    }

    /**
     * The external returns a participant's matches in activity scope.
     *
     * @return void
     */
    public function test_external_activity_scope() {
        $this->setUser($this->student1);
        $cma = get_coursemodule_from_instance('coursemail', $this->maila->id);

        $result = search_messages::execute($cma->id, 'matematicas', 0, 'activity');
        $this->assertCount(1, $result['items']);
    }

    /**
     * In course scope a supervisor's search aggregates every supervisable mailbox.
     *
     * @return void
     */
    public function test_external_course_scope_supervisor() {
        $this->setUser($this->supervisor);
        $cma = get_coursemodule_from_instance('coursemail', $this->maila->id);

        $result = search_messages::execute($cma->id, 'matematicas', 0, 'course');
        // Conv1 (mailbox A subject) + conv3 (mailbox B body).
        $this->assertCount(2, $result['items']);
    }

    /**
     * A too-short query returns no results.
     *
     * @return void
     */
    public function test_external_short_query_returns_empty() {
        $this->setUser($this->student1);
        $cma = get_coursemodule_from_instance('coursemail', $this->maila->id);

        $result = search_messages::execute($cma->id, 'a', 0, 'activity');
        $this->assertCount(0, $result['items']);
    }
}
