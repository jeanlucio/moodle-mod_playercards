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
 * Deck construction validator for PlayerCards.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Validates deck composition against the construction rules in SCOPE.md 4.7.
 *
 * Works on an already-resolved deck description (card metadata joined in by the caller
 * from playercards_guardians/playercards_lore), not on raw database IDs, so it stays a
 * pure function easy to unit test in isolation — mirrors the same design as
 * combat_engine.
 */
class deck_validator {
    /** @var int Minimum deck size. */
    public const MIN_SIZE = 40;

    /** @var int Maximum deck size. */
    public const MAX_SIZE = 60;

    /** @var int Minimum number of Guardians required per level, 1 to 5. */
    public const MIN_PER_LEVEL = 1;

    /** @var int Minimum number of Info+Quiz Lore cards required, combined. */
    public const MIN_INFO_QUIZ = 6;

    /**
     * Validates a deck. Each entry describes one distinct card included in the deck.
     *
     * @param array $entries Array of associative arrays, each with keys: cardtype
     *  ('guardian'|'lore'), quantity (int), maxcopies (int, the card's own copy limit),
     *  level (int 1-5, required when cardtype is 'guardian'), subtype (string
     *  'info'|'quiz'|'trap', required when cardtype is 'lore').
     * @return string[] Human-readable rule violations; an empty array means the deck is
     *  valid.
     */
    public static function validate(array $entries): array {
        $errors = [];
        $totalsize = 0;
        $levelcounts = array_fill(1, 5, 0);
        $infoquizcount = 0;

        foreach ($entries as $entry) {
            $quantity = (int) $entry['quantity'];
            $totalsize += $quantity;

            if ($quantity > (int) $entry['maxcopies']) {
                $errors[] = get_string('deckerrormaxcopies', 'mod_playercards', $entry['maxcopies']);
            }

            if ($entry['cardtype'] === 'guardian') {
                $levelcounts[(int) $entry['level']] += $quantity;
            } else if ($entry['cardtype'] === 'lore' && in_array($entry['subtype'], ['info', 'quiz'], true)) {
                $infoquizcount += $quantity;
            }
        }

        if ($totalsize < self::MIN_SIZE || $totalsize > self::MAX_SIZE) {
            $errors[] = get_string('deckerrorsize', 'mod_playercards', (object) [
                'min' => self::MIN_SIZE,
                'max' => self::MAX_SIZE,
                'actual' => $totalsize,
            ]);
        }

        foreach ($levelcounts as $level => $count) {
            if ($count < self::MIN_PER_LEVEL) {
                $errors[] = get_string('deckerrorlevelfloor', 'mod_playercards', (object) [
                    'min' => self::MIN_PER_LEVEL,
                    'level' => $level,
                ]);
            }
        }

        if ($infoquizcount < self::MIN_INFO_QUIZ) {
            $errors[] = get_string('deckerrorinfoquizfloor', 'mod_playercards', self::MIN_INFO_QUIZ);
        }

        return $errors;
    }
}
