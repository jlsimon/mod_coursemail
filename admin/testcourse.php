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

/**
 * Administration tool: create a self-contained demo course for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_coursemail\local\testcourse_generator;

admin_externalpage_setup('modcoursemailtestcourse');

$context = context_system::instance();
$pageurl = new moodle_url('/mod/coursemail/admin/testcourse.php');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testcoursepage', 'coursemail'));

$existing = testcourse_generator::existing_course();

// Handle the create action.
if ($action === 'create' && confirm_sesskey()) {
    if ($existing) {
        echo $OUTPUT->notification(get_string('testcoursealreadyexists', 'coursemail'), 'notifyproblem');
    } else {
        try {
            $course = testcourse_generator::create();
            $existing = $course;
            echo $OUTPUT->notification(get_string('testcoursecreated', 'coursemail'), 'notifysuccess');
            $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            echo html_writer::div(
                html_writer::link(
                    $courseurl,
                    get_string('testcourseopen', 'coursemail'),
                    ['class' => 'btn btn-primary']
                ),
                'mb-3'
            );
        } catch (moodle_exception $e) {
            echo $OUTPUT->notification($e->getMessage(), 'notifyproblem');
        }
    }
}

// Intro / help text.
echo html_writer::tag('p', get_string('testcourseintro', 'coursemail'));

// Create button (or link to the existing course).
if ($existing) {
    $courseurl = new moodle_url('/course/view.php', ['id' => $existing->id]);
    echo $OUTPUT->notification(get_string('testcoursealreadyexists', 'coursemail'), 'notifymessage');
    echo html_writer::div(
        html_writer::link(
            $courseurl,
            get_string('testcourseopen', 'coursemail'),
            ['class' => 'btn btn-secondary']
        ),
        'mb-3'
    );
} else {
    $createurl = new moodle_url($pageurl, ['action' => 'create', 'sesskey' => sesskey()]);
    echo html_writer::div(
        html_writer::link(
            $createurl,
            get_string('testcoursecreate', 'coursemail'),
            ['class' => 'btn btn-primary']
        ),
        'mb-3'
    );
}

// Help heading and accounts.
echo $OUTPUT->heading(get_string('testcoursehelpheading', 'coursemail'), 3);
echo html_writer::tag('p', get_string('testcoursepassnote', 'coursemail'));

$groups = testcourse_generator::groups();

// Teachers table.
echo $OUTPUT->heading(get_string('testcourseteachersheading', 'coursemail'), 4);
$tt = new html_table();
$tt->head = [
    get_string('testcoursecolusername', 'coursemail'),
    get_string('testcoursecolname', 'coursemail'),
    get_string('testcoursecolrole', 'coursemail'),
];
foreach (testcourse_generator::teachers() as $username => $names) {
    $tt->data[] = [
        s($username),
        s($names[0] . ' ' . $names[1]),
        get_string('testcourseroleteacher', 'coursemail'),
    ];
}
echo html_writer::table($tt);

// Students table.
echo $OUTPUT->heading(get_string('testcoursestudentsheading', 'coursemail'), 4);
$st = new html_table();
$st->head = [
    get_string('testcoursecolusername', 'coursemail'),
    get_string('testcoursecolname', 'coursemail'),
    get_string('testcoursecolgroup', 'coursemail'),
];
foreach (testcourse_generator::students() as $student) {
    $st->data[] = [
        s($student[0]),
        s($student[1] . ' ' . $student[2]),
        s($groups[$student[3]]),
    ];
}
echo html_writer::table($st);

// Activities description.
echo $OUTPUT->heading(get_string('testcourseactivitiesheading', 'coursemail'), 3);
echo html_writer::tag('p', get_string('testcourseactivitiesdesc', 'coursemail'));

echo $OUTPUT->footer();
