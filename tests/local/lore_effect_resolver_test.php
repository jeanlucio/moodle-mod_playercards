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
 * Unit tests for lore_effect_resolver.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tests every known effecttype's fixed target convention (SCOPE.md 4.6), in isolation
 * from any database or session state.
 *
 * @covers \mod_playercards\local\lore_effect_resolver
 */
final class lore_effect_resolver_test extends \basic_testcase {
    /**
     * Builds a minimal state array with one own Guardian and one enemy Guardian in play,
     * enough for every effecttype's target convention to be exercised.
     *
     * @return array
     */
    private function base_state(): array {
        return [
            'lifepoints' => ['human' => 10000, 'ai' => 10000],
            'humanfield' => [
                ['uid' => 'h1', 'cardtype' => 'guardian', 'cardid' => 1, 'atkbonus' => 0, 'defbonus' => 0],
                null, null, null, null,
            ],
            'aifield' => [
                ['uid' => 'a1', 'cardtype' => 'guardian', 'cardid' => 2, 'atkbonus' => 0, 'defbonus' => 0],
                null, null, null, null,
            ],
        ];
    }

    /**
     * atk_buff/def_buff always target one of the activator's own Guardians, adding to
     * its permanent bonus.
     *
     * @return void
     */
    public function test_atk_and_def_buff_target_own_guardian(): void {
        $state = lore_effect_resolver::apply($this->base_state(), 'human', 'atk_buff', 300, 0);
        $this->assertSame(300, $state['humanfield'][0]['atkbonus']);
        $this->assertSame(0, $state['humanfield'][0]['defbonus']);

        $state = lore_effect_resolver::apply($state, 'human', 'def_buff', 200, 0);
        $this->assertSame(300, $state['humanfield'][0]['atkbonus']);
        $this->assertSame(200, $state['humanfield'][0]['defbonus']);
    }

    /**
     * destroy always removes an enemy Guardian, regardless of its own value.
     *
     * @return void
     */
    public function test_destroy_removes_enemy_guardian(): void {
        $state = lore_effect_resolver::apply($this->base_state(), 'human', 'destroy', 0, 0);
        $this->assertNull($state['aifield'][0]);
    }

    /**
     * lp_heal always benefits the activator's own life points.
     *
     * @return void
     */
    public function test_lp_heal_targets_own_player(): void {
        $state = lore_effect_resolver::apply($this->base_state(), 'human', 'lp_heal', 400, null);
        $this->assertSame(10400, $state['lifepoints']['human']);
        $this->assertSame(10000, $state['lifepoints']['ai']);
    }

    /**
     * lp_damage always hurts the opponent's life points.
     *
     * @return void
     */
    public function test_lp_damage_targets_enemy_player(): void {
        $state = lore_effect_resolver::apply($this->base_state(), 'human', 'lp_damage', 400, null);
        $this->assertSame(10000, $state['lifepoints']['human']);
        $this->assertSame(9600, $state['lifepoints']['ai']);
    }

    /**
     * enable_promotion sets no board effect at all — only a pending Class Promotion
     * authorization for whoever activated it (SCOPE.md 4.3).
     *
     * @return void
     */
    public function test_enable_promotion_sets_pending_promotion(): void {
        $state = lore_effect_resolver::apply($this->base_state(), 'human', 'enable_promotion', 500, null);
        $this->assertSame(['bonus' => 500], $state['pendingpromotion']);
        $this->assertSame(10000, $state['lifepoints']['human']);
        $this->assertNotNull($state['humanfield'][0]);
        $this->assertNotNull($state['aifield'][0]);
    }

    /**
     * An unknown effecttype is rejected rather than silently doing nothing.
     *
     * @return void
     */
    public function test_unknown_effecttype_throws(): void {
        $this->expectException(\moodle_exception::class);
        lore_effect_resolver::apply($this->base_state(), 'human', 'not_a_real_effect', 0, null);
    }

    /**
     * A Guardian-targeting effect with an empty/invalid target slot is rejected.
     *
     * @return void
     */
    public function test_invalid_target_slot_throws(): void {
        $this->expectException(\moodle_exception::class);
        lore_effect_resolver::apply($this->base_state(), 'human', 'atk_buff', 300, 4);
    }
}
