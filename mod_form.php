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
 * Form definition for mod_playercards.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(__DIR__ . '/lib.php');

/**
 * Activity settings form for PlayerCards.
 */
class mod_playercards_mod_form extends moodleform_mod {
    /**
     * Defines the form elements.
     *
     * @return void
     */
    public function definition(): void {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'matchheader', get_string('matchheader', 'mod_playercards'));
        $mform->setExpanded('matchheader');

        $mform->addElement(
            'select',
            'aidifficultydefault',
            get_string('aidifficultydefault', 'mod_playercards'),
            [
                PLAYERCARDS_DIFFICULTY_EASY   => get_string('difficulty_easy', 'mod_playercards'),
                PLAYERCARDS_DIFFICULTY_NORMAL => get_string('difficulty_normal', 'mod_playercards'),
                PLAYERCARDS_DIFFICULTY_HARD   => get_string('difficulty_hard', 'mod_playercards'),
            ]
        );
        $mform->setType('aidifficultydefault', PARAM_ALPHA);
        $mform->setDefault('aidifficultydefault', PLAYERCARDS_DIFFICULTY_NORMAL);
        $mform->addHelpButton('aidifficultydefault', 'aidifficultydefault', 'mod_playercards');

        $mform->addElement('text', 'quiztimerseconds', get_string('quiztimerseconds', 'mod_playercards'), ['size' => 5]);
        $mform->setType('quiztimerseconds', PARAM_INT);
        $mform->setDefault('quiztimerseconds', 20);
        $mform->addRule('quiztimerseconds', null, 'numeric', null, 'client');
        $mform->addHelpButton('quiztimerseconds', 'quiztimerseconds', 'mod_playercards');

        $mform->addElement('text', 'maxturns', get_string('maxturns', 'mod_playercards'), ['size' => 5]);
        $mform->setType('maxturns', PARAM_INT);
        $mform->setDefault('maxturns', 0);
        $mform->addRule('maxturns', null, 'numeric', null, 'client');
        $mform->addHelpButton('maxturns', 'maxturns', 'mod_playercards');

        $mform->addElement('header', 'contentheader', get_string('contentheader', 'mod_playercards'));
        $mform->setExpanded('contentheader');

        $mform->addElement(
            'advcheckbox',
            'questionsource_own',
            get_string('questionsource_own', 'mod_playercards')
        );
        $mform->setType('questionsource_own', PARAM_INT);
        $mform->setDefault('questionsource_own', 1);

        $mform->addElement(
            'advcheckbox',
            'questionsource_bank',
            get_string('questionsource_bank', 'mod_playercards')
        );
        $mform->setType('questionsource_bank', PARAM_INT);
        $mform->setDefault('questionsource_bank', 0);

        // The real block_playerhud item picker (mirroring mod_playerwords) arrives with
        // hud_service.php in Fase 4 (SCOPE.md 16). Plain quantity/id inputs for now, so a
        // teacher can still wire an existing item id manually if they already know it.
        $mform->addElement('header', 'hudheader', get_string('hudheader', 'mod_playercards'));

        $mform->addElement('text', 'hud_card_cost_item', get_string('hud_card_cost_item', 'mod_playercards'), ['size' => 5]);
        $mform->setType('hud_card_cost_item', PARAM_INT);
        $mform->setDefault('hud_card_cost_item', 0);
        $mform->addHelpButton('hud_card_cost_item', 'hud_card_cost_item', 'mod_playercards');

        $mform->addElement('text', 'hud_card_cost_qty', get_string('hud_card_cost_qty', 'mod_playercards'), ['size' => 5]);
        $mform->setType('hud_card_cost_qty', PARAM_INT);
        $mform->setDefault('hud_card_cost_qty', 0);
        $mform->hideIf('hud_card_cost_qty', 'hud_card_cost_item', 'eq', 0);

        $mform->addElement('text', 'hud_retry_cost_item', get_string('hud_retry_cost_item', 'mod_playercards'), ['size' => 5]);
        $mform->setType('hud_retry_cost_item', PARAM_INT);
        $mform->setDefault('hud_retry_cost_item', 0);
        $mform->addHelpButton('hud_retry_cost_item', 'hud_retry_cost_item', 'mod_playercards');

        $mform->addElement('text', 'hud_retry_cost_qty', get_string('hud_retry_cost_qty', 'mod_playercards'), ['size' => 5]);
        $mform->setType('hud_retry_cost_qty', PARAM_INT);
        $mform->setDefault('hud_retry_cost_qty', 0);
        $mform->hideIf('hud_retry_cost_qty', 'hud_retry_cost_item', 'eq', 0);

        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);

        $mform->addElement(
            'select',
            'grademethod',
            get_string('grademethod', 'mod_playercards'),
            playercards_get_grademethod_options()
        );
        $mform->setType('grademethod', PARAM_INT);
        $mform->setDefault('grademethod', PLAYERCARDS_GRADE_HIGHEST);
        $mform->hideIf('grademethod', 'grade[modgrade_type]', 'eq', 'none');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds custom completion rules to the Moodle completion section.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $group = [];
        $group[] = $mform->createElement('checkbox', 'completionwinsenabled', '', '');
        $group[] = $mform->createElement('text', 'completionwins', '', ['size' => 3]);
        $mform->addGroup(
            $group,
            'completionwinsgroup',
            get_string('completionwinsgroup', 'mod_playercards'),
            [' '],
            false
        );

        $mform->setType('completionwins', PARAM_INT);
        $mform->setDefault('completionwins', 1);
        $mform->disabledIf('completionwins', 'completionwinsenabled', 'notchecked');

        return ['completionwinsgroup'];
    }

    /**
     * Returns whether at least one completion rule is enabled.
     *
     * @param array $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionwinsenabled']) && (int) $data['completionwins'] > 0;
    }

    /**
     * Normalises form data before saving.
     *
     * @param array $defaultvalues Default form values.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);

        if (!empty($defaultvalues['questionsource'])) {
            $source = (int) $defaultvalues['questionsource'];
            $defaultvalues['questionsource_own'] = (int) (($source & PLAYERCARDS_QUESTIONSOURCE_OWN) !== 0);
            $defaultvalues['questionsource_bank'] = (int) (($source & PLAYERCARDS_QUESTIONSOURCE_BANK) !== 0);
        }

        if (!empty($defaultvalues['completionwins'])) {
            $defaultvalues['completionwinsenabled'] = 1;
        }
    }

    /**
     * Custom validation for PlayerCards settings.
     *
     * @param array $data Form data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty($data['questionsource_own']) && empty($data['questionsource_bank'])) {
            $errors['questionsource_own'] = get_string('error_atleastonesource', 'mod_playercards');
        }

        if ((int) $data['quiztimerseconds'] < 5) {
            $errors['quiztimerseconds'] = get_string('error_quiztimerseconds', 'mod_playercards');
        }

        if ((int) $data['maxturns'] < 0) {
            $errors['maxturns'] = get_string('error_maxturns', 'mod_playercards');
        }

        if (!empty($data['hud_card_cost_item']) && (int) $data['hud_card_cost_qty'] < 1) {
            $errors['hud_card_cost_qty'] = get_string('error_hud_cost_qty', 'mod_playercards');
        }

        if (!empty($data['hud_retry_cost_item']) && (int) $data['hud_retry_cost_qty'] < 1) {
            $errors['hud_retry_cost_qty'] = get_string('error_hud_cost_qty', 'mod_playercards');
        }

        if (!empty($data['completionwinsenabled']) && (int) $data['completionwins'] < 1) {
            $errors['completionwinsgroup'] = get_string('error_completionwins', 'mod_playercards');
        }

        return $errors;
    }
}
