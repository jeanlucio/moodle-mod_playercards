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
 * Tests for the length limits of the lore and question forms.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\form;

/**
 * A value longer than its column must be refused by the form, not by a database error.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_playercards\form\edit_lore_form
 * @covers     \mod_playercards\form\edit_question_form
 */
final class maxlength_test extends \advanced_testcase {
    /**
     * Fields with the length of the column they are stored in.
     *
     * @return array[] Form class, field and column length.
     */
    public static function fields_provider(): array {
        return [
            'lore effect type' => [edit_lore_form::class, 'effecttype', 50],
            'lore question category' => [edit_lore_form::class, 'questioncategory', 100],
            'question category' => [edit_question_form::class, 'category', 100],
        ];
    }

    /**
     * Each field carries a maxlength rule that fits its column.
     *
     * @dataProvider fields_provider
     * @param string $class Form class.
     * @param string $field Field name.
     * @param int $column Length of the column.
     */
    public function test_field_has_a_maxlength_rule_that_fits_its_column(string $class, string $field, int $column): void {
        global $CFG;
        // The form classes rely on the page that uses them (manage.php) having loaded formslib.
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new $class(null, ['subtypes' => ['lore' => 'Lore']]);

        $quickform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        $rules = (new \ReflectionProperty(\HTML_QuickForm::class, '_rules'))->getValue($quickform);
        $limits = [];
        foreach ($rules[$field] ?? [] as $rule) {
            if ($rule['type'] === 'maxlength') {
                $limits[] = (int) $rule['format'];
            }
        }

        $this->assertNotEmpty($limits, "The {$field} field has no maxlength rule.");
        $this->assertLessThanOrEqual($column, min($limits));
    }
}
