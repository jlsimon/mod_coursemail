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

namespace mod_coursemail\output;

/**
 * Mobile app output for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the course view for the Moodle Mobile app.
     *
     * Renders the activity description and a link to open the full mailbox in the browser.
     *
     * @param array $args Arguments from the app (expects cmid and courseid).
     * @return array The view structure expected by the app (templates, javascript, otherdata).
     */
    public static function mobile_course_view(array $args): array {
        global $OUTPUT, $DB;

        $args = (object) $args;
        $cm = get_coursemodule_from_id('coursemail', $args->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        require_login($cm->course, false, $cm);
        require_capability('mod/coursemail:view', $context);

        $instance = $DB->get_record('coursemail', ['id' => $cm->instance], '*', MUST_EXIST);

        $data = [
            'cmid' => $cm->id,
            'name' => format_string($instance->name, true, ['context' => $context]),
            'url' => (new \moodle_url('/mod/coursemail/view.php', ['id' => $cm->id]))->out(false),
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_coursemail/mobile_view', $data),
                ],
            ],
            // The intro HTML is passed as otherdata so the template can bind it safely
            // through core-format-text instead of embedding raw HTML in the markup.
            'otherdata' => [
                'intro' => format_module_intro('coursemail', $instance, $cm->id),
            ],
            'javascript' => '',
        ];
    }
}
