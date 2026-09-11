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
 * Unit tests for combat_engine.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tests every row of the combat resolution table in SCOPE.md 4.5, including ties and
 * zero-stat edge cases.
 *
 * @covers \mod_playercards\local\combat_engine
 */
final class combat_engine_test extends \basic_testcase {
    /**
     * Direct attack deals damage equal to the attacker's ATK, unconditionally.
     *
     * @return void
     */
    public function test_direct_attack(): void {
        $this->assertSame(900, combat_engine::resolve_direct_attack(900));
        $this->assertSame(0, combat_engine::resolve_direct_attack(0));
    }

    /**
     * Offensive vs. Offensive, attacker's ATK is higher: defender destroyed, defender
     * takes damage equal to the ATK difference.
     *
     * @return void
     */
    public function test_offensive_vs_offensive_attacker_wins(): void {
        $result = combat_engine::resolve_combat(900, 600, 300, true);

        $this->assertFalse($result['attackerdestroyed']);
        $this->assertTrue($result['defenderdestroyed']);
        $this->assertSame(0, $result['attackerdamage']);
        $this->assertSame(300, $result['defenderdamage']);
    }

    /**
     * Offensive vs. Offensive, attacker's ATK is lower: attacker destroyed, attacker
     * takes damage equal to the ATK difference.
     *
     * @return void
     */
    public function test_offensive_vs_offensive_attacker_loses(): void {
        $result = combat_engine::resolve_combat(600, 900, 300, true);

        $this->assertTrue($result['attackerdestroyed']);
        $this->assertFalse($result['defenderdestroyed']);
        $this->assertSame(300, $result['attackerdamage']);
        $this->assertSame(0, $result['defenderdamage']);
    }

    /**
     * Offensive vs. Offensive, tied ATK: both destroyed, no life point damage either
     * side.
     *
     * @return void
     */
    public function test_offensive_vs_offensive_tie(): void {
        $result = combat_engine::resolve_combat(700, 700, 300, true);

        $this->assertTrue($result['attackerdestroyed']);
        $this->assertTrue($result['defenderdestroyed']);
        $this->assertSame(0, $result['attackerdamage']);
        $this->assertSame(0, $result['defenderdamage']);
    }

    /**
     * Offensive attacker vs. Defensive defender, attacker's ATK beats defender's DEF:
     * defender destroyed, no life point damage to either side.
     *
     * @return void
     */
    public function test_offensive_vs_defensive_attacker_wins(): void {
        $result = combat_engine::resolve_combat(900, 200, 600, false);

        $this->assertFalse($result['attackerdestroyed']);
        $this->assertTrue($result['defenderdestroyed']);
        $this->assertSame(0, $result['attackerdamage']);
        $this->assertSame(0, $result['defenderdamage']);
    }

    /**
     * Offensive attacker vs. Defensive defender, attacker's ATK is lower than the
     * defender's DEF: defender survives, attacker takes damage equal to the difference.
     *
     * @return void
     */
    public function test_offensive_vs_defensive_defender_survives(): void {
        $result = combat_engine::resolve_combat(400, 200, 900, false);

        $this->assertFalse($result['attackerdestroyed']);
        $this->assertFalse($result['defenderdestroyed']);
        $this->assertSame(500, $result['attackerdamage']);
        $this->assertSame(0, $result['defenderdamage']);
    }

    /**
     * Offensive attacker vs. Defensive defender, ATK equals DEF: no destruction, no
     * damage.
     *
     * @return void
     */
    public function test_offensive_vs_defensive_tie(): void {
        $result = combat_engine::resolve_combat(600, 200, 600, false);

        $this->assertFalse($result['attackerdestroyed']);
        $this->assertFalse($result['defenderdestroyed']);
        $this->assertSame(0, $result['attackerdamage']);
        $this->assertSame(0, $result['defenderdamage']);
    }

    /**
     * Zero-stat edge cases do not break the comparisons.
     *
     * @return void
     */
    public function test_zero_stat_edge_cases(): void {
        // Attacker with 0 ATK against a defensive 0 DEF Guardian: tie, no destruction.
        $result = combat_engine::resolve_combat(0, 500, 0, false);
        $this->assertFalse($result['attackerdestroyed']);
        $this->assertFalse($result['defenderdestroyed']);

        // Attacker with 0 ATK against an offensive Guardian with any positive ATK loses.
        $result = combat_engine::resolve_combat(0, 400, 0, true);
        $this->assertTrue($result['attackerdestroyed']);
        $this->assertSame(400, $result['attackerdamage']);
    }

    /**
     * The Guardian power pool rule: ATK + DEF must equal 400 times the level.
     *
     * @return void
     */
    public function test_is_valid_power_pool(): void {
        $this->assertTrue(combat_engine::is_valid_power_pool(1, 400, 0));
        $this->assertTrue(combat_engine::is_valid_power_pool(3, 600, 600));
        $this->assertTrue(combat_engine::is_valid_power_pool(5, 1150, 850));
        $this->assertFalse(combat_engine::is_valid_power_pool(5, 1150, 800));
        $this->assertFalse(combat_engine::is_valid_power_pool(2, 0, 0));
    }
}
