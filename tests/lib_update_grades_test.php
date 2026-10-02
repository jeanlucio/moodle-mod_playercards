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
 * Tests for playercards_update_grades() and the recompute triggered by playercards_update_instance().
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards;

/**
 * Tests for playercards_update_grades() and the recompute triggered by playercards_update_instance().
 *
 * @covers ::playercards_update_grades
 * @covers ::playercards_update_instance
 */
final class lib_update_grades_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playercards/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Inserts finished matches for a student: score and the hour (after $base) each one
     * finished. A match row is only ever written when the match ends, so its timecreated is
     * the finish time. The two 90s tie for the highest score on purpose.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid Student id.
     * @param int $base Base timestamp.
     * @return array Match records keyed by their hour offset.
     */
    private function create_matches(\stdClass $instance, int $userid, int $base): array {
        global $DB;

        $matches = [];
        foreach ([1 => 70.0, 2 => 90.0, 3 => 90.0, 4 => 50.0] as $hour => $score) {
            $record = (object) [
                'playercardsid' => $instance->id,
                'userid' => $userid,
                'deckid' => 0,
                'aidifficulty' => 'normal',
                'result' => $score > 0 ? 'win' : 'loss',
                'lpremaining' => 0,
                'turnsplayed' => 1,
                'score' => $score,
                'timecreated' => $base + $hour * HOURSECS,
            ];
            $record->id = $DB->insert_record('playercards_attempts', $record);
            $matches[$hour] = $record;
        }

        return $matches;
    }

    /**
     * Returns the student's grade as the gradebook reports it.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid Student id.
     * @return \stdClass
     */
    private function get_grade(\stdClass $instance, int $userid): \stdClass {
        $grades = grade_get_grades($instance->course, 'mod', 'playercards', $instance->id, [$userid]);

        return $grades->items[0]->grades[$userid];
    }

    /**
     * Data provider for test_update_grades_reports_datesubmitted().
     *
     * @return array
     */
    public static function datesubmitted_provider(): array {
        global $CFG;
        // Providers run before setUp(), so the PLAYERCARDS_GRADE_* constants are not loaded yet.
        require_once($CFG->dirroot . '/mod/playercards/lib.php');
        return [
            'highest picks the earliest of the tied best matches' => [PLAYERCARDS_GRADE_HIGHEST, 2],
            'first picks the first match' => [PLAYERCARDS_GRADE_FIRST, 1],
            'last picks the last match' => [PLAYERCARDS_GRADE_LAST, 4],
            'average depends on every match, so the last one' => [PLAYERCARDS_GRADE_AVERAGE, 4],
        ];
    }

    /**
     * The grade sent to the gradebook carries when the student finished the match that
     * produced it, not just when the grade changed: gradebook consumers (e.g. late-penalty
     * plugins) read it as the submission time.
     *
     * @dataProvider datesubmitted_provider
     * @param int $grademethod PLAYERCARDS_GRADE_* constant.
     * @param int $expectedhour Hour offset of the match expected as the submission.
     * @return void
     */
    public function test_update_grades_reports_datesubmitted(int $grademethod, int $expectedhour): void {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', [
            'course' => $course->id,
            'grade' => 100,
            'grademethod' => $grademethod,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $base = 1700000000;
        $this->create_matches($instance, (int) $user->id, $base);

        playercards_update_grades($instance, (int) $user->id);

        $this->assertEquals($base + $expectedhour * HOURSECS, $this->get_grade($instance, (int) $user->id)->datesubmitted);
    }

    /**
     * Deleting a match that does not produce the grade must not move datesubmitted.
     *
     * @return void
     */
    public function test_update_grades_datesubmitted_survives_unrelated_deletion(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', [
            'course' => $course->id,
            'grade' => 100,
            'grademethod' => PLAYERCARDS_GRADE_HIGHEST,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $base = 1700000000;
        $matches = $this->create_matches($instance, (int) $user->id, $base);
        playercards_update_grades($instance, (int) $user->id);

        $DB->delete_records('playercards_attempts', ['id' => $matches[4]->id]);
        playercards_update_grades($instance, (int) $user->id);

        $grade = $this->get_grade($instance, (int) $user->id);
        $this->assertSame(90.0, (float) $grade->grade);
        $this->assertEquals($base + 2 * HOURSECS, $grade->datesubmitted);
    }

    /**
     * Changing the grading method recomputes the grades already in the gradebook, as
     * quiz_update_instance() does, instead of leaving them on the old method until each
     * student plays another match.
     *
     * @return void
     */
    public function test_update_instance_recomputes_grades_when_grademethod_changes(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', [
            'course' => $course->id,
            'grade' => 100,
            'grademethod' => PLAYERCARDS_GRADE_HIGHEST,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $base = 1700000000;
        $this->create_matches($instance, (int) $user->id, $base);
        playercards_update_grades($instance, (int) $user->id);

        // Same shape mod_form posts and update_moduleinfo() hands to update_instance().
        $data = $DB->get_record('playercards', ['id' => $instance->id], '*', MUST_EXIST);
        $data->instance = $instance->id;
        $data->coursemodule = $instance->cmid;
        $data->questionsource_own = 1;
        $data->grademethod = PLAYERCARDS_GRADE_FIRST;
        playercards_update_instance($data);

        $grade = $this->get_grade($instance, (int) $user->id);
        $this->assertSame(70.0, (float) $grade->grade);
        $this->assertEquals($base + HOURSECS, $grade->datesubmitted);
    }
}
