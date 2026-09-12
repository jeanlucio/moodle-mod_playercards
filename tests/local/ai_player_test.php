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
 * Unit tests for ai_player.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tests the AI's minimal muster/attack turn logic.
 *
 * @covers \mod_playercards\local\ai_player
 * @covers \mod_playercards\local\card_presenter
 * @covers \mod_playercards\local\combat_engine
 */
final class ai_player_test extends \advanced_testcase {
    /**
     * Inserts a Guardian card row.
     *
     * @param int $level Guardian level.
     * @param int $atk Attack points.
     * @param int $def Defence points.
     * @return int
     */
    private function insert_guardian(int $level, int $atk, int $def = 0): int {
        global $DB;

        return $DB->insert_record('playercards_guardians', (object) [
            'level' => $level,
            'atk' => $atk,
            'def' => $def,
            'name' => "Guardian L{$level}",
            'description' => null,
            'imagefile' => null,
            'maxcopies' => 3,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Builds a bare match state with only the fields ai_player itself touches.
     *
     * @return array
     */
    private function base_state(): array {
        return [
            'lifepoints' => ['human' => 10000, 'ai' => 10000],
            'aihand' => [],
            'aifield' => array_fill(0, 5, null),
            'humanfield' => array_fill(0, 5, null),
        ];
    }

    /**
     * play_turn() musters the first level 1-3 Guardian in hand into the first empty
     * field slot, marking it summoning-sick so it cannot attack this same turn.
     *
     * @return void
     */
    public function test_play_turn_musters_free_level_guardian(): void {
        $this->resetAfterTest(true);

        $guardianid = $this->insert_guardian(2, 800);
        $state = $this->base_state();
        $state['aihand'][] = ['uid' => 'a1', 'cardtype' => 'guardian', 'cardid' => $guardianid];

        $result = ai_player::play_turn($state);

        $this->assertNotNull($result['aifield'][0]);
        $this->assertSame($guardianid, $result['aifield'][0]['cardid']);
        $this->assertTrue($result['aifield'][0]['sick']);
        $this->assertSame('attack', $result['aifield'][0]['posture']);
        $this->assertNotContains('a1', array_column($result['aihand'], 'uid'));
    }

    /**
     * A level 4-5 Guardian in hand is never mustered for free — the AI has no
     * sacrificial-muster logic in V1 (see ai_player's own class docblock).
     *
     * @return void
     */
    public function test_play_turn_never_musters_high_level_guardian(): void {
        $this->resetAfterTest(true);

        $guardianid = $this->insert_guardian(4, 1600);
        $state = $this->base_state();
        $state['aihand'][] = ['uid' => 'a1', 'cardtype' => 'guardian', 'cardid' => $guardianid];

        $result = ai_player::play_turn($state);

        $this->assertNull($result['aifield'][0]);
        $this->assertContains('a1', array_column($result['aihand'], 'uid'));
    }

    /**
     * With no eligible Guardian in play, the AI attacks the human's life points
     * directly for its full ATK.
     *
     * @return void
     */
    public function test_play_turn_attacks_directly_when_human_field_empty(): void {
        $this->resetAfterTest(true);

        $guardianid = $this->insert_guardian(1, 400);
        $state = $this->base_state();
        $state['aifield'][0] = [
            'uid' => 'a1', 'cardtype' => 'guardian', 'cardid' => $guardianid,
            'posture' => 'attack', 'sick' => false, 'attackedthisturn' => false,
        ];

        $result = ai_player::play_turn($state);

        $this->assertSame(10000 - 400, $result['lifepoints']['human']);
        $this->assertTrue($result['aifield'][0]['attackedthisturn']);
    }

    /**
     * With a human Guardian in play, the AI attacks it instead of the player directly,
     * resolving combat the same way declare_attack() does.
     *
     * @return void
     */
    public function test_play_turn_attacks_a_defender_when_human_field_occupied(): void {
        $this->resetAfterTest(true);

        $attackerid = $this->insert_guardian(4, 1600);
        $defenderid = $this->insert_guardian(1, 400);
        $state = $this->base_state();
        $state['aifield'][0] = [
            'uid' => 'a1', 'cardtype' => 'guardian', 'cardid' => $attackerid,
            'posture' => 'attack', 'sick' => false, 'attackedthisturn' => false,
        ];
        $state['humanfield'][0] = [
            'uid' => 'h1', 'cardtype' => 'guardian', 'cardid' => $defenderid,
            'posture' => 'attack', 'sick' => false, 'attackedthisturn' => false,
        ];

        $result = ai_player::play_turn($state);

        $this->assertNull($result['humanfield'][0]);
        $this->assertNotNull($result['aifield'][0]);
        $this->assertSame(10000, $result['lifepoints']['ai']);
        $this->assertSame(10000 - (1600 - 400), $result['lifepoints']['human']);
    }

    /**
     * A summoning-sick Guardian, one that already attacked this turn, and one in
     * Defensive posture are all skipped by the AI's attack step.
     *
     * @return void
     */
    public function test_play_turn_skips_ineligible_attackers(): void {
        $this->resetAfterTest(true);

        $sickid = $this->insert_guardian(1, 400);
        $spentid = $this->insert_guardian(1, 400);
        $defenseid = $this->insert_guardian(1, 400);
        $state = $this->base_state();
        $state['aifield'][0] = [
            'uid' => 'sick', 'cardtype' => 'guardian', 'cardid' => $sickid,
            'posture' => 'attack', 'sick' => true, 'attackedthisturn' => false,
        ];
        $state['aifield'][1] = [
            'uid' => 'spent', 'cardtype' => 'guardian', 'cardid' => $spentid,
            'posture' => 'attack', 'sick' => false, 'attackedthisturn' => true,
        ];
        $state['aifield'][2] = [
            'uid' => 'defense', 'cardtype' => 'guardian', 'cardid' => $defenseid,
            'posture' => 'defense', 'sick' => false, 'attackedthisturn' => false,
        ];

        $result = ai_player::play_turn($state);

        $this->assertSame(10000, $result['lifepoints']['human']);
    }
}
