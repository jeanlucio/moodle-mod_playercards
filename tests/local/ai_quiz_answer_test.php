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
 * Unit tests for ai_quiz_answer.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tests the AI's Quiz-answer probability table and correct-answer LP bonus table.
 *
 * @covers \mod_playercards\local\ai_quiz_answer
 */
final class ai_quiz_answer_test extends \basic_testcase {
    /**
     * Known difficulty x qtype combinations return the exact configured probability.
     *
     * @return void
     */
    public function test_guess_probability_known_combinations(): void {
        $this->assertSame(0.33, ai_quiz_answer::guess_probability('easy', 'multichoice'));
        $this->assertSame(0.5, ai_quiz_answer::guess_probability('easy', 'truefalse'));
        $this->assertSame(0.66, ai_quiz_answer::guess_probability('normal', 'multichoice'));
        $this->assertSame(0.75, ai_quiz_answer::guess_probability('normal', 'truefalse'));
        $this->assertSame(1.0, ai_quiz_answer::guess_probability('hard', 'multichoice'));
        $this->assertSame(1.0, ai_quiz_answer::guess_probability('hard', 'truefalse'));
    }

    /**
     * An unknown difficulty or qtype falls back to the Normal / multichoice cell.
     *
     * @return void
     */
    public function test_guess_probability_falls_back_for_unknown_values(): void {
        $this->assertSame(
            ai_quiz_answer::guess_probability('normal', 'multichoice'),
            ai_quiz_answer::guess_probability('impossible', 'multichoice')
        );
        $this->assertSame(
            ai_quiz_answer::guess_probability('normal', 'multichoice'),
            ai_quiz_answer::guess_probability('normal', 'impossible')
        );
    }

    /**
     * is_correct() is a strict less-than comparison against the probability.
     *
     * @return void
     */
    public function test_is_correct_compares_roll_against_probability(): void {
        $this->assertTrue(ai_quiz_answer::is_correct(0.5, 0.49));
        $this->assertFalse(ai_quiz_answer::is_correct(0.5, 0.5));
        $this->assertFalse(ai_quiz_answer::is_correct(0.5, 0.51));
    }

    /**
     * The correct-answer LP bonus scales with the Lore card's own difficulty field
     * (SCOPE.md 4.6's table) — never the match's AI difficulty.
     *
     * @return void
     */
    public function test_correct_answer_bonus_by_card_difficulty(): void {
        $this->assertSame(300, ai_quiz_answer::correct_answer_bonus('easy'));
        $this->assertSame(500, ai_quiz_answer::correct_answer_bonus('medium'));
        $this->assertSame(800, ai_quiz_answer::correct_answer_bonus('hard'));
        $this->assertSame(300, ai_quiz_answer::correct_answer_bonus('impossible'));
    }
}
