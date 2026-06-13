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
 * Helpers for the mailbox scope: a single activity ("activity" scope) or every
 * coursemail activity of the course at once ("course" scope, the unified view).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scope {
    /**
     * Returns the coursemail instances of a course that a user may read.
     *
     * Only instances the user can actually see (`$cm->uservisible`, so hidden or
     * availability-restricted activities are excluded) and holds the view capability
     * in are returned. This is the access-checked set the unified view aggregates over.
     *
     * @param int $courseid Course id.
     * @param int $userid User id the visibility is computed for.
     * @return \stdClass[] Keyed by coursemail instance id, each
     *                     {cmid, coursemailid, name, context}.
     */
    public static function course_instances($courseid, $userid) {
        $modinfo = get_fast_modinfo($courseid, $userid);
        $instances = [];
        foreach ($modinfo->get_instances_of('coursemail') as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            if (!has_capability('mod/coursemail:view', $context, $userid)) {
                continue;
            }
            $instances[(int) $cm->instance] = (object) [
                'cmid' => (int) $cm->id,
                'coursemailid' => (int) $cm->instance,
                'name' => $cm->get_formatted_name(),
                'context' => $context,
            ];
        }
        return $instances;
    }

    /**
     * Returns the subset of the course instances the user may supervise.
     *
     * Supervising requires the viewall capability (staff overseeing every mailbox).
     * Used to aggregate the supervision folder over a whole course: it narrows the
     * readable instances down to those where the user holds mod/coursemail:viewall.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return \stdClass[] Keyed by coursemail instance id, same shape as
     *                     {@see course_instances}.
     */
    public static function supervisable_instances($courseid, $userid) {
        $instances = [];
        foreach (self::course_instances($courseid, $userid) as $id => $instance) {
            if (has_capability('mod/coursemail:viewall', $instance->context, $userid)) {
                $instances[$id] = $instance;
            }
        }
        return $instances;
    }

    /**
     * Returns the subset of the course instances the user may compose in.
     *
     * Composing requires either the send capability (staff broadcasting) or the
     * reply capability (a student writing to staff). Used to offer a target-activity
     * picker when composing from the unified view.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array[] List of { cmid:int, name:string }, ordered by activity name.
     */
    public static function composable_instances($courseid, $userid) {
        $targets = [];
        foreach (self::course_instances($courseid, $userid) as $instance) {
            $cansend = has_capability('mod/coursemail:send', $instance->context, $userid);
            $canreply = has_capability('mod/coursemail:reply', $instance->context, $userid);
            if ($cansend || $canreply) {
                $targets[] = ['cmid' => $instance->cmid, 'name' => $instance->name];
            }
        }
        \core_collator::asort_array_of_arrays_by_key($targets, 'name');
        return array_values($targets);
    }
}
