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

use mod_coursemail\local\attachments;
use mod_coursemail\external\start_conversation;
use mod_coursemail\external\get_conversation;

/**
 * Tests for message attachments.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\attachments
 */
class attachment_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $instance;
    /** @var \stdClass */
    protected $cm;
    /** @var \context_module */
    protected $context;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student;

    /**
     * Sets up a course with one mailbox, a teacher and a student.
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
        $this->context = \context_module::instance($this->cm->id);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
    }

    /**
     * Stages a file in the current user's draft area and returns the draft item id.
     *
     * @param string $filename The file name.
     * @param string $content The file content.
     * @return int
     */
    protected function stage_draft_file($filename, $content) {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    /**
     * A staged attachment is moved to the message and visible to the recipient.
     *
     * @return void
     */
    public function test_attachment_is_saved_and_visible() {
        $this->setUser($this->teacher);
        $draftitemid = $this->stage_draft_file('nota.txt', 'Repasa el tema 3');

        $result = start_conversation::execute(
            $this->cm->id,
            'Con adjunto',
            '<p>Mira el fichero</p>',
            FORMAT_HTML,
            false,
            'users',
            [$this->student->id],
            $draftitemid
        );

        // The file now lives in the message's attachment area.
        $files = attachments::message_files($this->context, $result['messageid']);
        $this->assertCount(1, $files);
        $this->assertEquals('nota.txt', $files[0]['filename']);

        // The recipient sees it in the conversation.
        $this->setUser($this->student);
        $conversation = get_conversation::execute($this->cm->id, $result['conversationid']);
        $last = end($conversation['messages']);
        $this->assertTrue($last['hasattachments']);
        $this->assertCount(1, $last['attachments']);
        $this->assertEquals('nota.txt', $last['attachments'][0]['filename']);
    }

    /**
     * A message without a draft area has no attachments.
     *
     * @return void
     */
    public function test_no_attachment_without_draft() {
        $this->setUser($this->teacher);
        $result = start_conversation::execute(
            $this->cm->id,
            'Sin adjunto',
            '<p>x</p>',
            FORMAT_HTML,
            false,
            'users',
            [$this->student->id],
            0
        );
        $this->assertCount(0, attachments::message_files($this->context, $result['messageid']));
    }
}
