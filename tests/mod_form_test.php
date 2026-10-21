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
 * Unit tests for mod_playercards_mod_form's completion rule.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards;

/**
 * Tests for mod_playercards_mod_form.
 *
 * @covers \mod_playercards_mod_form
 */
final class mod_form_test extends \advanced_testcase {
    /** @var \stdClass Course used by every test. */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * The completion rule elements carry the form's suffix.
     *
     * The core shows the form on the "Default activity completion" page with a suffix and removes any
     * custom rule whose name lacks it, which would erase the rule from the defaults of every new activity.
     *
     * @return void
     */
    public function test_completion_rule_elements_carry_the_form_suffix(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/mod/playercards/mod_form.php');

        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('playercards', $instance->id);
        $PAGE->set_course($this->course);
        $form = new \mod_playercards_mod_form((object) [
            'instance' => $instance->id, 'id' => $cm->id, 'course' => $this->course->id,
        ], 0, $cm, $this->course);
        $quickform = (new \ReflectionProperty(\mod_playercards_mod_form::class, '_form'))->getValue($form);

        $form->set_suffix('_playercards');
        $names = $form->add_completion_rules();

        $this->assertSame(['completionwinsgroup_playercards'], $names);
        $this->assertTrue($quickform->elementExists('completionwinsgroup_playercards'));
        $this->assertFalse($quickform->elementExists('completionwinsgroup'));
        $this->assertTrue($form->completion_rule_enabled([
            'completionwinsenabled_playercards' => 1,
            'completionwins_playercards' => 2,
        ]));
        $this->assertFalse($form->completion_rule_enabled(['completionwinsenabled' => 1, 'completionwins' => 2]));
    }
}
