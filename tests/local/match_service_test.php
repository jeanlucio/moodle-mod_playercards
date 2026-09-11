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
 * Unit tests for match_service.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

use cache;
use cache_store;

/**
 * Tests match start, mulligan, state retrieval, muster, posture and combat.
 *
 * @covers \mod_playercards\local\match_service
 * @covers \mod_playercards\local\ai_deck_builder
 * @covers \mod_playercards\local\card_presenter
 */
final class match_service_test extends \advanced_testcase {
    /**
     * Overwrites session state directly, via the same session cache match_service
     * itself reads/writes — used to set up deterministic field/hand scenarios (specific
     * card levels, postures, sickness) that a real shuffled start_match() cannot
     * guarantee.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param array $state State to store.
     * @return void
     */
    private function inject_state(int $cmid, int $userid, array $state): void {
        cache::make_from_params(cache_store::MODE_SESSION, 'mod_playercards', 'matchstate')
            ->set($cmid . '_' . $userid, $state);
    }
    /**
     * Seeds a full instance fixture: 15 Guardians (3 per level), the 'normal' AI
     * reference deck, and a valid 40-card active deck for the given user.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id to own the active deck.
     * @return int[] The 15 seeded Guardian card ids, 3 per level 1-5 in level order
     *  (indices 0-2 level 1, 3-5 level 2, ..., 12-14 level 5).
     */
    private function seed_playable_fixture(\stdClass $instance, int $userid): array {
        global $DB;

        $guardianids = [];
        for ($level = 1; $level <= 5; $level++) {
            for ($i = 0; $i < 3; $i++) {
                $guardianids[] = $DB->insert_record('playercards_guardians', (object) [
                    'level' => $level,
                    'atk' => 400 * $level,
                    'def' => 0,
                    'name' => "Guardian L{$level}-{$i}",
                    'description' => null,
                    'imagefile' => null,
                    'maxcopies' => 3,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ]);
            }
        }

        // The db/install.php seed data already ships one reference deck per difficulty
        // (it runs as part of the PHPUnit site install) — clear the 'normal' one so this
        // fixture is the only one build_deck() can find here.
        $existingdeckids = $DB->get_fieldset_select(
            'playercards_decks',
            'id',
            'playercardsid IS NULL AND aidifficulty = :difficulty',
            ['difficulty' => 'normal']
        );
        if ($existingdeckids !== []) {
            $DB->delete_records_list('playercards_deck_cards', 'deckid', $existingdeckids);
            $DB->delete_records_list('playercards_decks', 'id', $existingdeckids);
        }

        $refdeckid = $DB->insert_record('playercards_decks', (object) [
            'playercardsid' => null,
            'userid' => null,
            'aidifficulty' => 'normal',
            'name' => 'AI reference deck (normal)',
            'size' => 20,
            'active' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        foreach ($guardianids as $guardianid) {
            $DB->insert_record('playercards_deck_cards', (object) [
                'deckid' => $refdeckid,
                'cardtype' => 'guardian',
                'cardid' => $guardianid,
                'quantity' => 1,
            ]);
        }

        $loreid = $DB->insert_record('playercards_lore', (object) [
            'playercardsid' => $instance->id,
            'subtype' => 'info',
            'name' => 'Test Lore',
            'content' => 'Content.',
            'effecttype' => 'atk_buff',
            'effectvalue' => 100,
            'maxcopies' => 3,
            'difficulty' => null,
            'questionsource' => 'own',
            'questioncategory' => 'test',
            'createdby' => $userid,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $deckid = $DB->insert_record('playercards_decks', (object) [
            'playercardsid' => $instance->id,
            'userid' => $userid,
            'aidifficulty' => null,
            'name' => 'My deck',
            'size' => 20,
            'active' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        foreach ($guardianids as $guardianid) {
            $DB->insert_record('playercards_deck_cards', (object) [
                'deckid' => $deckid,
                'cardtype' => 'guardian',
                'cardid' => $guardianid,
                'quantity' => 1,
            ]);
        }
        $DB->insert_record('playercards_deck_cards', (object) [
            'deckid' => $deckid,
            'cardtype' => 'lore',
            'cardid' => $loreid,
            'quantity' => 5,
        ]);

        return $guardianids;
    }

    /**
     * Starts a match and resolves the mulligan (keeping the hand), reaching turn 1's
     * main phase — the common starting point for every muster/posture/combat test.
     *
     * start_match() decides the coin toss randomly, so activeplayer is forced to
     * 'human' here regardless of the real outcome — every caller of this helper tests a
     * human-only action and needs a deterministic "it's your turn", not a state that
     * would make roughly half of all test runs fail on an unrelated "not your turn"
     * check.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array Match state at the start of turn 1.
     */
    private function reach_main_phase(\stdClass $instance, int $cmid, int $userid): array {
        $started = match_service::start_match($instance, $cmid, $userid, 'normal');
        $state = match_service::mulligan($cmid, $userid, $started['token'], true);
        $state['activeplayer'] = 'human';
        $this->inject_state($cmid, $userid, $state);
        return $state;
    }

    /**
     * start_match() deals a 5-card hand to each side, sets the mulligan phase, and
     * hydrates the human hand with full card metadata while keeping the AI's hand as a
     * count only.
     *
     * @return void
     */
    public function test_start_match_deals_hands_and_sets_mulligan_phase(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);

        $state = match_service::start_match($instance, 42, (int) $student->id, 'normal');

        $this->assertTrue($state['hasmatch']);
        $this->assertSame('mulligan', $state['phase']);
        $this->assertCount(5, $state['humanhand']);
        $this->assertCount(5, $state['aihand']);
        $this->assertSame(10000, $state['lifepoints']['human']);
        $this->assertSame(10000, $state['lifepoints']['ai']);
        $this->assertContains($state['firstplayer'], ['human', 'ai']);

        $exported = match_service::export_state($state);
        $this->assertCount(5, $exported['humanhand']);
        $this->assertArrayHasKey('name', $exported['humanhand'][0]);
        $this->assertSame(5, $exported['aihandcount']);
        $this->assertArrayNotHasKey('aihand', $exported);
        $this->assertArrayNotHasKey('humandeck', $exported);
    }

    /**
     * Starting a match with no active deck is a foreseeable, user-reachable condition
     * (deck building has not happened yet) — a moodle_exception, not a coding_exception.
     *
     * @return void
     */
    public function test_start_match_without_active_deck_throws_moodle_exception(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        match_service::start_match($instance, 42, (int) $student->id, 'normal');
    }

    /**
     * An invalid difficulty value is rejected before any deck is touched.
     *
     * @return void
     */
    public function test_start_match_rejects_invalid_difficulty(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        match_service::start_match($instance, 42, (int) $student->id, 'impossible');
    }

    /**
     * mulligan(keep: true) preserves the opening hand and advances to turn 1; keep:
     * false shuffles it back and draws a fresh hand of the same size.
     *
     * @return void
     */
    public function test_mulligan_keep_and_redraw(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);

        $cmid = 42;
        $userid = (int) $student->id;

        $started = match_service::start_match($instance, $cmid, $userid, 'normal');
        $originaluids = array_column($started['humanhand'], 'uid');

        $kept = match_service::mulligan($cmid, $userid, $started['token'], true);
        $this->assertSame('main', $kept['phase']);
        $this->assertSame(1, $kept['turnnumber']);
        $this->assertSame($originaluids, array_column($kept['humanhand'], 'uid'));

        // Restart to get a fresh mulligan-phase state, then redraw instead of keeping.
        $restarted = match_service::start_match($instance, $cmid, $userid, 'normal');
        $redrawn = match_service::mulligan($cmid, $userid, $restarted['token'], false);
        $this->assertSame('main', $redrawn['phase']);
        $this->assertCount(5, $redrawn['humanhand']);
    }

    /**
     * mulligan() rejects a token that does not match the session's current match — a
     * stale client acting on a match that was since restarted or discarded.
     *
     * @return void
     */
    public function test_mulligan_rejects_stale_token(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);

        match_service::start_match($instance, 42, (int) $student->id, 'normal');

        $this->expectException(\moodle_exception::class);
        match_service::mulligan(42, (int) $student->id, 'not-the-real-token', true);
    }

    /**
     * get_state() returns the default (no match) shape until a match is started, then
     * reflects whatever the last mutation left behind — matching what a page reload
     * must be able to resume from.
     *
     * @return void
     */
    public function test_get_state_reflects_current_match(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);

        $cmid = 42;
        $userid = (int) $student->id;

        $before = match_service::get_state($cmid, $userid, null);
        $this->assertFalse($before['hasmatch']);

        $started = match_service::start_match($instance, $cmid, $userid, 'normal');
        $resumed = match_service::get_state($cmid, $userid, null);
        $this->assertTrue($resumed['hasmatch']);
        $this->assertSame($started['token'], $resumed['token']);
    }

    /**
     * Builds a raw field slot entry, as match_service itself stores it internally.
     *
     * @param string $uid Card uid.
     * @param int $cardid Guardian card id.
     * @param string $posture attack | defense.
     * @param bool $sick Whether it was mustered this turn.
     * @param bool $attackedthisturn Whether it already attacked this turn.
     * @return array
     */
    private function field_entry(
        string $uid,
        int $cardid,
        string $posture,
        bool $sick = false,
        bool $attackedthisturn = false
    ): array {
        return [
            'uid' => $uid,
            'cardtype' => 'guardian',
            'cardid' => $cardid,
            'posture' => $posture,
            'sick' => $sick,
            'attackedthisturn' => $attackedthisturn,
        ];
    }

    /**
     * A level 1-3 Guardian is mustered for free, occupies the chosen empty slot as
     * summoning-sick, is removed from hand, and consumes the turn's one normal muster.
     *
     * @return void
     */
    public function test_muster_guardian_free_for_low_level(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanhand'][] = ['uid' => 'testlvl1', 'cardtype' => 'guardian', 'cardid' => $guardianids[0]];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::muster_guardian($cmid, $userid, $state['token'], 'testlvl1', 2, 'attack');

        $this->assertNotNull($result['humanfield'][2]);
        $this->assertSame($guardianids[0], $result['humanfield'][2]['cardid']);
        $this->assertTrue($result['humanfield'][2]['sick']);
        $this->assertSame('attack', $result['humanfield'][2]['posture']);
        $this->assertTrue($result['musterusedthisturn']);
        $this->assertNotContains('testlvl1', array_column($result['humanhand'], 'uid'));
    }

    /**
     * A level 4-5 Guardian requires sacrificing an own level 1-3 Guardian in play; the
     * sacrifice's slot is freed and the new Guardian occupies the target slot.
     *
     * @return void
     */
    public function test_muster_guardian_sacrificial_for_high_level(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanhand'][] = ['uid' => 'testlvl4', 'cardtype' => 'guardian', 'cardid' => $guardianids[9]];
        $state['humanfield'][0] = $this->field_entry('sacme', $guardianids[0], 'defense');
        $this->inject_state($cmid, $userid, $state);

        // Level 4-5 without a sacrifice is rejected.
        try {
            match_service::muster_guardian($cmid, $userid, $state['token'], 'testlvl4', 1, 'attack');
            $this->fail('Expected a moodle_exception for a missing sacrifice.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_sacrificerequired', $e->errorcode);
        }

        $result = match_service::muster_guardian($cmid, $userid, $state['token'], 'testlvl4', 1, 'attack', 0);

        $this->assertNull($result['humanfield'][0]);
        $this->assertNotNull($result['humanfield'][1]);
        $this->assertSame($guardianids[9], $result['humanfield'][1]['cardid']);
    }

    /**
     * Only one normal/sacrificial muster is allowed per turn.
     *
     * @return void
     */
    public function test_muster_guardian_rejects_second_muster_same_turn(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanhand'][] = ['uid' => 'first', 'cardtype' => 'guardian', 'cardid' => $guardianids[0]];
        $state['humanhand'][] = ['uid' => 'second', 'cardtype' => 'guardian', 'cardid' => $guardianids[1]];
        $this->inject_state($cmid, $userid, $state);

        match_service::muster_guardian($cmid, $userid, $state['token'], 'first', 0, 'attack');

        $this->expectException(\moodle_exception::class);
        match_service::muster_guardian($cmid, $userid, $state['token'], 'second', 1, 'attack');
    }

    /**
     * change_posture() toggles a non-sick Guardian's posture, and rejects a Guardian
     * mustered this same turn.
     *
     * @return void
     */
    public function test_change_posture_toggles_and_blocks_summoning_sickness(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('vet', $guardianids[0], 'attack', false);
        $state['humanfield'][1] = $this->field_entry('rookie', $guardianids[1], 'attack', true);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::change_posture($cmid, $userid, $state['token'], 0);
        $this->assertSame('defense', $result['humanfield'][0]['posture']);
        $this->assertTrue($result['postureusedthisturn']);

        // Only one posture change per turn, even on a different Guardian.
        $this->expectException(\moodle_exception::class);
        match_service::change_posture($cmid, $userid, $result['token'], 1);
    }

    /**
     * change_posture() rejects a Guardian mustered this same turn.
     *
     * @return void
     */
    public function test_change_posture_rejects_summoning_sickness(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('rookie', $guardianids[0], 'attack', true);
        $this->inject_state($cmid, $userid, $state);

        $this->expectException(\moodle_exception::class);
        match_service::change_posture($cmid, $userid, $state['token'], 0);
    }

    /**
     * declare_attack() resolves combat via combat_engine and applies its result to both
     * fields and life points — a stronger attacker destroys the weaker defender and
     * deals the ATK difference as damage (SCOPE.md 4.5).
     *
     * @return void
     */
    public function test_declare_attack_resolves_combat_against_a_defender(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        // Guardian index 11 is a level-4 card (atk = 1600) from seed_playable_fixture();
        // index 0 is a level-1 card (atk = 400) — a clean, predictable ATK gap.
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('attacker', $guardianids[11], 'attack', false);
        $state['aifield'][0] = $this->field_entry('defender', $guardianids[0], 'attack', false);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::declare_attack($cmid, $userid, $state['token'], 0, 0);

        $this->assertNull($result['aifield'][0]);
        $this->assertNotNull($result['humanfield'][0]);
        $this->assertTrue($result['humanfield'][0]['attackedthisturn']);
        $this->assertSame(10000, $result['lifepoints']['human']);
        $this->assertSame(10000 - (1600 - 400), $result['lifepoints']['ai']);
    }

    /**
     * A direct attack is only allowed when the AI's field is entirely empty, and deals
     * damage equal to the attacker's ATK with no comparison (SCOPE.md 4.5).
     *
     * @return void
     */
    public function test_declare_attack_direct_when_ai_field_empty(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('attacker', $guardianids[0], 'attack', false);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::declare_attack($cmid, $userid, $state['token'], 0, null);

        $this->assertSame(10000 - 400, $result['lifepoints']['ai']);
    }

    /**
     * A direct attack is rejected while the AI has any Guardian in play — it must be
     * targeted instead.
     *
     * @return void
     */
    public function test_declare_attack_rejects_direct_when_ai_has_a_guardian(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('attacker', $guardianids[0], 'attack', false);
        $state['aifield'][2] = $this->field_entry('defender', $guardianids[1], 'defense', false);
        $this->inject_state($cmid, $userid, $state);

        $this->expectException(\moodle_exception::class);
        match_service::declare_attack($cmid, $userid, $state['token'], 0, null);
    }

    /**
     * A Guardian cannot attack the same turn it was mustered, nor attack a second time
     * in the same turn.
     *
     * @return void
     */
    public function test_declare_attack_rejects_sickness_and_repeat_attacks(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('sick', $guardianids[0], 'attack', true);
        $state['humanfield'][1] = $this->field_entry('veteran', $guardianids[1], 'attack', false);
        $this->inject_state($cmid, $userid, $state);

        try {
            match_service::declare_attack($cmid, $userid, $state['token'], 0, null);
            $this->fail('Expected a moodle_exception for summoning sickness.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_summoningsickness', $e->errorcode);
        }

        $afterfirst = match_service::declare_attack($cmid, $userid, $state['token'], 1, null);
        $this->assertTrue($afterfirst['humanfield'][1]['attackedthisturn']);

        $this->expectException(\moodle_exception::class);
        match_service::declare_attack($cmid, $userid, $state['token'], 1, null);
    }
}
