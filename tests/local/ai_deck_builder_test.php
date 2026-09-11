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
 * Unit tests for ai_deck_builder.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tests deck assembly for the AI opponent.
 *
 * @covers \mod_playercards\local\ai_deck_builder
 */
final class ai_deck_builder_test extends \advanced_testcase {
    /**
     * Builds a minimal AI reference deck (guardian half only) fixture, independent of
     * db/install.php's real seed data, so this test does not depend on specific catalog
     * ids or quantities.
     *
     * @param string $difficulty easy | normal | hard.
     * @param int $guardiancopies Total guardian copies to seed for this difficulty.
     * @param int $decksize Declared size of the reference deck.
     * @return int The seeded Guardian card id.
     */
    private function seed_ai_reference_deck(string $difficulty, int $guardiancopies, int $decksize): int {
        global $DB;

        // The db/install.php seed data already ships one reference deck per difficulty
        // (it runs as part of the PHPUnit site install) — clear it so this test's fixture
        // is the only one build_deck() can find, regardless of what install.php seeded.
        $existingdeckids = $DB->get_fieldset_select(
            'playercards_decks',
            'id',
            'playercardsid IS NULL AND aidifficulty = :difficulty',
            ['difficulty' => $difficulty]
        );
        if ($existingdeckids !== []) {
            $DB->delete_records_list('playercards_deck_cards', 'deckid', $existingdeckids);
            $DB->delete_records_list('playercards_decks', 'id', $existingdeckids);
        }

        $guardianid = $DB->insert_record('playercards_guardians', (object) [
            'level' => 1,
            'atk' => 400,
            'def' => 0,
            'name' => 'Test Guardian',
            'description' => null,
            'imagefile' => null,
            'maxcopies' => 3,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $deckid = $DB->insert_record('playercards_decks', (object) [
            'playercardsid' => null,
            'userid' => null,
            'aidifficulty' => $difficulty,
            'name' => 'AI reference deck (' . $difficulty . ')',
            'size' => $decksize,
            'active' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('playercards_deck_cards', (object) [
            'deckid' => $deckid,
            'cardtype' => 'guardian',
            'cardid' => $guardianid,
            'quantity' => $guardiancopies,
        ]);

        return $guardianid;
    }

    /**
     * The Guardian half is expanded into one entry per physical copy, and the Lore half
     * is sampled from the instance's own pool to fill the rest of the declared deck size.
     *
     * @return void
     */
    public function test_build_deck_fills_guardian_and_lore_halves(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);

        $guardianid = $this->seed_ai_reference_deck('normal', 5, 10);

        $loreid = $DB->insert_record('playercards_lore', (object) [
            'playercardsid' => $instance->id,
            'subtype' => 'info',
            'name' => 'Test Lore',
            'content' => 'Content.',
            'effecttype' => 'atk_buff',
            'effectvalue' => 100,
            'maxcopies' => 5,
            'difficulty' => null,
            'questionsource' => 'own',
            'questioncategory' => 'test',
            'createdby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $deck = ai_deck_builder::build_deck((int) $instance->id, 'normal');

        $this->assertCount(10, $deck);

        $guardianentries = array_filter($deck, static fn(array $e): bool => $e['cardtype'] === 'guardian');
        $loreentries = array_filter($deck, static fn(array $e): bool => $e['cardtype'] === 'lore');

        $this->assertCount(5, $guardianentries);
        $this->assertCount(5, $loreentries);
        foreach ($guardianentries as $entry) {
            $this->assertSame($guardianid, $entry['cardid']);
        }
        foreach ($loreentries as $entry) {
            $this->assertSame($loreid, $entry['cardid']);
        }
    }

    /**
     * The Lore half is capped at each card's own maxcopies, and the returned deck is
     * simply smaller than declared when the instance does not have enough Lore authored
     * yet — never an error.
     *
     * @return void
     */
    public function test_build_deck_caps_lore_at_maxcopies_and_degrades_gracefully(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);

        $this->seed_ai_reference_deck('easy', 2, 10);

        $DB->insert_record('playercards_lore', (object) [
            'playercardsid' => $instance->id,
            'subtype' => 'trap',
            'name' => 'Only Lore Card',
            'content' => 'Effect.',
            'effecttype' => 'destroy',
            'effectvalue' => 0,
            'maxcopies' => 2,
            'difficulty' => null,
            'questionsource' => null,
            'questioncategory' => null,
            'createdby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $deck = ai_deck_builder::build_deck((int) $instance->id, 'easy');

        // Wants 8 Lore copies (10 - 2 guardian) but only 2 exist (maxcopies), so the
        // final deck has 4 cards total, not 10 — never an error.
        $this->assertCount(4, $deck);
    }
}
