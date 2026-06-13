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
use mod_coursemail\external\reply;

/**
 * Tests for new-message notifications.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\notifier
 */
class notification_test extends \advanced_testcase {
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
     * Sets up a course with one mailbox, a teacher and two students.
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
    }

    /**
     * Starting a conversation notifies the recipients (not the author), and a
     * requires-response message says so.
     *
     * @return void
     */
    public function test_start_conversation_notifies_recipients() {
        $this->setUser($this->teacher);
        $sink = $this->redirectMessages();

        start_conversation::execute(
            $this->cm->id,
            'Hola clase',
            '<p>Bienvenidos</p>',
            FORMAT_HTML,
            true,
            'users',
            [$this->student1->id, $this->student2->id]
        );

        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);

        $to = [];
        foreach ($messages as $message) {
            $this->assertEquals($this->teacher->id, $message->useridfrom);
            $to[] = (int) $message->useridto;
            // The requires-response note must be present (the message blocks progress).
            $this->assertStringContainsString(
                get_string('notifrequiresresponse', 'coursemail'),
                $message->fullmessage
            );
        }
        sort($to);
        $expected = [(int) $this->student1->id, (int) $this->student2->id];
        sort($expected);
        $this->assertEquals($expected, $to);
    }

    /**
     * Writing through the data layer (e.g. the demo course builder) does not notify.
     *
     * @return void
     */
    public function test_data_layer_does_not_notify() {
        $sink = $this->redirectMessages();

        $mailbox = new mailbox($this->instance->id);
        $mailbox->start_conversation(
            $this->teacher->id,
            'Sin aviso',
            true,
            [$this->student1->id],
            '<p>x</p>'
        );

        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * A reply notifies the other participants, not the replier.
     *
     * @return void
     */
    public function test_reply_notifies_other_participants() {
        // Seed a conversation via the data layer (no notification yet).
        $mailbox = new mailbox($this->instance->id);
        $message = $mailbox->start_conversation(
            $this->teacher->id,
            'Pregunta',
            true,
            [$this->student1->id],
            '<p>¿Dudas?</p>'
        );

        $this->setUser($this->student1);
        $sink = $this->redirectMessages();

        reply::execute($this->cm->id, $message->get('conversationid'), '<p>Sí, una</p>', FORMAT_HTML);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->teacher->id, (int) $messages[0]->useridto);
        $this->assertEquals($this->student1->id, (int) $messages[0]->useridfrom);
    }
}
