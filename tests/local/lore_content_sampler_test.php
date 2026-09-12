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
 * Unit tests for lore_content_sampler.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tests sampling Info/Quiz content from a Lore card's own category (SCOPE.md 4.6).
 *
 * @covers \mod_playercards\local\lore_content_sampler
 */
final class lore_content_sampler_test extends \advanced_testcase {
    /**
     * Inserts an approved own-pool question row.
     *
     * @param int $playercardsid Instance id.
     * @param string $category Category label.
     * @param string $qtype description | multichoice | truefalse.
     * @param string $questiontext Question/content text.
     * @param string|null $answers JSON-encoded [{text, correct}, ...], null for description.
     * @return int
     */
    private function insert_question(
        int $playercardsid,
        string $category,
        string $qtype,
        string $questiontext,
        ?string $answers
    ): int {
        global $DB;

        return $DB->insert_record('playercards_questions', (object) [
            'playercardsid' => $playercardsid,
            'category' => $category,
            'qtype' => $qtype,
            'questiontext' => $questiontext,
            'answers' => $answers,
            'approved' => 1,
            'addedby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Builds a minimal Lore card record.
     *
     * @param int $playercardsid Instance id.
     * @param string $category Category label.
     * @param string $questionsource own | bank.
     * @param string $difficulty easy | medium | hard.
     * @return \stdClass
     */
    private function lore_card(
        int $playercardsid,
        string $category,
        string $questionsource = 'own',
        string $difficulty = 'easy'
    ): \stdClass {
        return (object) [
            'playercardsid' => $playercardsid,
            'questioncategory' => $category,
            'questionsource' => $questionsource,
            'difficulty' => $difficulty,
        ];
    }

    /**
     * sample_info() returns an approved description-type item's text.
     *
     * @return void
     */
    public function test_sample_info_returns_approved_description(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $this->insert_question((int) $instance->id, 'geografia', 'description', 'Brasília é a capital do Brasil.', null);

        $content = lore_content_sampler::sample_info($this->lore_card((int) $instance->id, 'geografia'));

        $this->assertSame('Brasília é a capital do Brasil.', $content);
    }

    /**
     * sample_quiz() returns the question text, options in order, and the correct index.
     *
     * @return void
     */
    public function test_sample_quiz_returns_question_and_options(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $answers = json_encode([
            ['text' => 'Rio de Janeiro', 'correct' => false],
            ['text' => 'Brasília', 'correct' => true],
            ['text' => 'São Paulo', 'correct' => false],
        ]);
        $this->insert_question(
            (int) $instance->id,
            'geografia',
            'multichoice',
            'Qual é a capital do Brasil?',
            $answers
        );

        $question = lore_content_sampler::sample_quiz($this->lore_card((int) $instance->id, 'geografia'));

        $this->assertSame('Qual é a capital do Brasil?', $question['questiontext']);
        $this->assertSame(['Rio de Janeiro', 'Brasília', 'São Paulo'], $question['options']);
        $this->assertSame(1, $question['correctindex']);
    }

    /**
     * A card with no approved content in its category is rejected, not silently empty.
     *
     * @return void
     */
    public function test_sample_info_rejects_empty_category(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        lore_content_sampler::sample_info($this->lore_card((int) $instance->id, 'empty-category'));
    }

    /**
     * A card configured to source from the real question bank is rejected — that
     * integration is not built yet (SCOPE.md 16, Fase 4's question_fetcher).
     *
     * @return void
     */
    public function test_sample_info_rejects_bank_source(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        lore_content_sampler::sample_info($this->lore_card((int) $instance->id, 'geografia', 'bank'));
    }
}
