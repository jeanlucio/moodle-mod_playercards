<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for the custom completion rule.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\completion;

use advanced_testcase;

/**
 * Tests for \mod_playercards\completion\custom_completion.
 *
 * @covers \mod_playercards\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * The display order places the custom rule after the grade rules.
     *
     * @return void
     */
    public function test_get_sort_order_lists_every_standard_condition_first(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playercards', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionwinsenabled' => 1,
            'completionwins' => 1,
        ]);
        $completion = new custom_completion(get_fast_modinfo($course)->get_cm($cm->cmid), 0);

        $this->assertSame(
            ['completionview', 'completionusegrade', 'completionpassgrade', 'completionwins'],
            $completion->get_sort_order()
        );
    }

    /**
     * The completion details are built when the pass-grade condition is combined with the custom rule.
     *
     * The core sorts the details with get_sort_order() and throws a coding_exception for any condition the list
     * omits, which takes down the course page and the activity header for everyone. Driving the real details
     * object is the only way to catch a missing key: the sort order alone looks right either way.
     *
     * @return void
     */
    public function test_completion_details_are_built_with_pass_grade_and_the_custom_rule(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playercards', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionwinsenabled' => 1,
            'completionwins' => 1,
        ]);
        $DB->set_field('course_modules', 'completionpassgrade', 1, ['id' => $cm->cmid]);
        $DB->set_field('course_modules', 'completiongradeitemnumber', 0, ['id' => $cm->cmid]);
        rebuild_course_cache($course->id, true);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $cminfo = get_fast_modinfo($course, $user->id)->get_cm($cm->cmid);
        $details = (new \core_completion\cm_completion_details(
            new \completion_info($course),
            $cminfo,
            $user->id
        ))->get_details();

        $this->assertSame(['completionusegrade', 'completionpassgrade', 'completionwins'], array_keys($details));
    }
}
