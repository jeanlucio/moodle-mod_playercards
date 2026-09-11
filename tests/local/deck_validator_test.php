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
 * Unit tests for deck_validator.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tests the deck construction rules in SCOPE.md 4.7: free size range, per-level Guardian
 * floor, combined Info+Quiz floor, no forced Guardian/Lore ratio, and the per-card copy
 * limit.
 *
 * @covers \mod_playercards\local\deck_validator
 */
final class deck_validator_test extends \basic_testcase {
    /**
     * Builds a deck that satisfies every rule: 40 cards total, 2 distinct Guardians per
     * level at 3 copies each (30 Guardians, well above the 1-per-level floor), 4 distinct
     * Info/Quiz cards at 2 copies each (8, above the floor of 6) plus 2 Trap cards at 1
     * copy each (2) for the remaining Lore slots.
     *
     * @return array
     */
    private function valid_deck_entries(): array {
        $entries = [];

        for ($level = 1; $level <= 5; $level++) {
            for ($card = 0; $card < 2; $card++) {
                $entries[] = ['cardtype' => 'guardian', 'level' => $level, 'quantity' => 3, 'maxcopies' => 3];
            }
        }

        for ($card = 0; $card < 2; $card++) {
            $entries[] = ['cardtype' => 'lore', 'subtype' => 'info', 'quantity' => 2, 'maxcopies' => 3];
            $entries[] = ['cardtype' => 'lore', 'subtype' => 'quiz', 'quantity' => 2, 'maxcopies' => 3];
        }

        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 1, 'maxcopies' => 3];
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 1, 'maxcopies' => 3];

        return $entries;
    }

    /**
     * A deck satisfying every rule produces no errors.
     *
     * @return void
     */
    public function test_valid_deck_passes(): void {
        $this->assertSame([], deck_validator::validate($this->valid_deck_entries()));
    }

    /**
     * A deck below the minimum size is rejected, even when every other rule is
     * satisfied at the floor exactly.
     *
     * @return void
     */
    public function test_deck_below_min_size(): void {
        $entries = [];
        for ($level = 1; $level <= 5; $level++) {
            $entries[] = ['cardtype' => 'guardian', 'level' => $level, 'quantity' => 1, 'maxcopies' => 3];
        }
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'info', 'quantity' => 3, 'maxcopies' => 3];
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'quiz', 'quantity' => 3, 'maxcopies' => 3];

        $errors = deck_validator::validate($entries);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('40', $errors[0]);
    }

    /**
     * A deck above the maximum size is rejected.
     *
     * @return void
     */
    public function test_deck_above_max_size(): void {
        $entries = $this->valid_deck_entries();
        // Add 21 more distinct filler Guardians (1 copy each) to push the total to 61.
        for ($i = 0; $i < 21; $i++) {
            $entries[] = ['cardtype' => 'guardian', 'level' => 1, 'quantity' => 1, 'maxcopies' => 3];
        }

        $errors = deck_validator::validate($entries);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('60', $errors[0]);
    }

    /**
     * A deck missing every Guardian of a specific level is rejected, naming that level.
     *
     * @return void
     */
    public function test_deck_missing_a_level(): void {
        $entries = array_values(array_filter(
            $this->valid_deck_entries(),
            static fn (array $entry): bool => !($entry['cardtype'] === 'guardian' && $entry['level'] === 3)
        ));
        // Backfill the removed 6 Guardian copies with Lore so the deck stays at 40 total,
        // isolating the level-floor violation from a size violation.
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 3, 'maxcopies' => 3];
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 3, 'maxcopies' => 3];

        $errors = deck_validator::validate($entries);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('3', $errors[0]);
    }

    /**
     * A deck below the combined Info+Quiz floor is rejected, even with Lore present via
     * Trap cards only.
     *
     * @return void
     */
    public function test_deck_below_infoquiz_floor(): void {
        $entries = array_values(array_filter(
            $this->valid_deck_entries(),
            static fn (array $entry): bool => $entry['cardtype'] !== 'lore' || $entry['subtype'] === 'trap'
        ));
        // Backfill with more Trap copies so the deck stays at 40 total, isolating the
        // Info+Quiz floor violation from a size violation.
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 2, 'maxcopies' => 3];
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 2, 'maxcopies' => 3];
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 2, 'maxcopies' => 3];
        $entries[] = ['cardtype' => 'lore', 'subtype' => 'trap', 'quantity' => 2, 'maxcopies' => 3];

        $errors = deck_validator::validate($entries);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('6', $errors[0]);
    }

    /**
     * A card included above its own copy limit is rejected.
     *
     * @return void
     */
    public function test_deck_exceeds_maxcopies(): void {
        $entries = $this->valid_deck_entries();
        $entries[0]['quantity'] = 4;
        $entries[0]['maxcopies'] = 3;

        $errors = deck_validator::validate($entries);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('3', $errors[0]);
    }

    /**
     * Multiple simultaneous violations are all reported, not just the first one found.
     *
     * @return void
     */
    public function test_multiple_violations_all_reported(): void {
        $entries = [
            ['cardtype' => 'guardian', 'level' => 1, 'quantity' => 1, 'maxcopies' => 3],
        ];

        $errors = deck_validator::validate($entries);

        // Below min size, missing levels 2-5, below Info+Quiz floor: at least 3 errors.
        $this->assertGreaterThanOrEqual(3, count($errors));
    }
}
