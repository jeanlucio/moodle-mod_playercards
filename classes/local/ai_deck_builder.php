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
 * Builds a playable card list for the AI opponent's deck.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Assembles the AI's full deck at match start (SCOPE.md 5, note on AI reference decks):
 * the Guardian half is fixed per difficulty (seeded once in db/install.php), the Lore half
 * is sampled fresh, per match, from the playing instance's own Lore pool — there is no
 * global Lore catalog to draw from instead, since Lore is always teacher-authored content.
 */
class ai_deck_builder {
    /**
     * Builds the AI's deck for one match as a flat list of individual card copies.
     *
     * @param int $playercardsid Activity instance id, used to sample the Lore half.
     * @param string $difficulty easy | normal | hard.
     * @return array List of ['cardtype' => 'guardian'|'lore', 'cardid' => int].
     */
    public static function build_deck(int $playercardsid, string $difficulty): array {
        global $DB;

        $refdeck = $DB->get_record(
            'playercards_decks',
            ['playercardsid' => null, 'aidifficulty' => $difficulty],
            '*',
            MUST_EXIST
        );
        $guardianrows = $DB->get_records(
            'playercards_deck_cards',
            ['deckid' => $refdeck->id, 'cardtype' => 'guardian']
        );

        $entries = [];
        foreach ($guardianrows as $row) {
            for ($i = 0; $i < (int) $row->quantity; $i++) {
                $entries[] = ['cardtype' => 'guardian', 'cardid' => (int) $row->cardid];
            }
        }

        $loreneeded = max(0, (int) $refdeck->size - count($entries));
        if ($loreneeded > 0) {
            $entries = array_merge($entries, self::sample_lore($playercardsid, $loreneeded));
        }

        return $entries;
    }

    /**
     * Samples up to $count Lore card copies from an instance's own pool, each card
     * limited to its own maxcopies — the same cap a student deck must respect (SCOPE.md
     * 4.7). Returns fewer than $count when the instance simply does not have that much
     * Lore authored yet; the AI still plays with a smaller deck rather than failing the
     * match start.
     *
     * @param int $playercardsid Activity instance id.
     * @param int $count Maximum number of Lore card copies to sample.
     * @return array List of ['cardtype' => 'lore', 'cardid' => int].
     */
    private static function sample_lore(int $playercardsid, int $count): array {
        global $DB;

        $lorecards = $DB->get_records('playercards_lore', ['playercardsid' => $playercardsid]);

        $pool = [];
        foreach ($lorecards as $lore) {
            for ($i = 0; $i < (int) $lore->maxcopies; $i++) {
                $pool[] = ['cardtype' => 'lore', 'cardid' => (int) $lore->id];
            }
        }

        shuffle($pool);
        return array_slice($pool, 0, $count);
    }
}
