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
use mod_coursemail\local\recipients;
use mod_coursemail\external\start_conversation;
use mod_coursemail\external\reply;
use mod_coursemail\external\save_draft;
use mod_coursemail\external\send_draft;
use mod_coursemail\external\get_folder;

/**
 * Tests for the mod_coursemail write side (recipients and write externals).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\local\recipients
 * @covers     \mod_coursemail\external\start_conversation
 * @covers     \mod_coursemail\external\reply
 * @covers     \mod_coursemail\external\save_draft
 * @covers     \mod_coursemail\external\send_draft
 */
class write_test extends \advanced_testcase {
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
    protected $student1;
    /** @var \stdClass */
    protected $student2;
    /** @var mailbox */
    protected $mailbox;

    /**
     * Sets up shared fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->instance = $generator->create_module('coursemail', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_id('coursemail', $this->instance->cmid, 0, false, MUST_EXIST);
        $this->context = \context_module::instance($this->cm->id);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student1 = $generator->create_and_enrol($this->course, 'student');
        $this->student2 = $generator->create_and_enrol($this->course, 'student');
        $this->mailbox = new mailbox($this->instance->id);
    }

    /**
     * Class targeting returns the students only — never the sender nor co-teachers.
     */
    public function test_recipients_resolve_class(): void {
        $coteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $this->setUser($this->teacher);
        $ids = recipients::resolve($this->cm, $this->context, $this->teacher->id, 'class', []);
        $this->assertContains((int) $this->student1->id, $ids);
        $this->assertContains((int) $this->student2->id, $ids);
        $this->assertNotContains((int) $this->teacher->id, $ids);
        // A co-teacher is staff, so "the whole class" must not reach them.
        $this->assertNotContains((int) $coteacher->id, $ids);
    }

    /**
     * Group targeting returns only members of the chosen group.
     */
    public function test_recipients_resolve_group(): void {
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($group, $this->student1);

        $this->setUser($this->teacher);
        $ids = recipients::resolve($this->cm, $this->context, $this->teacher->id, 'group', [$group->id]);
        $this->assertEquals([(int) $this->student1->id], $ids);
    }

    /**
     * Explicit user targeting validates membership.
     */
    public function test_recipients_resolve_users(): void {
        $this->setUser($this->teacher);
        $ids = recipients::resolve(
            $this->cm,
            $this->context,
            $this->teacher->id,
            'users',
            [$this->student1->id, -1]
        );
        $this->assertEquals([(int) $this->student1->id], $ids);
    }

    /**
     * A teacher never reaches another teacher: co-teachers are absent from the
     * individual-recipient picker and are dropped from both users and group
     * targeting (a co-teacher who is a group member is excluded too).
     */
    public function test_teacher_recipients_exclude_coteachers(): void {
        $coteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($group, $this->student1);
        groups_add_member($group, $coteacher);

        $this->setUser($this->teacher);

        // The composer picker lists students only, never the co-teacher.
        $options = recipients::composer_options($this->cm, $this->context, $this->teacher->id);
        $ids = array_column($options['users'], 'id');
        $this->assertContains((int) $this->student1->id, $ids);
        $this->assertContains((int) $this->student2->id, $ids);
        $this->assertNotContains((int) $coteacher->id, $ids);

        // Explicitly targeting a co-teacher by id is silently dropped.
        $users = recipients::resolve(
            $this->cm,
            $this->context,
            $this->teacher->id,
            'users',
            [$this->student1->id, $coteacher->id]
        );
        $this->assertEquals([(int) $this->student1->id], $users);

        // Group targeting reaches the group's students, not its co-teacher member.
        $groupids = recipients::resolve($this->cm, $this->context, $this->teacher->id, 'group', [$group->id]);
        $this->assertEquals([(int) $this->student1->id], $groupids);
    }

    /**
     * The composer surfaces the instance defaults for the staff-only compose switches,
     * and never offers them to students (cansend false).
     */
    public function test_composer_options_surface_instance_defaults(): void {
        $generator = $this->getDataGenerator();
        $instance = $generator->create_module('coursemail', [
            'course' => $this->course->id,
            'requireresponsedefault' => 1,
            'requiremanualcompletedefault' => 1,
        ]);
        $cm = get_coursemodule_from_id('coursemail', $instance->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $this->setUser($this->teacher);
        $staffoptions = recipients::composer_options($cm, $context, $this->teacher->id);
        $this->assertTrue($staffoptions['requiresresponsedefault']);
        $this->assertTrue($staffoptions['requiremanualcompletedefault']);

        // A student cannot set these staff-only switches, so the defaults are withheld.
        $this->setUser($this->student1);
        $studentoptions = recipients::composer_options($cm, $context, $this->student1->id);
        $this->assertFalse($studentoptions['requiresresponsedefault']);
        $this->assertFalse($studentoptions['requiremanualcompletedefault']);
    }

    /**
     * Staff targeting (student to teachers) returns the senders.
     */
    public function test_recipients_resolve_staff(): void {
        $this->setUser($this->student1);
        $ids = recipients::resolve($this->cm, $this->context, $this->student1->id, 'staff', []);
        $this->assertEquals([(int) $this->teacher->id], $ids);
    }

    /**
     * In separate-groups mode a student only sees teachers sharing their group,
     * which also drives the single-teacher shortcut in the composer options.
     */
    public function test_visible_staff_respects_separate_groups(): void {
        $teacher2 = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $instance = $this->getDataGenerator()->create_module(
            'coursemail',
            ['course' => $this->course->id, 'groupmode' => SEPARATEGROUPS]
        );
        $cm = get_coursemodule_from_id('coursemail', $instance->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $g1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $g2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($g1, $this->teacher);
        groups_add_member($g1, $this->student1);
        groups_add_member($g2, $teacher2);

        $this->setUser($this->student1);

        $visible = recipients::visible_staff($cm, $context, $this->student1->id);
        $this->assertEquals([(int) $this->teacher->id], array_keys($visible));

        $options = recipients::composer_options($cm, $context, $this->student1->id);
        $this->assertFalse($options['cansend']);
        $this->assertTrue($options['single']);
        $this->assertFalse($options['norecipients']);
        $this->assertCount(1, $options['users']);

        $ids = recipients::resolve($cm, $context, $this->student1->id, 'staff', []);
        $this->assertEquals([(int) $this->teacher->id], $ids);
    }

    /**
     * In separate-groups mode a teacher without accessallgroups only addresses the
     * students (and groups) of their own groups, both in the composer options and
     * when resolving class/group/users targeting. Editing teachers, which hold
     * accessallgroups by default, keep seeing everyone (covered separately).
     */
    public function test_teacher_recipients_respect_separate_groups(): void {
        // A non-editing teacher can send but lacks accessallgroups, so it is the
        // role that gets confined to its own groups.
        $groupteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');

        $instance = $this->getDataGenerator()->create_module(
            'coursemail',
            ['course' => $this->course->id, 'groupmode' => SEPARATEGROUPS]
        );
        $cm = get_coursemodule_from_id('coursemail', $instance->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $g1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $g2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($g1, $groupteacher);
        groups_add_member($g1, $this->student1);
        groups_add_member($g2, $this->student2);

        $this->setUser($groupteacher);

        // Composer options: only student1 and only group g1 are offered.
        $options = recipients::composer_options($cm, $context, $groupteacher->id);
        $this->assertTrue($options['cansend']);
        $this->assertEquals([(int) $this->student1->id], array_column($options['users'], 'id'));
        $this->assertEquals([(int) $g1->id], array_column($options['groups'], 'id'));

        // Class targeting reaches only the teacher's own group.
        $class = recipients::resolve($cm, $context, $groupteacher->id, 'class', []);
        $this->assertEquals([(int) $this->student1->id], $class);

        // Targeting a group outside the teacher's groups yields nothing.
        $outside = recipients::resolve($cm, $context, $groupteacher->id, 'group', [$g2->id]);
        $this->assertEquals([], $outside);

        // Explicitly targeting a student outside the teacher's groups is rejected.
        $users = recipients::resolve(
            $cm,
            $context,
            $groupteacher->id,
            'users',
            [$this->student1->id, $this->student2->id]
        );
        $this->assertEquals([(int) $this->student1->id], $users);
    }

    /**
     * An editing teacher holds accessallgroups by default and therefore keeps
     * seeing every student even in separate-groups mode.
     */
    public function test_editing_teacher_sees_all_groups(): void {
        $instance = $this->getDataGenerator()->create_module(
            'coursemail',
            ['course' => $this->course->id, 'groupmode' => SEPARATEGROUPS]
        );
        $cm = get_coursemodule_from_id('coursemail', $instance->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $g1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $g2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($g1, $this->student1);
        groups_add_member($g2, $this->student2);

        $this->setUser($this->teacher);

        $class = recipients::resolve($cm, $context, $this->teacher->id, 'class', []);
        $this->assertContains((int) $this->student1->id, $class);
        $this->assertContains((int) $this->student2->id, $class);
    }

    /**
     * Selected-staff targeting keeps only the chosen, visible teachers.
     */
    public function test_recipients_resolve_staffselected(): void {
        $teacher2 = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $this->setUser($this->student1);
        $ids = recipients::resolve(
            $this->cm,
            $this->context,
            $this->student1->id,
            'staffselected',
            [$this->teacher->id, $this->student2->id]
        );
        $this->assertEquals([(int) $this->teacher->id], $ids);
        $this->assertNotContains((int) $teacher2->id, $ids);
    }

    /**
     * A student starts a conversation addressed to selected teachers only;
     * the requires-response flag is forced off for student-initiated threads.
     */
    public function test_start_conversation_student_to_selected_staff(): void {
        $teacher2 = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $this->setUser($this->student1);
        $result = start_conversation::execute(
            $this->instance->cmid,
            'Doubt',
            'Question',
            FORMAT_MOODLE,
            true,
            'staffselected',
            [$this->teacher->id]
        );

        $conversation = new \mod_coursemail\local\conversation($result['conversationid']);
        $this->assertEquals(0, $conversation->get('requiresresponse'));

        $this->setUser($this->teacher);
        $this->assertCount(1, get_folder::execute($this->instance->cmid, 'inbox')['items']);

        $this->setUser($teacher2);
        $this->assertCount(0, get_folder::execute($this->instance->cmid, 'inbox')['items']);
    }

    /**
     * A teacher starts a conversation; recipients receive it and events fire.
     */
    public function test_start_conversation_external(): void {
        $this->setUser($this->teacher);

        $sink = $this->redirectEvents();
        $result = start_conversation::execute(
            $this->instance->cmid,
            'Hi',
            'Body',
            FORMAT_MOODLE,
            true,
            'users',
            [$this->student1->id]
        );
        $events = $sink->get_events();
        $sink->close();

        $this->assertGreaterThan(0, $result['conversationid']);

        $this->setUser($this->student1);
        $inbox = get_folder::execute($this->instance->cmid, 'inbox');
        $this->assertCount(1, $inbox['items']);

        $created = array_filter($events, function ($e) {
            return $e instanceof \mod_coursemail\event\conversation_created;
        });
        $sent = array_filter($events, function ($e) {
            return $e instanceof \mod_coursemail\event\message_sent;
        });
        $this->assertCount(1, $created);
        $this->assertCount(1, $sent);
    }

    /**
     * A student starting a conversation reaches staff.
     */
    public function test_start_conversation_student_to_staff(): void {
        $this->setUser($this->student1);
        start_conversation::execute(
            $this->instance->cmid,
            'Doubt',
            'Question',
            FORMAT_MOODLE,
            false,
            'staff',
            []
        );

        $this->setUser($this->teacher);
        $inbox = get_folder::execute($this->instance->cmid, 'inbox');
        $this->assertCount(1, $inbox['items']);
    }

    /**
     * Starting a conversation with no resolvable recipients is rejected.
     */
    public function test_start_conversation_no_recipients(): void {
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        start_conversation::execute(
            $this->instance->cmid,
            'Hi',
            'Body',
            FORMAT_MOODLE,
            false,
            'users',
            []
        );
    }

    /**
     * Replying delivers to the other participants and fires the reply event.
     */
    public function test_reply_external(): void {
        $message = $this->mailbox->start_conversation(
            $this->teacher->id,
            'Hi',
            false,
            [$this->student1->id],
            'Body'
        );

        $this->setUser($this->student1);
        $sink = $this->redirectEvents();
        reply::execute($this->instance->cmid, $message->get('conversationid'), 'My answer');
        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals(1, $this->mailbox->count_unread($this->teacher->id));
        $replied = array_filter($events, function ($e) {
            return $e instanceof \mod_coursemail\event\message_replied;
        });
        $this->assertCount(1, $replied);
    }

    /**
     * A draft can be saved and then sent to recipients.
     */
    public function test_save_and_send_draft_external(): void {
        $this->setUser($this->teacher);

        $saved = save_draft::execute($this->instance->cmid, 0, 'Draft subject', 'Draft body');
        $this->assertGreaterThan(0, $saved['draftid']);

        $drafts = get_folder::execute($this->instance->cmid, 'drafts');
        $this->assertCount(1, $drafts['items']);

        $sent = send_draft::execute(
            $this->instance->cmid,
            $saved['draftid'],
            'Final subject',
            'Final body',
            FORMAT_MOODLE,
            true,
            'users',
            [$this->student1->id]
        );
        $this->assertGreaterThan(0, $sent['messageid']);

        // No longer a draft; delivered to the student.
        $drafts = get_folder::execute($this->instance->cmid, 'drafts');
        $this->assertCount(0, $drafts['items']);

        $this->setUser($this->student1);
        $inbox = get_folder::execute($this->instance->cmid, 'inbox');
        $this->assertCount(1, $inbox['items']);
        $this->assertEquals('Final subject', $inbox['items'][0]['subject']);
    }

    /**
     * Enrols a user holding a custom role with view + send but NOT reply,
     * mirroring a teaching role that may broadcast to students yet is not a
     * student-style "reply to staff" participant.
     *
     * @return \stdClass The enrolled user.
     */
    protected function create_send_only_user(): \stdClass {
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('mod/coursemail:view', CAP_ALLOW, $roleid, $this->context->id);
        assign_capability('mod/coursemail:send', CAP_ALLOW, $roleid, $this->context->id);
        // Deliberately NOT granting mod/coursemail:reply.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $roleid);
        return $user;
    }

    /**
     * A role with :send but without :reply can address users, the class and groups
     * through both the recipients resolver and the write externals. This locks the
     * v0.11.2 capability alignment (externals gate on :view, then resolve() enforces
     * :send for these routes) so such a role is never blocked at the door.
     */
    public function test_send_without_reply_can_target_users_class_and_group(): void {
        $sender = $this->create_send_only_user();
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($group, $this->student1);
        // The sender lacks accessallgroups, so it may only target a group it belongs to.
        groups_add_member($group, $sender);

        $this->setUser($sender);
        $this->assertTrue(has_capability('mod/coursemail:send', $this->context));
        $this->assertFalse(has_capability('mod/coursemail:reply', $this->context));

        // The resolver accepts every send-side targeting mode.
        $users = recipients::resolve($this->cm, $this->context, $sender->id, 'users', [$this->student1->id]);
        $this->assertEquals([(int) $this->student1->id], $users);
        $class = recipients::resolve($this->cm, $this->context, $sender->id, 'class', []);
        $this->assertContains((int) $this->student1->id, $class);
        $grp = recipients::resolve($this->cm, $this->context, $sender->id, 'group', [$group->id]);
        $this->assertEquals([(int) $this->student1->id], $grp);

        // And the externals deliver: start a class-wide conversation...
        $result = start_conversation::execute(
            $this->instance->cmid,
            'Hi',
            'Body',
            FORMAT_MOODLE,
            true,
            'class',
            []
        );
        $this->assertGreaterThan(0, $result['conversationid']);

        // ...and save then send a draft to a group.
        $saved = save_draft::execute($this->instance->cmid, 0, 'Draft', 'Body');
        $sent = send_draft::execute(
            $this->instance->cmid,
            $saved['draftid'],
            'Subject',
            'Body',
            FORMAT_MOODLE,
            true,
            'group',
            [$group->id]
        );
        $this->assertGreaterThan(0, $sent['messageid']);

        $this->setUser($this->student1);
        $this->assertCount(2, get_folder::execute($this->instance->cmid, 'inbox')['items']);
    }

    /**
     * A role with :send but without :reply must still be denied the student-side
     * staff routes, which the resolver gates on :reply.
     */
    public function test_send_without_reply_is_denied_staff_route(): void {
        $sender = $this->create_send_only_user();
        $this->setUser($sender);

        $this->expectException(\required_capability_exception::class);
        recipients::resolve($this->cm, $this->context, $sender->id, 'staff', []);
    }

    /**
     * The staffselected route is likewise denied without :reply (separate test so
     * the single expected exception does not mask the staff route above).
     */
    public function test_send_without_reply_is_denied_staffselected_route(): void {
        $sender = $this->create_send_only_user();
        $this->setUser($sender);

        $this->expectException(\required_capability_exception::class);
        recipients::resolve($this->cm, $this->context, $sender->id, 'staffselected', [$this->teacher->id]);
    }
}
