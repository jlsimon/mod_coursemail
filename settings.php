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
 * Global administration settings for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Admin tool: build a self-contained demo course showcasing the activity.
    $ADMIN->add('modsettings', new admin_externalpage(
        'modcoursemailtestcourse',
        get_string('testcoursepage', 'coursemail'),
        new moodle_url('/mod/coursemail/admin/testcourse.php')
    ));
}

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'mod_coursemail/perpage',
        get_string('perpage', 'coursemail'),
        get_string('perpage_desc', 'coursemail'),
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_coursemail/attachmentmaxbytes',
        get_string('attachmentmaxbytes', 'coursemail'),
        get_string('attachmentmaxbytes_desc', 'coursemail'),
        5242880,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_coursemail/attachmentmaxfiles',
        get_string('attachmentmaxfiles', 'coursemail'),
        get_string('attachmentmaxfiles_desc', 'coursemail'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_description(
        'mod_coursemail/testcourselink',
        get_string('testcoursepage', 'coursemail'),
        get_string(
            'testcoursesettingslink',
            'coursemail',
            (new moodle_url('/mod/coursemail/admin/testcourse.php'))->out()
        )
    ));
}
