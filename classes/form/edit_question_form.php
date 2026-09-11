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
 * Form to create or edit an item in the PlayerCards own question pool.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\form;

use moodleform;

/**
 * Create/edit form for a single playercards_questions row (SCOPE.md 5, 6).
 *
 * Answer options are entered one per line for now, the correct one prefixed with "*" —
 * parsed into the {text, correct} JSON pairs the schema expects. A richer per-option
 * widget can replace this later without changing the stored format.
 */
class edit_question_form extends moodleform {
    #[\Override]
    public function definition(): void {
        $mform = $this->_form;

        // Named "questionid", not "id" — the latter collides with manage.php's own
        // required_param('id') (the course module id) on POST, since Moodle's param
        // lookup checks $_POST before $_GET.
        $mform->addElement('hidden', 'questionid');
        $mform->setType('questionid', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('text', 'category', get_string('questioncategorylabel', 'mod_playercards'));
        $mform->setType('category', PARAM_TEXT);
        $mform->addRule('category', null, 'required', null, 'client');
        $mform->addHelpButton('category', 'questioncategorylabel', 'mod_playercards');

        $mform->addElement(
            'select',
            'qtype',
            get_string('questiontype', 'mod_playercards'),
            [
                'multichoice' => get_string('qtype_multichoice', 'mod_playercards'),
                'truefalse' => get_string('qtype_truefalse', 'mod_playercards'),
                'description' => get_string('qtype_description', 'mod_playercards'),
            ]
        );
        $mform->setType('qtype', PARAM_ALPHA);

        $mform->addElement('textarea', 'questiontext', get_string('questiontextlabel', 'mod_playercards'), ['rows' => 3]);
        $mform->setType('questiontext', PARAM_TEXT);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        $mform->addElement('textarea', 'answerlines', get_string('answerlines', 'mod_playercards'), ['rows' => 5]);
        $mform->setType('answerlines', PARAM_TEXT);
        $mform->addHelpButton('answerlines', 'answerlines', 'mod_playercards');
        $mform->hideIf('answerlines', 'qtype', 'eq', 'description');

        $mform->addElement('advcheckbox', 'approved', get_string('questionapproved', 'mod_playercards'));
        $mform->setType('approved', PARAM_INT);
        $mform->setDefault('approved', 0);

        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if ($data['qtype'] !== 'description') {
            $lines = array_filter(array_map('trim', explode("\n", (string) $data['answerlines'])));
            if (count($lines) < 2) {
                $errors['answerlines'] = get_string('error_needtwoanswers', 'mod_playercards');
            } else if (!self::has_exactly_one_correct($lines)) {
                $errors['answerlines'] = get_string('error_needonecorrect', 'mod_playercards');
            }
        }

        return $errors;
    }

    /**
     * Whether exactly one of the given answer lines is marked correct (prefixed "*").
     *
     * @param string[] $lines Trimmed, non-empty answer lines.
     * @return bool
     */
    private static function has_exactly_one_correct(array $lines): bool {
        $correctcount = 0;
        foreach ($lines as $line) {
            if (str_starts_with($line, '*')) {
                $correctcount++;
            }
        }

        return $correctcount === 1;
    }
}
