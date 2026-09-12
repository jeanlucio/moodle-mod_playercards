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
 * @covers \mod_playercards\local\ai_player
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
     * Starts a match and resolves the mulligan (keeping the hand), reaching an ordinary,
     * unrestricted main phase — the common starting point for every muster/posture/
     * combat/Lore test.
     *
     * start_match() decides the coin toss randomly, so firstplayer/activeplayer are
     * forced to 'human' here, *before* calling mulligan() — every caller of this helper
     * tests a human-only action and needs a deterministic "it's your turn", not a state
     * that would make roughly half of all test runs fail on an unrelated "not your turn"
     * check. This must happen before mulligan() runs, not after: since mulligan() now
     * processes the AI's entire turn 1 itself whenever the AI is first (see its own
     * docblock), overriding activeplayer only after the call would be too late — the
     * AI's turn would already have mutated life points/turnnumber/etc.
     *
     * turnnumber is forced to 2 (past mulligan()'s own turn 1) for the same reason: the
     * player who goes first has no Battle Phase at all on turn 1 (SCOPE.md 4.9), and this
     * helper exists to test ordinary muster/posture/attack/Lore mechanics, not that
     * specific edge case — see test_declare_attack_rejects_on_turn_1() for it.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array Match state at the start of an ordinary main phase.
     */
    private function reach_main_phase(\stdClass $instance, int $cmid, int $userid): array {
        $started = match_service::start_match($instance, $cmid, $userid, 'normal');
        $started['firstplayer'] = 'human';
        $started['activeplayer'] = 'human';
        $this->inject_state($cmid, $userid, $started);

        $state = match_service::mulligan($cmid, $userid, $instance, $started['token'], true);
        $state['turnnumber'] = 2;
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
        $started['firstplayer'] = 'human';
        $started['activeplayer'] = 'human';
        $this->inject_state($cmid, $userid, $started);

        $kept = match_service::mulligan($cmid, $userid, $instance, $started['token'], true);
        $this->assertSame('main', $kept['phase']);
        $this->assertSame(1, $kept['turnnumber']);
        $this->assertSame($originaluids, array_column($kept['humanhand'], 'uid'));

        // Restart to get a fresh mulligan-phase state, then redraw instead of keeping.
        $restarted = match_service::start_match($instance, $cmid, $userid, 'normal');
        $restarted['firstplayer'] = 'human';
        $restarted['activeplayer'] = 'human';
        $this->inject_state($cmid, $userid, $restarted);

        $redrawn = match_service::mulligan($cmid, $userid, $instance, $restarted['token'], false);
        $this->assertSame('main', $redrawn['phase']);
        $this->assertCount(5, $redrawn['humanhand']);
    }

    /**
     * When the AI wins the coin toss, turn 1 belongs to the AI — nothing else would ever
     * process it (the client only ever calls end_turn() to close the *human's* turn), so
     * mulligan() must play the AI's turn 1 out itself and open the human's turn 2, rather
     * than leaving the match stuck forever on "AI's turn" (the real bug this test guards
     * against — found live: the board showed "Vez da IA" with no way to progress).
     *
     * @return void
     */
    public function test_mulligan_processes_ai_first_turn_when_ai_wins_coin_toss(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $started = match_service::start_match($instance, $cmid, $userid, 'normal');
        $started['firstplayer'] = 'ai';
        $started['activeplayer'] = 'ai';
        $this->inject_state($cmid, $userid, $started);

        $state = match_service::mulligan($cmid, $userid, $instance, $started['token'], true);

        $this->assertFalse($state['finished']);
        $this->assertSame('human', $state['activeplayer']);
        // AI's own turn 1, then the human's turn 2 — matches "whoever moves first skips
        // their own turn-1 draw" applying to the AI here instead of the human.
        $this->assertSame(2, $state['turnnumber']);
        $this->assertFalse($state['musterusedthisturn']);
        $this->assertFalse($state['postureusedthisturn']);
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
        match_service::mulligan(42, (int) $student->id, $instance, 'not-the-real-token', true);
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

        $result = match_service::declare_attack($cmid, $userid, $instance, $state['token'], 0, 0);

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

        $result = match_service::declare_attack($cmid, $userid, $instance, $state['token'], 0, null);

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
        match_service::declare_attack($cmid, $userid, $instance, $state['token'], 0, null);
    }

    /**
     * A Guardian can attack the same turn it was mustered — real Yu-Gi-Oh has no
     * "summoning sickness" restriction on attacking (SCOPE.md 4.2, corrected in v1.15
     * after being modelled on Magic: The Gathering by mistake since v1.0) — but still
     * cannot attack a second time in the same turn.
     *
     * @return void
     */
    public function test_declare_attack_allows_freshly_summoned_guardian_and_rejects_repeat_attacks(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('freshlysummoned', $guardianids[0], 'attack', true);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::declare_attack($cmid, $userid, $instance, $state['token'], 0, null);

        $this->assertTrue($result['humanfield'][0]['attackedthisturn']);
        $this->assertSame(10000 - 400, $result['lifepoints']['ai']);

        $this->expectException(\moodle_exception::class);
        match_service::declare_attack($cmid, $userid, $instance, $result['token'], 0, null);
    }

    /**
     * declare_attack() ends the match immediately when it knocks the AI's life points to
     * 0 or below — this check was missing entirely until v1.17 (the only knockout check
     * that existed lived inside end_turn()'s AI-turn processing), so a human attack that
     * finished the AI off mid-turn used to leave the match running indefinitely instead
     * of declaring a win (a real bug found live: life points went to -1800 with no
     * result ever recorded).
     *
     * @return void
     */
    public function test_declare_attack_finishes_match_on_ai_knockout(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = (int) $instance->cmid;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['lifepoints']['ai'] = 300;
        $state['humanfield'][0] = $this->field_entry('attacker', $guardianids[0], 'attack', false);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::declare_attack($cmid, $userid, $instance, $state['token'], 0, null);

        $this->assertTrue($result['finished']);
        $this->assertSame('win', $result['result']);
        $this->assertLessThanOrEqual(0, $result['lifepoints']['ai']);

        $attempt = $DB->get_record('playercards_attempts', ['playercardsid' => $instance->id, 'userid' => $userid]);
        $this->assertNotFalse($attempt);
        $this->assertSame('win', $attempt->result);
    }

    /**
     * declare_attack() also ends the match on a human knockout — reachable when the
     * attacker loses combat and its own controller takes the ATK-difference recoil
     * damage (SCOPE.md 4.5). Same missing-check bug as the AI-knockout case above.
     *
     * @return void
     */
    public function test_declare_attack_finishes_match_on_human_knockout(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        // Guardian index 0 is level 1 (atk 400); index 11 is level 4 (atk 1600) — a weak
        // attacker losing to a strong defender, recoiling the ATK difference (1200) onto
        // the human.
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = (int) $instance->cmid;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['lifepoints']['human'] = 1000;
        $state['humanfield'][0] = $this->field_entry('weakattacker', $guardianids[0], 'attack', false);
        $state['aifield'][0] = $this->field_entry('strongdefender', $guardianids[11], 'attack', false);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::declare_attack($cmid, $userid, $instance, $state['token'], 0, 0);

        $this->assertTrue($result['finished']);
        $this->assertSame('loss', $result['result']);
        $this->assertLessThanOrEqual(0, $result['lifepoints']['human']);

        $attempt = $DB->get_record('playercards_attempts', ['playercardsid' => $instance->id, 'userid' => $userid]);
        $this->assertNotFalse($attempt);
        $this->assertSame('loss', $attempt->result);
    }

    /**
     * declare_attack() rejects any attack attempt during turn 1 — the player who goes
     * first has no Battle Phase at all on their own very first turn (SCOPE.md 4.9), a
     * real Yu-Gi-Oh rule distinct from summoning sickness (which does not exist). Uses a
     * hand-rolled setup rather than reach_main_phase(), which deliberately forces
     * turnnumber past 1 for every other muster/posture/combat/Lore test.
     *
     * @return void
     */
    public function test_declare_attack_rejects_on_turn_1(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $started = match_service::start_match($instance, $cmid, $userid, 'normal');
        $started['firstplayer'] = 'human';
        $started['activeplayer'] = 'human';
        $this->inject_state($cmid, $userid, $started);

        $state = match_service::mulligan($cmid, $userid, $instance, $started['token'], true);
        $state['humanfield'][0] = $this->field_entry('attacker', $guardianids[0], 'attack', false);
        $this->inject_state($cmid, $userid, $state);

        try {
            match_service::declare_attack($cmid, $userid, $instance, $state['token'], 0, null);
            $this->fail('Expected a moodle_exception for turn 1 having no Battle Phase.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_nobattlephaseturn1', $e->errorcode);
        }
    }

    /**
     * Inserts a Lore card row.
     *
     * @param int $playercardsid Instance id.
     * @param string $subtype info | quiz | trap.
     * @param string $effecttype Effect identifier.
     * @param int $effectvalue Effect magnitude.
     * @param string $category Content category (own pool).
     * @param string|null $difficulty easy | medium | hard, required for quiz.
     * @return int
     */
    private function insert_lore_card(
        int $playercardsid,
        string $subtype,
        string $effecttype,
        int $effectvalue,
        string $category = 'test',
        ?string $difficulty = null
    ): int {
        global $DB;

        return $DB->insert_record('playercards_lore', (object) [
            'playercardsid' => $playercardsid,
            'subtype' => $subtype,
            'name' => 'Test ' . $subtype,
            'content' => 'Content.',
            'effecttype' => $effecttype,
            'effectvalue' => $effectvalue,
            'maxcopies' => 3,
            'difficulty' => $difficulty,
            'questionsource' => $subtype !== 'trap' ? 'own' : null,
            'questioncategory' => $subtype !== 'trap' ? $category : null,
            'createdby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * set_lore() places the card face-down in the chosen slot and removes it from hand.
     *
     * @return void
     */
    public function test_set_lore_places_card_and_removes_from_hand(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $loreid = $this->insert_lore_card((int) $instance->id, 'trap', 'lp_damage', 400);
        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanhand'][] = ['uid' => 'lorecard', 'cardtype' => 'lore', 'cardid' => $loreid];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::set_lore($cmid, $userid, $state['token'], 'lorecard', 2);

        $this->assertNotNull($result['humanlore'][2]);
        $this->assertTrue($result['humanlore'][2]['facedown']);
        $this->assertNotContains('lorecard', array_column($result['humanhand'], 'uid'));
    }

    /**
     * Activating an Info card reveals its sampled content and applies its effect
     * unconditionally (SCOPE.md 4.6).
     *
     * @return void
     */
    public function test_activate_lore_info_reveals_content_and_applies_effect(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $DB->insert_record('playercards_questions', (object) [
            'playercardsid' => $instance->id,
            'category' => 'test',
            'qtype' => 'description',
            'questiontext' => 'Revealed fact.',
            'answers' => null,
            'approved' => 1,
            'addedby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $loreid = $this->insert_lore_card((int) $instance->id, 'info', 'atk_buff', 300);

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('vet', $guardianids[0], 'attack');
        $state['humanlore'][1] = ['uid' => 'lorecard', 'cardtype' => 'lore', 'cardid' => $loreid, 'facedown' => true];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::activate_lore($cmid, $userid, $instance, $state['token'], 1, 0);

        $this->assertSame('Revealed fact.', $result['revealedcontent']);
        $this->assertSame(300, $result['state']['humanfield'][0]['atkbonus']);
        $this->assertNull($result['state']['humanlore'][1]);
    }

    /**
     * A Trap card applies its effect with no revealed content at all (SCOPE.md 4.6).
     *
     * @return void
     */
    public function test_activate_lore_trap_applies_effect_without_content(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $loreid = $this->insert_lore_card((int) $instance->id, 'trap', 'lp_damage', 400);
        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanlore'][1] = ['uid' => 'lorecard', 'cardtype' => 'lore', 'cardid' => $loreid, 'facedown' => true];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::activate_lore($cmid, $userid, $instance, $state['token'], 1, null);

        $this->assertSame('', $result['revealedcontent']);
        $this->assertSame(9600, $result['state']['lifepoints']['ai']);
    }

    /**
     * activate_lore() ends the match immediately when a Trap card's lp_damage effect
     * knocks the AI's life points to 0 or below — same missing-check bug as
     * declare_attack()'s knockout tests above, fixed alongside it in v1.17.
     *
     * @return void
     */
    public function test_activate_lore_finishes_match_on_knockout(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = (int) $instance->cmid;
        $userid = (int) $student->id;

        $loreid = $this->insert_lore_card((int) $instance->id, 'trap', 'lp_damage', 400);
        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['lifepoints']['ai'] = 300;
        $state['humanlore'][1] = ['uid' => 'lorecard', 'cardtype' => 'lore', 'cardid' => $loreid, 'facedown' => true];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::activate_lore($cmid, $userid, $instance, $state['token'], 1, null);

        $this->assertTrue($result['state']['finished']);
        $this->assertSame('win', $result['state']['result']);

        $attempt = $DB->get_record('playercards_attempts', ['playercardsid' => $instance->id, 'userid' => $userid]);
        $this->assertNotFalse($attempt);
        $this->assertSame('win', $attempt->result);
    }

    /**
     * activate_lore() refuses a Quiz card — it must go through activate_quiz() instead.
     *
     * @return void
     */
    public function test_activate_lore_rejects_quiz_card(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $loreid = $this->insert_lore_card((int) $instance->id, 'quiz', 'lp_damage', 400, 'test', 'easy');
        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanlore'][1] = ['uid' => 'lorecard', 'cardtype' => 'lore', 'cardid' => $loreid, 'facedown' => true];
        $this->inject_state($cmid, $userid, $state);

        $this->expectException(\moodle_exception::class);
        match_service::activate_lore($cmid, $userid, $instance, $state['token'], 1, null);
    }

    /**
     * activate_quiz() with the match on 'hard' AI difficulty deterministically answers
     * correctly (guess_probability() is 1.0 for hard), healing the AI by its own
     * difficulty-scaled bonus and discarding the card with no effect applied.
     *
     * @return void
     */
    public function test_activate_quiz_ai_correct_heals_ai(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $DB->insert_record('playercards_questions', (object) [
            'playercardsid' => $instance->id,
            'category' => 'test',
            'qtype' => 'truefalse',
            'questiontext' => 'The sky is blue.',
            'answers' => json_encode([
                ['text' => 'True', 'correct' => true],
                ['text' => 'False', 'correct' => false],
            ]),
            'approved' => 1,
            'addedby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $loreid = $this->insert_lore_card((int) $instance->id, 'quiz', 'lp_damage', 400, 'test', 'medium');

        $started = match_service::start_match($instance, $cmid, $userid, 'hard');
        $started['firstplayer'] = 'human';
        $started['activeplayer'] = 'human';
        $this->inject_state($cmid, $userid, $started);

        $state = match_service::mulligan($cmid, $userid, $instance, $started['token'], true);
        $state['humanlore'][1] = ['uid' => 'lorecard', 'cardtype' => 'lore', 'cardid' => $loreid, 'facedown' => true];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::activate_quiz($cmid, $userid, $instance, $state['token'], 1, null);

        $this->assertTrue($result['aicorrect']);
        $this->assertSame(500, $result['lpchange']);
        $this->assertSame(10500, $result['state']['lifepoints']['ai']);
        $this->assertSame(10000, $result['state']['lifepoints']['human']);
        $this->assertNull($result['state']['humanlore'][1]);
    }

    /**
     * class_promotion() spends a pending authorization, removes both sacrifices (one
     * from the field, one from hand) and permanently boosts a third own Guardian already
     * in play (SCOPE.md 4.3).
     *
     * @return void
     */
    public function test_class_promotion_boosts_field_target_and_clears_authorization(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('sac1', $guardianids[0], 'attack');
        $state['humanfield'][1] = $this->field_entry('target', $guardianids[1], 'defense');
        $state['humanhand'][] = ['uid' => 'sac2', 'cardtype' => 'guardian', 'cardid' => $guardianids[2]];
        $state['pendingpromotion'] = ['bonus' => 400];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::class_promotion(
            $cmid,
            $userid,
            $state['token'],
            [['source' => 'field', 'ref' => '0'], ['source' => 'hand', 'ref' => 'sac2']],
            ['source' => 'field', 'ref' => '1'],
            'def'
        );

        $this->assertNull($result['humanfield'][0]);
        $this->assertNotContains('sac2', array_column($result['humanhand'], 'uid'));
        $this->assertSame(400, $result['humanfield'][1]['defbonus']);
        $this->assertArrayNotHasKey('pendingpromotion', $result);
    }

    /**
     * A target coming from hand enters the field as a Special Summon: it does not
     * consume the normal muster, but still carries summoning sickness (SCOPE.md 4.2, 4.3).
     *
     * @return void
     */
    public function test_class_promotion_special_summons_hand_target(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanfield'][0] = $this->field_entry('sac1', $guardianids[0], 'attack');
        $state['humanhand'][] = ['uid' => 'sac2', 'cardtype' => 'guardian', 'cardid' => $guardianids[1]];
        $state['humanhand'][] = ['uid' => 'target', 'cardtype' => 'guardian', 'cardid' => $guardianids[2]];
        $state['pendingpromotion'] = ['bonus' => 500];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::class_promotion(
            $cmid,
            $userid,
            $state['token'],
            [['source' => 'field', 'ref' => '0'], ['source' => 'hand', 'ref' => 'sac2']],
            ['source' => 'hand', 'ref' => 'target'],
            'atk'
        );

        // The sacrificed field slot (0) is now empty, so the Special-Summoned target
        // lands there.
        $this->assertNotNull($result['humanfield'][0]);
        $this->assertSame($guardianids[2], $result['humanfield'][0]['cardid']);
        $this->assertTrue($result['humanfield'][0]['sick']);
        $this->assertSame(500, $result['humanfield'][0]['atkbonus']);
        $this->assertFalse($result['musterusedthisturn']);
    }

    /**
     * class_promotion() is rejected without a pending authorization, and rejects two
     * sacrifices both coming from hand (SCOPE.md 4.3 — at least one must be in play).
     *
     * @return void
     */
    public function test_class_promotion_rejects_without_authorization_and_two_hand_sacrifices(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanhand'][] = ['uid' => 'sac1', 'cardtype' => 'guardian', 'cardid' => $guardianids[0]];
        $state['humanhand'][] = ['uid' => 'sac2', 'cardtype' => 'guardian', 'cardid' => $guardianids[1]];
        $state['humanhand'][] = ['uid' => 'target', 'cardtype' => 'guardian', 'cardid' => $guardianids[2]];
        $this->inject_state($cmid, $userid, $state);

        $sacrifices = [['source' => 'hand', 'ref' => 'sac1'], ['source' => 'hand', 'ref' => 'sac2']];
        $target = ['source' => 'hand', 'ref' => 'target'];

        try {
            match_service::class_promotion($cmid, $userid, $state['token'], $sacrifices, $target, 'atk');
            $this->fail('Expected a moodle_exception without a pending authorization.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_promotionnotauthorized', $e->errorcode);
        }

        $state['pendingpromotion'] = ['bonus' => 300];
        $this->inject_state($cmid, $userid, $state);

        try {
            match_service::class_promotion($cmid, $userid, $state['token'], $sacrifices, $target, 'atk');
            $this->fail('Expected a moodle_exception for two hand-only sacrifices.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_needfieldsacrifice', $e->errorcode);
        }
    }

    /**
     * end_turn() closes the human's hand limit, lets the AI muster and attack, then
     * opens the human's next turn — advancing turnnumber by 2 and drawing one card for
     * each side along the way.
     *
     * @return void
     */
    public function test_end_turn_advances_turn_and_lets_ai_act(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['aihand'] = [['uid' => 'aig1', 'cardtype' => 'guardian', 'cardid' => $guardianids[0]]];
        $state['aifield'] = array_fill(0, 5, null);
        $state['humanfield'] = array_fill(0, 5, null);
        $humandeckbefore = count($state['humandeck']);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::end_turn($cmid, $userid, $instance, $state['token']);

        $this->assertFalse($result['finished']);
        $this->assertSame('human', $result['activeplayer']);
        $this->assertSame(4, $result['turnnumber']);
        // The AI musters its only Guardian, then immediately attacks directly with it
        // (humanfield is empty) — a Guardian can attack the same turn it is mustered,
        // real Yu-Gi-Oh has no summoning-sickness restriction on attacking.
        $this->assertNotNull($result['aifield'][0]);
        $this->assertSame($guardianids[0], $result['aifield'][0]['cardid']);
        $this->assertTrue($result['aifield'][0]['attackedthisturn']);
        $this->assertSame(10000 - 400, $result['lifepoints']['human']);
        // The human draws one card at the very end of end_turn(), for their next turn.
        $this->assertCount($humandeckbefore - 1, $result['humandeck']);
        $this->assertFalse($result['musterusedthisturn']);
        $this->assertFalse($result['postureusedthisturn']);
    }

    /**
     * The human's hand is trimmed to the maximum size at the end of their own turn, and
     * the newly drawn card for their next turn is appended after the trim.
     *
     * @return void
     */
    public function test_end_turn_trims_human_hand_to_max_size(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $guardianids = $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humanhand'] = [];
        for ($i = 0; $i < 8; $i++) {
            $state['humanhand'][] = ['uid' => "extra{$i}", 'cardtype' => 'guardian', 'cardid' => $guardianids[0]];
        }
        $expectedkept = array_slice(array_column($state['humanhand'], 'uid'), 0, 6);
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::end_turn($cmid, $userid, $instance, $state['token']);

        $this->assertCount(7, $result['humanhand']);
        $this->assertSame($expectedkept, array_slice(array_column($result['humanhand'], 'uid'), 0, 6));
    }

    /**
     * When the AI's deck runs out before it would draw, the match ends immediately as a
     * human win, and a {playercards_attempts} row is recorded.
     *
     * @return void
     */
    public function test_end_turn_ai_deck_out_ends_match_as_win(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = (int) $instance->cmid;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['aideck'] = [];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::end_turn($cmid, $userid, $instance, $state['token']);

        $this->assertTrue($result['finished']);
        $this->assertSame('win', $result['result']);

        $attempt = $DB->get_record('playercards_attempts', ['playercardsid' => $instance->id, 'userid' => $userid]);
        $this->assertNotFalse($attempt);
        $this->assertSame('win', $attempt->result);
        $this->assertSame(100.0, (float) $attempt->score);
        $this->assertSame(3, (int) $attempt->turnsplayed);
    }

    /**
     * When the human's deck runs out at the start of their next turn, the match ends
     * immediately as a loss.
     *
     * @return void
     */
    public function test_end_turn_human_deck_out_ends_match_as_loss(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = (int) $instance->cmid;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['humandeck'] = [];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::end_turn($cmid, $userid, $instance, $state['token']);

        $this->assertTrue($result['finished']);
        $this->assertSame('loss', $result['result']);

        $attempt = $DB->get_record('playercards_attempts', ['playercardsid' => $instance->id, 'userid' => $userid]);
        $this->assertNotFalse($attempt);
        $this->assertSame('loss', $attempt->result);
        $this->assertSame(0.0, (float) $attempt->score);
        $this->assertSame(4, (int) $attempt->turnsplayed);
    }

    /**
     * Reaching the configured turn limit ends the match immediately, awarding the
     * result to whoever has more life points at that moment (SCOPE.md 4.9).
     *
     * @return void
     */
    public function test_end_turn_maxturns_limit_ends_match_by_lifepoints(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'playercards',
            ['course' => $course->id, 'maxturns' => 1]
        );
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = (int) $instance->cmid;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['lifepoints'] = ['human' => 5000, 'ai' => 6000];
        $this->inject_state($cmid, $userid, $state);

        $result = match_service::end_turn($cmid, $userid, $instance, $state['token']);

        $this->assertTrue($result['finished']);
        $this->assertSame('loss', $result['result']);
    }

    /**
     * end_turn() is rejected once the match has already finished.
     *
     * @return void
     */
    public function test_end_turn_rejects_when_finished(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['finished'] = true;
        $state['result'] = 'win';
        $this->inject_state($cmid, $userid, $state);

        $this->expectException(\moodle_exception::class);
        match_service::end_turn($cmid, $userid, $instance, $state['token']);
    }

    /**
     * end_turn() is rejected when it is not currently the human's turn.
     *
     * @return void
     */
    public function test_end_turn_rejects_when_not_humans_turn(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->seed_playable_fixture($instance, (int) $student->id);
        $cmid = 42;
        $userid = (int) $student->id;

        $state = $this->reach_main_phase($instance, $cmid, $userid);
        $state['activeplayer'] = 'ai';
        $this->inject_state($cmid, $userid, $state);

        $this->expectException(\moodle_exception::class);
        match_service::end_turn($cmid, $userid, $instance, $state['token']);
    }
}
