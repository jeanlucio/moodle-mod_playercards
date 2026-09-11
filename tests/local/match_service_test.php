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

/**
 * Tests match start, mulligan and state retrieval.
 *
 * @covers \mod_playercards\local\match_service
 * @covers \mod_playercards\local\ai_deck_builder
 * @covers \mod_playercards\local\card_presenter
 */
final class match_service_test extends \advanced_testcase {
    /**
     * Seeds a full instance fixture: 15 Guardians (3 per level), the 'normal' AI
     * reference deck, and a valid 40-card active deck for the given user.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id to own the active deck.
     * @return void
     */
    private function seed_playable_fixture(\stdClass $instance, int $userid): void {
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
}
