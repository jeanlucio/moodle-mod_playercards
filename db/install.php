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
 * Post-installation seed data: the global Guardian catalog and the 3 fixed AI reference
 * decks (SCOPE.md 5, 17).
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seeds the V1 Guardian catalog (ported from the validated standalone prototype, one
 * commitment per level) and the 3 AI reference decks' fixed Guardian half. Each AI deck's
 * Lore half is never stored — it is sampled at match start from the playing instance's own
 * approved Lore pool (SCOPE.md 5, note on AI reference decks).
 *
 * @return void
 */
function xmldb_playercards_install(): void {
    global $DB;

    $now = time();
    $guardians = [
        // Level 1 (400 total).
        ['name' => 'Sentinela Novata', 'level' => 1, 'atk' => 400, 'def' => 0],
        ['name' => 'Guarda de Bronze', 'level' => 1, 'atk' => 200, 'def' => 200],
        ['name' => 'Vigia Ágil', 'level' => 1, 'atk' => 300, 'def' => 100],
        // Level 2 (800 total).
        ['name' => 'Escudeiro de Ferro', 'level' => 2, 'atk' => 400, 'def' => 400],
        ['name' => 'Batedor Veloz', 'level' => 2, 'atk' => 600, 'def' => 200],
        ['name' => 'Muralha Jovem', 'level' => 2, 'atk' => 100, 'def' => 700],
        // Level 3 (1200 total).
        ['name' => 'Cavaleiro Errante', 'level' => 3, 'atk' => 700, 'def' => 500],
        ['name' => 'Guardião de Pedra', 'level' => 3, 'atk' => 200, 'def' => 1000],
        ['name' => 'Lâmina Sombria', 'level' => 3, 'atk' => 900, 'def' => 300],
        // Level 4 (1600 total).
        ['name' => 'Golem de Aço', 'level' => 4, 'atk' => 400, 'def' => 1200],
        ['name' => 'Berserker das Cinzas', 'level' => 4, 'atk' => 1300, 'def' => 300],
        ['name' => 'Protetor Ancião', 'level' => 4, 'atk' => 700, 'def' => 900],
        // Level 5 (2000 total).
        ['name' => 'Titã Guardião', 'level' => 5, 'atk' => 900, 'def' => 1100],
        ['name' => 'Dragão de Obsidiana', 'level' => 5, 'atk' => 1600, 'def' => 400],
        ['name' => 'Colosso Eterno', 'level' => 5, 'atk' => 500, 'def' => 1500],
    ];

    $ids = [];
    foreach ($guardians as $index => $guardian) {
        $ids[$index + 1] = $DB->insert_record('playercards_guardians', (object) [
            'level' => $guardian['level'],
            'atk' => $guardian['atk'],
            'def' => $guardian['def'],
            'name' => $guardian['name'],
            'description' => null,
            'imagefile' => null,
            'maxcopies' => 3,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    // Guardian-only half of each AI reference deck, by 1-indexed catalog position above.
    // Deliberately weak-to-strong across easy/normal/hard (SCOPE.md 17): easy avoids
    // level 4-5 entirely, hard keeps only a handful of low-level cards as sacrifice
    // material for its own Convocação por Sacrifício. Each totals 20 copies, leaving 20
    // Lore slots (deck size 40) to be filled dynamically per match.
    $aidecks = [
        'easy' => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 2],
        'normal' => [
            1 => 2, 2 => 2, 3 => 1, 4 => 2, 5 => 1, 6 => 1, 7 => 2, 8 => 1,
            9 => 1, 10 => 2, 11 => 1, 12 => 1, 13 => 1, 14 => 1, 15 => 1,
        ],
        'hard' => [
            1 => 1, 4 => 1, 5 => 1, 7 => 1, 8 => 1, 9 => 1,
            10 => 3, 11 => 2, 12 => 2, 13 => 3, 14 => 2, 15 => 2,
        ],
    ];

    foreach ($aidecks as $difficulty => $composition) {
        $deckid = $DB->insert_record('playercards_decks', (object) [
            'playercardsid' => null,
            'userid' => null,
            'aidifficulty' => $difficulty,
            'name' => 'AI reference deck (' . $difficulty . ')',
            'size' => 40,
            'active' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        foreach ($composition as $catalogposition => $quantity) {
            $DB->insert_record('playercards_deck_cards', (object) [
                'deckid' => $deckid,
                'cardtype' => 'guardian',
                'cardid' => $ids[$catalogposition],
                'quantity' => $quantity,
            ]);
        }
    }
}
