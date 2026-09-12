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
 * Samples Info/Quiz content for a Lore card activation.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Draws a random item from a Lore card's configured category at activation time
 * (SCOPE.md 4.6) — never a fixed text/question stored on the card itself.
 *
 * Only the 'own' pool ({playercards_questions}) is implemented here. A card configured
 * with questionsource = 'bank' throws a clear error instead of silently sourcing nothing:
 * real Moodle question bank integration is planned for Fase 4's dedicated
 * question_fetcher (SCOPE.md 16), not this etapa.
 */
class lore_content_sampler {
    /**
     * Samples an Info card's revealed text: a random approved 'description'-type item
     * from the card's own category.
     *
     * @param \stdClass $lorecard Lore card record (playercards_lore).
     * @return string The revealed text.
     */
    public static function sample_info(\stdClass $lorecard): string {
        $row = self::sample_row($lorecard, 'description');
        return format_text($row->questiontext, FORMAT_PLAIN);
    }

    /**
     * Samples a Quiz card's question: a random approved multichoice/truefalse item from
     * the card's own category.
     *
     * @param \stdClass $lorecard Lore card record (playercards_lore).
     * @return array ['questiontext' => string, 'options' => string[], 'correctindex' => int].
     */
    public static function sample_quiz(\stdClass $lorecard): array {
        global $DB;

        if ($lorecard->questionsource !== 'own') {
            throw new \moodle_exception('error_banksourcenotready', 'mod_playercards');
        }

        $rows = $DB->get_records_select(
            'playercards_questions',
            'playercardsid = :pid AND category = :category AND qtype IN (\'multichoice\', \'truefalse\') AND approved = 1',
            ['pid' => $lorecard->playercardsid, 'category' => $lorecard->questioncategory]
        );
        if ($rows === []) {
            throw new \moodle_exception('error_nocontentavailable', 'mod_playercards');
        }

        $values = array_values($rows);
        $row = $values[array_rand($values)];
        $answers = json_decode((string) $row->answers, true) ?? [];

        $options = [];
        $correctindex = 0;
        foreach ($answers as $index => $answer) {
            $options[] = $answer['text'];
            if (!empty($answer['correct'])) {
                $correctindex = $index;
            }
        }

        return [
            'questiontext' => format_text($row->questiontext, FORMAT_PLAIN),
            'options' => $options,
            'correctindex' => $correctindex,
        ];
    }

    /**
     * Samples one approved row from a card's own category, restricted to a qtype.
     *
     * @param \stdClass $lorecard Lore card record.
     * @param string $qtype Required question type.
     * @return \stdClass
     */
    private static function sample_row(\stdClass $lorecard, string $qtype): \stdClass {
        global $DB;

        if ($lorecard->questionsource !== 'own') {
            throw new \moodle_exception('error_banksourcenotready', 'mod_playercards');
        }

        $rows = $DB->get_records('playercards_questions', [
            'playercardsid' => $lorecard->playercardsid,
            'category' => $lorecard->questioncategory,
            'qtype' => $qtype,
            'approved' => 1,
        ]);
        if ($rows === []) {
            throw new \moodle_exception('error_nocontentavailable', 'mod_playercards');
        }

        $values = array_values($rows);
        return $values[array_rand($values)];
    }
}
