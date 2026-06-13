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

use mod_coursemail\output\mobile;

/**
 * Tests for the mod_coursemail Mobile app output.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_coursemail\output\mobile
 */
class mobile_test extends \advanced_testcase {
    /**
     * The mobile course view returns a main template that shows the activity name.
     */
    public function test_mobile_course_view(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('coursemail', [
            'course' => $course->id,
            'name' => 'My mailbox',
            'intro' => 'Welcome to the mailbox.',
            'introformat' => FORMAT_HTML,
        ]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $cm = get_coursemodule_from_id('coursemail', $instance->cmid, 0, false, MUST_EXIST);
        $result = mobile::mobile_course_view(['cmid' => $cm->id, 'courseid' => $course->id]);

        $this->assertArrayHasKey('templates', $result);
        $this->assertNotEmpty($result['templates']);
        $this->assertStringContainsString('My mailbox', $result['templates'][0]['html']);
        $this->assertArrayHasKey('intro', $result['otherdata']);
        $this->assertStringContainsString('Welcome to the mailbox', $result['otherdata']['intro']);
    }
}
