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
 * Main view: renders the mailbox for a coursemail instance.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/coursemail/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module id.
$c  = optional_param('c', 0, PARAM_INT);  // Coursemail instance id.

if ($id) {
    $cm = get_coursemodule_from_id('coursemail', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $instance = $DB->get_record('coursemail', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $instance = $DB->get_record('coursemail', ['id' => $c], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('coursemail', $instance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/coursemail:view', $context);

// Trigger the course module viewed event.
$event = \mod_coursemail\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('coursemail', $instance);
$event->trigger();

// Mark the activity as viewed for completion purposes.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/coursemail/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($instance);

$cansend = has_capability('mod/coursemail:send', $context);
$canreply = has_capability('mod/coursemail:reply', $context);
$cancompose = $cansend || $canreply;

// A student composes towards teachers: tailor the compose button to who is reachable.
// No teacher available -> nothing to compose; a single teacher -> a direct shortcut label.
$composelabel = null;
if (!$cansend && $canreply) {
    $staffcount = count(\mod_coursemail\local\recipients::visible_staff($cm, $context, $USER->id));
    if ($staffcount === 0) {
        $cancompose = false;
    } else if ($staffcount === 1) {
        $composelabel = get_string('messageteacher', 'coursemail');
    }
}

// The unified ("course") scope aggregates every coursemail of the course the user
// can read; only offer it when there is more than one such activity. The compose
// targets list lets the user pick a destination activity when composing in that mode.
$courseinstances = \mod_coursemail\local\scope::course_instances($course->id, $USER->id);
$scopeavailable = count($courseinstances) > 1;
$composetargets = $scopeavailable
    ? \mod_coursemail\local\scope::composable_instances($course->id, $USER->id)
    : [];

// Staff with viewall get a supervision folder listing every conversation.
$canviewall = has_capability('mod/coursemail:viewall', $context);

$output = $PAGE->get_renderer('mod_coursemail');
$renderable = new \mod_coursemail\output\mailbox_page(
    $cm->id,
    $cancompose,
    $composelabel,
    $scopeavailable,
    $canviewall
);

$PAGE->requires->js_call_amd('mod_coursemail/mailbox', 'init', [[
    'cmid' => (int) $cm->id,
    'scopeavailable' => $scopeavailable,
    'composetargets' => $composetargets,
    'isstaff' => $cansend,
]]);

echo $output->header();
echo $output->heading(format_string($instance->name));

if (!empty($instance->intro)) {
    echo $output->box(format_module_intro('coursemail', $instance, $cm->id), 'generalbox', 'intro');
}

echo $output->render($renderable);

echo $output->footer();
