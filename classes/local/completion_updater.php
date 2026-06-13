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
 * Triggers re-evaluation of activity completion for affected users.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_updater {
    /**
     * Recomputes completion for the given users on a coursemail course module.
     *
     * Safe to call unconditionally: it returns early unless the activity uses
     * automatic completion tracking (manual / no tracking have nothing to recompute).
     *
     * @param \stdClass|\cm_info $cm The course module (must expose course, id, completion).
     * @param int[] $userids The users whose completion may have changed.
     */
    public static function update_for_users($cm, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $completion = new \completion_info($course);
        // Only automatic tracking is recomputed here: our custom rules (read/reply)
        // are automatic. Under manual tracking the student ticks the box, so calling
        // update_state() with COMPLETION_UNKNOWN would be rejected as an invalid
        // manual state; under no tracking there is nothing to do.
        if ($completion->is_enabled($cm) != COMPLETION_TRACKING_AUTOMATIC) {
            return;
        }

        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
