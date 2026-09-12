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
 * AI answer probability for a Quiz Lore card.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Determines whether the AI answers a Quiz card's question correctly, by match
 * difficulty and question type — the same table and convention as
 * mod_playerpuzzle\local\engine\combat::boss_guess_probability() (SCOPE.md 11): a harder
 * match difficulty makes the AI a *better* opponent (answers correctly more often),
 * denying the student's LP-heal bonus and instead suffering the card's own effect
 * (SCOPE.md 4.6).
 *
 * This is a separate axis from the Lore card's own `difficulty` field (easy/medium/hard),
 * which instead sets the LP bonus awarded on a *correct* answer (SCOPE.md 4.6's table) —
 * the two never feed into the same calculation.
 */
class ai_quiz_answer {
    /** @var array<string, array<string, float>> Correct-guess probability by match difficulty x qtype. */
    private const GUESS_PROBABILITIES = [
        'easy' => ['multichoice' => 0.33, 'truefalse' => 0.5],
        'normal' => ['multichoice' => 0.66, 'truefalse' => 0.75],
        'hard' => ['multichoice' => 1.0, 'truefalse' => 1.0],
    ];

    /** @var array<string, int> Life point bonus awarded for a correct answer, by the card's own difficulty (SCOPE.md 4.6). */
    private const CORRECT_ANSWER_BONUS = ['easy' => 300, 'medium' => 500, 'hard' => 800];

    /**
     * Returns the probability the AI answers correctly. Unknown values fall back to the
     * Normal / multichoice cell, never above it.
     *
     * @param string $difficulty easy | normal | hard (the match's AI difficulty).
     * @param string $qtype multichoice | truefalse.
     * @return float A probability in the 0..1 range.
     */
    public static function guess_probability(string $difficulty, string $qtype): float {
        $row = self::GUESS_PROBABILITIES[$difficulty] ?? self::GUESS_PROBABILITIES['normal'];
        return $row[$qtype] ?? $row['multichoice'];
    }

    /**
     * Whether a random roll lands within the correct-guess probability. A pure function
     * kept separate from the actual random draw so it stays trivially unit-testable.
     *
     * @param float $probability Probability from guess_probability().
     * @param float $roll A value in the 0..1 range (e.g. mt_rand()/mt_getrandmax()).
     * @return bool
     */
    public static function is_correct(float $probability, float $roll): bool {
        return $roll < $probability;
    }

    /**
     * Returns the life point bonus for correctly answering a Quiz card, based on the
     * card's own configured difficulty (never the match's AI difficulty).
     *
     * @param string $carddifficulty easy | medium | hard, the Lore card's own difficulty field.
     * @return int
     */
    public static function correct_answer_bonus(string $carddifficulty): int {
        return self::CORRECT_ANSWER_BONUS[$carddifficulty] ?? self::CORRECT_ANSWER_BONUS['easy'];
    }
}
