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
 * Form to create or edit a Lore card.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\form;

use moodleform;

/**
 * Create/edit form for a single playercards_lore row (SCOPE.md 5, 6).
 *
 * The category picker for Quiz/Info content (own pool label or real question bank
 * category id) is a plain text field for now — a proper picker UI arrives alongside
 * classes/local/lore_ai_generator.php in Fase 4 (SCOPE.md 16).
 */
class edit_lore_form extends moodleform {
    #[\Override]
    public function definition(): void {
        $mform = $this->_form;
        $subtypes = $this->_customdata['subtypes'];

        // Named "loreid", not "id" — the latter collides with manage.php's own
        // required_param('id') (the course module id) on POST, since Moodle's param
        // lookup checks $_POST before $_GET.
        $mform->addElement('hidden', 'loreid');
        $mform->setType('loreid', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('lorename', 'mod_playercards'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('select', 'subtype', get_string('lore', 'mod_playercards'), $subtypes);
        $mform->setType('subtype', PARAM_ALPHA);

        $mform->addElement('textarea', 'content', get_string('lorecontent', 'mod_playercards'), ['rows' => 4]);
        $mform->setType('content', PARAM_TEXT);
        $mform->addRule('content', null, 'required', null, 'client');
        $mform->addHelpButton('content', 'lorecontent', 'mod_playercards');

        $mform->addElement('text', 'effecttype', get_string('loreeffecttype', 'mod_playercards'));
        $mform->setType('effecttype', PARAM_ALPHANUMEXT);
        $mform->addRule('effecttype', null, 'required', null, 'client');
        $mform->addHelpButton('effecttype', 'loreeffecttype', 'mod_playercards');

        $mform->addElement('text', 'effectvalue', get_string('loreeffectvalue', 'mod_playercards'), ['size' => 5]);
        $mform->setType('effectvalue', PARAM_INT);
        $mform->setDefault('effectvalue', 0);

        $mform->addElement('text', 'maxcopies', get_string('loremaxcopies', 'mod_playercards'), ['size' => 5]);
        $mform->setType('maxcopies', PARAM_INT);
        $mform->setDefault('maxcopies', 3);

        $mform->addElement(
            'select',
            'difficulty',
            get_string('loredifficulty', 'mod_playercards'),
            [
                'easy' => get_string('difficulty_easy', 'mod_playercards'),
                'medium' => get_string('difficulty_medium', 'mod_playercards'),
                'hard' => get_string('difficulty_hard', 'mod_playercards'),
            ]
        );
        $mform->setType('difficulty', PARAM_ALPHA);
        $mform->hideIf('difficulty', 'subtype', 'neq', 'quiz');

        $mform->addElement(
            'select',
            'questionsource',
            get_string('lorequestionsource', 'mod_playercards'),
            [
                'own' => get_string('questionsource_own', 'mod_playercards'),
                'bank' => get_string('questionsource_bank', 'mod_playercards'),
            ]
        );
        $mform->setType('questionsource', PARAM_ALPHA);
        $mform->hideIf('questionsource', 'subtype', 'eq', 'trap');

        $mform->addElement('text', 'questioncategory', get_string('lorequestioncategory', 'mod_playercards'));
        $mform->setType('questioncategory', PARAM_TEXT);
        $mform->hideIf('questioncategory', 'subtype', 'eq', 'trap');
        $mform->addHelpButton('questioncategory', 'lorequestioncategory', 'mod_playercards');

        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if ($data['subtype'] === 'quiz' && empty($data['difficulty'])) {
            $errors['difficulty'] = get_string('error_required', 'mod_playercards');
        }

        if ($data['subtype'] !== 'trap' && empty(trim($data['questioncategory']))) {
            $errors['questioncategory'] = get_string('error_required', 'mod_playercards');
        }

        if ($data['effecttype'] === 'enable_promotion' && $data['subtype'] === 'trap') {
            $errors['effecttype'] = get_string('error_promotiontrap', 'mod_playercards');
        }

        return $errors;
    }
}
