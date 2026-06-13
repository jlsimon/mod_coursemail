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

namespace mod_coursemail\local;

/**
 * Builds a self-contained demonstration course for mod_coursemail.
 *
 * Creates a course populated with a few dummy resources/activities and three
 * coursemail instances (welcome, mid-course tutoring and pre-exam comments),
 * together with a fixed set of teachers, students and groups, and seeds the
 * professor messages for each mailbox. Intended to be triggered by a site
 * administrator from the plugin settings.
 *
 * All accounts share a single demo password ({@see PASSWORD}); the password is
 * never displayed on the help screen.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testcourse_generator {
    /** @var string Short name that uniquely identifies the demo course. */
    const COURSE_SHORTNAME = 'cm_testcourse';

    /** @var string Shared password for every demo account (never displayed). */
    const PASSWORD = 'cm123%%%';

    /**
     * Returns the fixed teacher accounts (username => [firstname, lastname]).
     *
     * @return array
     */
    public static function teachers() {
        return [
            'cm_profe1' => ['Ana', 'Profesora'],
            'cm_profe2' => ['Carlos', 'Profesor'],
        ];
    }

    /**
     * Returns the fixed student accounts.
     *
     * Each entry: [username, firstname, lastname, group (1 or 2)].
     *
     * @return array
     */
    public static function students() {
        return [
            ['cm_jose', 'José', 'García', 1],
            ['cm_maria', 'María', 'López', 1],
            ['cm_juan', 'Juan', 'Martínez', 1],
            ['cm_ana', 'Ana', 'Sánchez', 1],
            ['cm_luis', 'Luis', 'Fernández', 2],
            ['cm_carmen', 'Carmen', 'Ruiz', 2],
            ['cm_pedro', 'Pedro', 'Díaz', 2],
            ['cm_lucia', 'Lucía', 'Moreno', 2],
        ];
    }

    /**
     * Returns the fixed group names keyed by group number.
     *
     * @return array
     */
    public static function groups() {
        return [1 => 'cm_grupo1', 2 => 'cm_grupo2'];
    }

    /**
     * Returns the existing demo course record, or false if it does not exist.
     *
     * @return \stdClass|false
     */
    public static function existing_course() {
        global $DB;
        return $DB->get_record('course', ['shortname' => self::COURSE_SHORTNAME]);
    }

    /**
     * Creates the whole demonstration course.
     *
     * Aborts (throws) if a course with {@see COURSE_SHORTNAME} already exists.
     *
     * @return \stdClass The created course record.
     */
    public static function create() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/completionlib.php');

        if (self::existing_course()) {
            throw new \moodle_exception('testcourseexists', 'coursemail');
        }

        // 1. Course.
        $catid = $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $course = create_course((object) [
            'fullname'      => get_string('testcoursefullname', 'coursemail'),
            'shortname'     => self::COURSE_SHORTNAME,
            'category'      => $catid,
            'format'        => 'topics',
            'numsections'   => 4,
            'enablecompletion' => 1,
            'summary'       => get_string('testcoursesummary', 'coursemail'),
            'summaryformat' => FORMAT_HTML,
            'visible'       => 1,
        ]);

        $sectionnames = [
            1 => get_string('testcoursesection1', 'coursemail'),
            2 => get_string('testcoursesection2', 'coursemail'),
            3 => get_string('testcoursesection3', 'coursemail'),
            4 => get_string('testcoursesection4', 'coursemail'),
        ];
        foreach ($sectionnames as $num => $name) {
            $DB->set_field(
                'course_sections',
                'name',
                $name,
                ['course' => $course->id, 'section' => $num]
            );
        }

        // 2. Users (idempotent by username — reuse leftovers from a prior run).
        $teacherids = [];
        foreach (self::teachers() as $username => $names) {
            $teacherids[$username] = self::ensure_user($username, $names[0], $names[1]);
        }
        $studentids = [];
        $studentgroup = [];
        foreach (self::students() as $s) {
            $uid = self::ensure_user($s[0], $s[1], $s[2]);
            $studentids[] = $uid;
            $studentgroup[$uid] = $s[3];
        }

        // 3. Enrolment.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $manual = enrol_get_plugin('manual');
        $manualinstance = null;
        foreach (enrol_get_instances($course->id, false) as $inst) {
            if ($inst->enrol === 'manual') {
                $manualinstance = $inst;
                break;
            }
        }
        if (!$manualinstance) {
            $manual->add_default_instance($course);
            $manualinstance = $DB->get_record(
                'enrol',
                ['courseid' => $course->id, 'enrol' => 'manual'],
                '*',
                MUST_EXIST
            );
        }
        foreach ($teacherids as $uid) {
            $manual->enrol_user($manualinstance, $uid, $teacherroleid);
        }
        foreach ($studentids as $uid) {
            $manual->enrol_user($manualinstance, $uid, $studentroleid);
        }

        // 4. Groups.
        $groupid = [];
        foreach (self::groups() as $num => $name) {
            $groupid[$num] = groups_create_group((object) [
                'courseid' => $course->id,
                'name'     => $name,
            ]);
        }
        foreach ($studentids as $uid) {
            groups_add_member($groupid[$studentgroup[$uid]], $uid);
        }

        // 5. Dummy resources / activities.
        self::add_label($course, 1, get_string('testcourselabelwelcome', 'coursemail'));
        $page = self::add_page(
            $course,
            2,
            get_string('testcoursepagename', 'coursemail'),
            get_string('testcoursepagebody', 'coursemail')
        );
        $contents = self::add_label($course, 2, get_string('testcourselabelcontents', 'coursemail'));
        $forum = self::add_forum(
            $course,
            2,
            get_string('testcourseforumname', 'coursemail'),
            get_string('testcourseforumintro', 'coursemail')
        );

        // 6. The three coursemail instances (automatic completion: must reply).
        $cm1 = self::add_coursemail(
            $course,
            1,
            get_string('testcoursemail1name', 'coursemail'),
            get_string('testcoursemail1intro', 'coursemail')
        );
        $cm2 = self::add_coursemail(
            $course,
            3,
            get_string('testcoursemail2name', 'coursemail'),
            get_string('testcoursemail2intro', 'coursemail')
        );
        $cm3 = self::add_coursemail(
            $course,
            4,
            get_string('testcoursemail3name', 'coursemail'),
            get_string('testcoursemail3intro', 'coursemail')
        );

        // Exam placeholder (after the pre-exam comments mailbox).
        $exam = self::add_label($course, 4, get_string('testcourselabelexam', 'coursemail'));

        // 6b. Gate later content on completion of the preceding coursemail(s), so a
        // student cannot move on until they have replied. This demonstrates Moodle's
        // native "restrict access by activity completion" against the coursemail rules.
        self::gate_on_completion($page->coursemodule, [$cm1->coursemodule]);
        self::gate_on_completion($contents->coursemodule, [$cm1->coursemodule]);
        self::gate_on_completion($forum->coursemodule, [$cm1->coursemodule]);
        self::gate_on_completion($cm2->coursemodule, [$cm1->coursemodule]);
        self::gate_on_completion($cm3->coursemodule, [$cm1->coursemodule, $cm2->coursemodule]);
        self::gate_on_completion(
            $exam->coursemodule,
            [$cm1->coursemodule, $cm2->coursemodule, $cm3->coursemodule]
        );

        // 7. Seed the professor messages.
        $profe1 = $teacherids['cm_profe1'];
        self::seed_class_message(
            $cm1->instance,
            $profe1,
            $studentids,
            get_string('testcoursemail1name', 'coursemail'),
            get_string('testcoursemsg1body', 'coursemail')
        );
        self::seed_class_message(
            $cm2->instance,
            $profe1,
            $studentids,
            get_string('testcoursemail2name', 'coursemail'),
            get_string('testcoursemsg2body', 'coursemail')
        );
        self::seed_individual_messages(
            $cm3->instance,
            $profe1,
            $studentids,
            get_string('testcoursemail3name', 'coursemail')
        );

        rebuild_course_cache($course->id, true);

        return $course;
    }

    /**
     * Returns the id of the demo user with the given username, creating it if absent.
     *
     * @param string $username Username (e.g. cm_jose).
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @return int User id.
     */
    protected static function ensure_user($username, $firstname, $lastname) {
        global $CFG, $DB;

        $existing = $DB->get_record(
            'user',
            ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]
        );
        if ($existing) {
            return $existing->id;
        }

        $user = (object) [
            'username'    => $username,
            'firstname'   => $firstname,
            'lastname'    => $lastname,
            'email'       => $username . '@example.com',
            'auth'        => 'manual',
            'confirmed'   => 1,
            'mnethostid'  => $CFG->mnet_localhost_id,
        ];
        // Prefer Spanish for the demo accounts, but only if the pack is installed.
        if (get_string_manager()->translation_exists('es', false)) {
            $user->lang = 'es';
        }
        // Create without a password so the demo password can bypass the site
        // password policy (it is intentionally simple for a test site).
        $userid = user_create_user($user, false, false);
        $record = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        update_internal_user_password($record, self::PASSWORD);

        return $userid;
    }

    /**
     * Builds the base module-info object shared by every module type.
     *
     * @param string $modulename Frankenstyle module name (e.g. 'label').
     * @param \stdClass $course Course record.
     * @param int $section Section number.
     * @param string $name Activity name.
     * @param string $introhtml Intro/description HTML.
     * @return \stdClass
     */
    protected static function base_moduleinfo($modulename, $course, $section, $name, $introhtml) {
        global $DB;

        $mi = new \stdClass();
        $mi->modulename = $modulename;
        $mi->module = $DB->get_field('modules', 'id', ['name' => $modulename], MUST_EXIST);
        $mi->course = $course->id;
        $mi->section = $section;
        $mi->name = $name;
        $mi->visible = 1;
        $mi->visibleoncoursepage = 1;
        $mi->introeditor = ['text' => $introhtml, 'format' => FORMAT_HTML, 'itemid' => 0];
        $mi->showdescription = 1;
        $mi->cmidnumber = '';
        $mi->groupmode = 0;
        $mi->groupingid = 0;
        $mi->completion = COMPLETION_TRACKING_NONE;
        $mi->completionview = 0;
        $mi->completionexpected = 0;
        $mi->completiongradeitemnumber = null;
        $mi->completionpassgrade = 0;
        return $mi;
    }

    /**
     * Adds a label resource to the course.
     *
     * @param \stdClass $course Course record.
     * @param int $section Section number.
     * @param string $html Label content HTML.
     * @return \stdClass The created module info.
     */
    protected static function add_label($course, $section, $html) {
        $mi = self::base_moduleinfo('label', $course, $section, get_string('pluginname', 'label'), $html);
        return add_moduleinfo($mi, $course);
    }

    /**
     * Adds a page resource to the course.
     *
     * @param \stdClass $course Course record.
     * @param int $section Section number.
     * @param string $name Page name.
     * @param string $html Page content HTML.
     * @return \stdClass The created module info.
     */
    protected static function add_page($course, $section, $name, $html) {
        $mi = self::base_moduleinfo('page', $course, $section, $name, '');
        $mi->page = ['text' => $html, 'format' => FORMAT_HTML, 'itemid' => 0];
        $mi->display = 5; // RESOURCELIB_DISPLAY_OPEN.
        $mi->printheading = 1;
        $mi->printintro = 0;
        $mi->printlastmodified = 1;
        return add_moduleinfo($mi, $course);
    }

    /**
     * Adds a discussion forum to the course.
     *
     * @param \stdClass $course Course record.
     * @param int $section Section number.
     * @param string $name Forum name.
     * @param string $introhtml Forum intro HTML.
     * @return \stdClass The created module info.
     */
    protected static function add_forum($course, $section, $name, $introhtml) {
        $mi = self::base_moduleinfo('forum', $course, $section, $name, $introhtml);
        $mi->type = 'general';
        $mi->forcesubscribe = 0;
        $mi->assessed = 0;
        $mi->scale = 0;
        $mi->grade_forum = 0;
        $mi->maxbytes = 0;
        $mi->maxattachments = 1;
        $mi->blockafter = 0;
        $mi->blockperiod = 0;
        $mi->warnafter = 0;
        $mi->completionposts = 0;
        $mi->completiondiscussions = 0;
        $mi->completionreplies = 0;
        return add_moduleinfo($mi, $course);
    }

    /**
     * Adds a coursemail instance to the course.
     *
     * @param \stdClass $course Course record.
     * @param int $section Section number.
     * @param string $name Activity name.
     * @param string $introhtml Intro HTML shown on the course page.
     * @return \stdClass The created module info (->instance is the coursemail id).
     */
    protected static function add_coursemail($course, $section, $name, $introhtml) {
        $mi = self::base_moduleinfo('coursemail', $course, $section, $name, $introhtml);
        $mi->requireresponsedefault = 1;
        // Automatic completion when the student has replied to every conversation
        // that requires a response: this is the condition the later activities gate on.
        $mi->completion = COMPLETION_TRACKING_AUTOMATIC;
        $mi->completionmail = 1;
        $mi->completionrequireread = 1;
        return add_moduleinfo($mi, $course);
    }

    /**
     * Restricts a module so it is only available once the given coursemail
     * activities are marked complete (native "restrict access by completion").
     *
     * @param int $cmid Course module id to restrict.
     * @param int[] $prereqcmids Course module ids that must be complete first.
     * @return void
     */
    protected static function gate_on_completion($cmid, array $prereqcmids) {
        global $DB;

        if (empty($prereqcmids)) {
            return;
        }
        $conditions = [];
        $showc = [];
        foreach ($prereqcmids as $prereqcmid) {
            $conditions[] = [
                'type' => 'completion',
                'cm' => (int) $prereqcmid,
                'e' => COMPLETION_COMPLETE,
            ];
            $showc[] = true;
        }
        $tree = ['op' => '&', 'c' => $conditions, 'showc' => $showc];
        $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $cmid]);
    }

    /**
     * Seeds a single class-wide conversation (one message to all students).
     *
     * @param int $coursemailid Coursemail instance id.
     * @param int $senderid Sending teacher user id.
     * @param int[] $studentids Recipient student ids.
     * @param string $subject Conversation subject.
     * @param string $body Message body HTML.
     * @return void
     */
    protected static function seed_class_message($coursemailid, $senderid, array $studentids, $subject, $body) {
        $mailbox = new mailbox($coursemailid);
        $mailbox->start_conversation($senderid, $subject, true, $studentids, $body, FORMAT_HTML);
    }

    /**
     * Seeds one personalised conversation per student.
     *
     * @param int $coursemailid Coursemail instance id.
     * @param int $senderid Sending teacher user id.
     * @param int[] $studentids Recipient student ids.
     * @param string $subject Conversation subject.
     * @return void
     */
    protected static function seed_individual_messages($coursemailid, $senderid, array $studentids) {
        global $DB;

        $mailbox = new mailbox($coursemailid);
        $focus = self::exam_focus_areas();
        $i = 0;
        foreach ($studentids as $uid) {
            $student = $DB->get_record('user', ['id' => $uid], 'id, firstname', MUST_EXIST);
            $a = (object) [
                'firstname' => $student->firstname,
                'focus'     => $focus[$i % count($focus)],
            ];
            $body = get_string('testcoursemsg3body', 'coursemail', $a);
            $subject = get_string(
                'testcoursemsg3subject',
                'coursemail',
                (object) ['firstname' => $student->firstname]
            );
            $mailbox->start_conversation($senderid, $subject, true, [$uid], $body, FORMAT_HTML);
            $i++;
        }
    }

    /**
     * Returns the rotating list of per-student exam focus areas.
     *
     * @return string[]
     */
    protected static function exam_focus_areas() {
        return [
            get_string('testcoursefocus1', 'coursemail'),
            get_string('testcoursefocus2', 'coursemail'),
            get_string('testcoursefocus3', 'coursemail'),
            get_string('testcoursefocus4', 'coursemail'),
        ];
    }
}
