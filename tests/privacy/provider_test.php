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
 * Privacy provider tests for mod_playercards.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the Privacy API provider.
 *
 * @covers \mod_playercards\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a playercards course module and returns its instance record.
     *
     * @param \stdClass $course Course object.
     * @return \stdClass Playercards instance record (id, cmid, ...).
     */
    private function make_instance(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_module('playercards', ['course' => $course->id]);
    }

    /**
     * Inserts one Lore card authored by the given user.
     *
     * @param int $playercardsid Activity instance id.
     * @param int $createdby Author user id.
     * @return int Inserted lore id.
     */
    private function make_lore(int $playercardsid, int $createdby): int {
        global $DB;
        return $DB->insert_record('playercards_lore', (object) [
            'playercardsid'    => $playercardsid,
            'subtype'          => 'info',
            'name'             => 'Test Lore',
            'content'          => 'Educational content.',
            'effecttype'       => 'atk_buff',
            'effectvalue'      => 100,
            'maxcopies'        => 3,
            'difficulty'       => null,
            'questionsource'   => 'own',
            'questioncategory' => 'test',
            'createdby'        => $createdby,
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);
    }

    /**
     * Inserts one question pool item added by the given user.
     *
     * @param int $playercardsid Activity instance id.
     * @param int $addedby Author user id.
     * @return int Inserted question id.
     */
    private function make_question(int $playercardsid, int $addedby): int {
        global $DB;
        return $DB->insert_record('playercards_questions', (object) [
            'playercardsid' => $playercardsid,
            'category'      => 'test',
            'qtype'         => 'truefalse',
            'questiontext'  => 'Is this a test question?',
            'answers'       => json_encode([['text' => 'Yes', 'correct' => true]]),
            'approved'      => 1,
            'addedby'       => $addedby,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * Inserts one card ownership row for the given student.
     *
     * @param int $playercardsid Activity instance id.
     * @param int $userid Student user id.
     * @return int Inserted ownership id.
     */
    private function make_ownership(int $playercardsid, int $userid): int {
        global $DB;
        return $DB->insert_record('playercards_ownership', (object) [
            'playercardsid' => $playercardsid,
            'userid'        => $userid,
            'cardtype'      => 'guardian',
            'cardid'        => 1,
            'quantity'      => 2,
            'timemodified'  => time(),
        ]);
    }

    /**
     * Inserts one deck. Pass $userid and $playercardsid as null together to create a
     * global AI reference deck, mirroring the 3 fixed decks the real game seeds.
     *
     * @param int|null $playercardsid Activity instance id, or null for a global AI reference deck.
     * @param int|null $userid Student user id, or null for a global AI reference deck.
     * @param string|null $aidifficulty AI difficulty, set only on AI reference decks.
     * @return int Inserted deck id.
     */
    private function make_deck(?int $playercardsid, ?int $userid, ?string $aidifficulty = null): int {
        global $DB;
        return $DB->insert_record('playercards_decks', (object) [
            'playercardsid' => $playercardsid,
            'userid'        => $userid,
            'aidifficulty'  => $aidifficulty,
            'name'          => 'Test Deck',
            'size'          => 40,
            'active'        => 1,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * Inserts one card into a deck's composition.
     *
     * @param int $deckid Deck id.
     * @return int Inserted deck card id.
     */
    private function make_deck_card(int $deckid): int {
        global $DB;
        return $DB->insert_record('playercards_deck_cards', (object) [
            'deckid'   => $deckid,
            'cardtype' => 'guardian',
            'cardid'   => 1,
            'quantity' => 3,
        ]);
    }

    /**
     * Inserts one completed match attempt for the given student.
     *
     * @param int $playercardsid Activity instance id.
     * @param int $userid Student user id.
     * @param int $deckid Deck used in the match.
     * @return int Inserted attempt id.
     */
    private function make_attempt(int $playercardsid, int $userid, int $deckid): int {
        global $DB;
        return $DB->insert_record('playercards_attempts', (object) [
            'playercardsid' => $playercardsid,
            'userid'        => $userid,
            'deckid'        => $deckid,
            'aidifficulty'  => 'normal',
            'result'        => 'win',
            'lpremaining'   => 4000,
            'turnsplayed'   => 6,
            'score'         => 100.0,
            'timecreated'   => time(),
        ]);
    }

    /**
     * Asserts that a table's declared metadata field keys, plus a documented exclusion
     * list, exactly account for every real column of that table (minus id) — in both
     * directions, so a column silently added to install.xml without an explicit decision
     * fails this test, and so does an exclusion left behind for a column that no longer
     * exists.
     *
     * @param string $table Table name, without the {} braces.
     * @param array $documentedexclusions Column names deliberately not exported.
     * @return void
     */
    private function assert_table_columns_declared_or_documented(string $table, array $documentedexclusions): void {
        global $DB;

        $tableitem = null;
        foreach (provider::get_metadata(new collection('mod_playercards'))->get_collection() as $item) {
            if ($item->get_name() === $table) {
                $tableitem = $item;
                break;
            }
        }
        $this->assertNotNull($tableitem, "Table '$table' is not declared in get_metadata().");
        $declaredfields = array_keys($tableitem->get_privacy_fields());

        $realcolumns = array_keys($DB->get_columns($table));
        $realcolumns = array_values(array_diff($realcolumns, ['id']));

        sort($declaredfields);
        $expected = $declaredfields;
        foreach ($documentedexclusions as $excluded) {
            $expected[] = $excluded;
        }
        sort($expected);
        sort($realcolumns);
        $this->assertSame($realcolumns, $expected);

        foreach ($documentedexclusions as $excluded) {
            $this->assertNotContains($excluded, $declaredfields);
        }
    }

    /**
     * Tests that get_metadata declares all 6 playercards tables carrying personal data.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_playercards'));
        $keys = array_map(fn ($item) => $item->get_name(), $collection->get_collection());

        $this->assertContains('playercards_lore', $keys);
        $this->assertContains('playercards_questions', $keys);
        $this->assertContains('playercards_ownership', $keys);
        $this->assertContains('playercards_decks', $keys);
        $this->assertContains('playercards_deck_cards', $keys);
        $this->assertContains('playercards_attempts', $keys);
    }

    /**
     * Regression test for metadata drift on playercards_lore: effecttype, effectvalue,
     * maxcopies, difficulty, questionsource and questioncategory are catalog/game-mechanics
     * configuration, not personal data; timemodified can be touched by any teacher with the
     * managelore capability editing another author's card (manage.php never updates
     * createdby/timecreated on an edit); playercardsid is a structural foreign key.
     *
     * @return void
     */
    public function test_metadata_playercards_lore_columns_declared_or_documented(): void {
        $this->assert_table_columns_declared_or_documented('playercards_lore', [
            'playercardsid', 'effecttype', 'effectvalue', 'maxcopies',
            'difficulty', 'questionsource', 'questioncategory', 'timemodified',
        ]);
    }

    /**
     * Regression test for metadata drift on playercards_questions: approved is a
     * moderation flag any teacher can toggle and timemodified can be touched by another
     * teacher's edit, so neither reliably traces the addedby user's own action;
     * playercardsid is a structural foreign key.
     *
     * @return void
     */
    public function test_metadata_playercards_questions_columns_declared_or_documented(): void {
        $this->assert_table_columns_declared_or_documented('playercards_questions', [
            'playercardsid', 'approved', 'timemodified',
        ]);
    }

    /**
     * Regression test for metadata drift on playercards_ownership: playercardsid is a
     * structural foreign key, always scoped by instance on every call.
     *
     * @return void
     */
    public function test_metadata_playercards_ownership_columns_declared_or_documented(): void {
        $this->assert_table_columns_declared_or_documented('playercards_ownership', ['playercardsid']);
    }

    /**
     * Regression test for metadata drift on playercards_decks: playercardsid is a
     * structural foreign key, always scoped by instance on every call.
     *
     * @return void
     */
    public function test_metadata_playercards_decks_columns_declared_or_documented(): void {
        $this->assert_table_columns_declared_or_documented('playercards_decks', ['playercardsid']);
    }

    /**
     * Regression test for metadata drift on playercards_deck_cards: deckid is a
     * structural foreign key, the table is always exported/deleted alongside its
     * parent deck rather than looked up independently by user.
     *
     * @return void
     */
    public function test_metadata_playercards_deck_cards_columns_declared_or_documented(): void {
        $this->assert_table_columns_declared_or_documented('playercards_deck_cards', ['deckid']);
    }

    /**
     * Regression test for metadata drift on playercards_attempts: playercardsid and
     * deckid are structural foreign keys.
     *
     * @return void
     */
    public function test_metadata_playercards_attempts_columns_declared_or_documented(): void {
        $this->assert_table_columns_declared_or_documented('playercards_attempts', ['playercardsid', 'deckid']);
    }

    /**
     * Tests that get_contexts_for_userid finds a context via each of the 5 personal-data
     * sources independently: Lore authorship, question authorship, card ownership, saved
     * decks and match attempts.
     *
     * @return void
     */
    public function test_get_contexts_for_userid_finds_every_source(): void {
        $course = $this->getDataGenerator()->create_course();

        $cmlore = $this->make_instance($course);
        $userlore = $this->getDataGenerator()->create_user();
        $this->make_lore((int) $cmlore->id, (int) $userlore->id);

        $cmquestion = $this->make_instance($course);
        $userquestion = $this->getDataGenerator()->create_user();
        $this->make_question((int) $cmquestion->id, (int) $userquestion->id);

        $cmownership = $this->make_instance($course);
        $userownership = $this->getDataGenerator()->create_user();
        $this->make_ownership((int) $cmownership->id, (int) $userownership->id);

        $cmdeck = $this->make_instance($course);
        $userdeck = $this->getDataGenerator()->create_user();
        $this->make_deck((int) $cmdeck->id, (int) $userdeck->id);

        $cmattempt = $this->make_instance($course);
        $userattempt = $this->getDataGenerator()->create_user();
        $deckid = $this->make_deck((int) $cmattempt->id, (int) $userattempt->id);
        $this->make_attempt((int) $cmattempt->id, (int) $userattempt->id, $deckid);

        $cases = [
            [$userlore, $cmlore],
            [$userquestion, $cmquestion],
            [$userownership, $cmownership],
            [$userdeck, $cmdeck],
            [$userattempt, $cmattempt],
        ];
        foreach ($cases as [$user, $cm]) {
            $contextlist = provider::get_contexts_for_userid((int) $user->id);
            $expected = \context_module::instance($cm->cmid)->id;
            $this->assertContains((string) $expected, $contextlist->get_contextids());
        }
    }

    /**
     * Regression test: a global AI reference deck (userid and playercardsid both null)
     * must never surface a context for an unrelated user, even though it lives in the
     * same table as real student decks.
     *
     * @return void
     */
    public function test_get_contexts_for_userid_ignores_global_ai_reference_decks(): void {
        $this->make_deck(null, null, 'normal');
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertSame([], $contextlist->get_contextids());
    }

    /**
     * Tests that get_users_in_context aggregates users from all 5 personal-data sources
     * within a single instance, and ignores a non-module context.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);

        $teacher = $this->getDataGenerator()->create_user();
        $author = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $this->make_lore((int) $cm->id, (int) $teacher->id);
        $this->make_question((int) $cm->id, (int) $author->id);
        $this->make_ownership((int) $cm->id, (int) $student1->id);
        $deckid = $this->make_deck((int) $cm->id, (int) $student2->id);
        $this->make_attempt((int) $cm->id, (int) $student2->id, $deckid);

        $context = \context_module::instance($cm->cmid);
        $userlist = new userlist($context, 'mod_playercards');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int) $teacher->id, $userids);
        $this->assertContains((int) $author->id, $userids);
        $this->assertContains((int) $student1->id, $userids);
        $this->assertContains((int) $student2->id, $userids);
        $this->assertCount(4, $userids);
    }

    /**
     * Tests that get_users_in_context is a silent no-op for a non-module context.
     *
     * @return void
     */
    public function test_get_users_in_context_ignores_non_module_context(): void {
        $userlist = new userlist(\context_system::instance(), 'mod_playercards');

        provider::get_users_in_context($userlist);

        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * Tests that export_user_data exports all 5 sources for the user, including a deck's
     * card composition nested under the deck, and does not leak another user's data from
     * the same instance.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        $this->make_lore((int) $cm->id, (int) $user->id);
        $this->make_question((int) $cm->id, (int) $user->id);
        $this->make_ownership((int) $cm->id, (int) $user->id);
        $deckid = $this->make_deck((int) $cm->id, (int) $user->id);
        $this->make_deck_card($deckid);
        $this->make_attempt((int) $cm->id, (int) $user->id, $deckid);

        // Another user's data in the same instance must never appear in this export.
        $this->make_lore((int) $cm->id, (int) $otheruser->id);

        $context = \context_module::instance($cm->cmid);
        $contextlist = new approved_contextlist($user, 'mod_playercards', [$context->id]);
        provider::export_user_data($contextlist);

        $writercontext = writer::with_context($context);

        $loredata = $writercontext->get_data([get_string('privacy:metadata:playercards_lore', 'mod_playercards')]);
        $this->assertCount(1, $loredata->lore);
        $this->assertSame('Test Lore', $loredata->lore[0]['name']);

        $questiondata = $writercontext->get_data(
            [get_string('privacy:metadata:playercards_questions', 'mod_playercards')]
        );
        $this->assertCount(1, $questiondata->questions);
        $this->assertSame('Is this a test question?', $questiondata->questions[0]['questiontext']);

        $ownershipdata = $writercontext->get_data(
            [get_string('privacy:metadata:playercards_ownership', 'mod_playercards')]
        );
        $this->assertCount(1, $ownershipdata->ownership);
        $this->assertSame(2, $ownershipdata->ownership[0]['quantity']);

        $decksdata = $writercontext->get_data([get_string('privacy:metadata:playercards_decks', 'mod_playercards')]);
        $this->assertCount(1, $decksdata->decks);
        $this->assertSame('Test Deck', $decksdata->decks[0]['name']);
        $this->assertCount(1, $decksdata->decks[0]['cards']);
        $this->assertSame(3, $decksdata->decks[0]['cards'][0]['quantity']);

        $attemptsdata = $writercontext->get_data(
            [get_string('privacy:metadata:playercards_attempts', 'mod_playercards')]
        );
        $this->assertCount(1, $attemptsdata->attempts);
        $this->assertSame('win', $attemptsdata->attempts[0]['result']);
    }

    /**
     * Regression test: a global AI reference deck (userid and playercardsid both null)
     * must never be exported for a real student, even though it lives in the same table.
     *
     * @return void
     */
    public function test_export_user_data_does_not_leak_global_ai_reference_decks(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_deck(null, null, 'hard');

        $context = \context_module::instance($cm->cmid);
        $contextlist = new approved_contextlist($user, 'mod_playercards', [$context->id]);
        provider::export_user_data($contextlist);

        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    /**
     * Tests that export_user_data is a no-op for an empty approved contextlist.
     *
     * @return void
     */
    public function test_export_user_data_empty_contextlist_is_noop(): void {
        $user = $this->getDataGenerator()->create_user();
        $contextlist = new approved_contextlist($user, 'mod_playercards', []);

        provider::export_user_data($contextlist);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Tests that export_user_data ignores a non-module context in the approved list.
     *
     * @return void
     */
    public function test_export_user_data_ignores_non_module_context(): void {
        $user = $this->getDataGenerator()->create_user();
        $contextlist = new approved_contextlist($user, 'mod_playercards', [\context_system::instance()->id]);

        provider::export_user_data($contextlist);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Tests that delete_data_for_user anonymises Lore/question authorship (createdby/
     * addedby set to 0) and hard-deletes ownership, decks (with their card composition)
     * and attempts — for the target user only, leaving another user's data in the same
     * instance untouched.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        $loreid = $this->make_lore((int) $cm->id, (int) $user->id);
        $questionid = $this->make_question((int) $cm->id, (int) $user->id);
        $this->make_ownership((int) $cm->id, (int) $user->id);
        $deckid = $this->make_deck((int) $cm->id, (int) $user->id);
        $deckcardid = $this->make_deck_card($deckid);
        $this->make_attempt((int) $cm->id, (int) $user->id, $deckid);

        $otherloreid = $this->make_lore((int) $cm->id, (int) $otheruser->id);
        $this->make_ownership((int) $cm->id, (int) $otheruser->id);

        $context = \context_module::instance($cm->cmid);
        $contextlist = new approved_contextlist($user, 'mod_playercards', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame('0', (string) $DB->get_field('playercards_lore', 'createdby', ['id' => $loreid]));
        $this->assertSame('0', (string) $DB->get_field('playercards_questions', 'addedby', ['id' => $questionid]));
        $this->assertSame(0, $DB->count_records('playercards_ownership', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('playercards_decks', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('playercards_deck_cards', ['id' => $deckcardid]));
        $this->assertSame(0, $DB->count_records('playercards_attempts', ['userid' => $user->id]));

        // The other user's rows in the same instance must be untouched.
        $this->assertEquals($otheruser->id, $DB->get_field('playercards_lore', 'createdby', ['id' => $otherloreid]));
        $this->assertSame(1, $DB->count_records('playercards_ownership', ['userid' => $otheruser->id]));
    }

    /**
     * Regression test: delete_data_for_user must never touch a global AI reference deck
     * (playercardsid null), even one belonging to the same activity's other decks.
     *
     * @return void
     */
    public function test_delete_data_for_user_never_touches_global_ai_reference_decks(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_deck((int) $cm->id, (int) $user->id);
        $aideckid = $this->make_deck(null, null, 'easy');

        $context = \context_module::instance($cm->cmid);
        $contextlist = new approved_contextlist($user, 'mod_playercards', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertTrue($DB->record_exists('playercards_decks', ['id' => $aideckid]));
    }

    /**
     * Tests that delete_data_for_user is a no-op for an empty approved contextlist.
     *
     * @return void
     */
    public function test_delete_data_for_user_empty_contextlist_is_noop(): void {
        $user = $this->getDataGenerator()->create_user();
        $contextlist = new approved_contextlist($user, 'mod_playercards', []);

        provider::delete_data_for_user($contextlist);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Tests that delete_data_for_users removes data for the listed users only, within
     * one context.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $deckid1 = $this->make_deck((int) $cm->id, (int) $user1->id);
        $this->make_attempt((int) $cm->id, (int) $user1->id, $deckid1);
        $deckid2 = $this->make_deck((int) $cm->id, (int) $user2->id);
        $this->make_attempt((int) $cm->id, (int) $user2->id, $deckid2);

        $context = \context_module::instance($cm->cmid);
        $approvedlist = new approved_userlist($context, 'mod_playercards', [$user1->id]);
        provider::delete_data_for_users($approvedlist);

        $this->assertSame(0, $DB->count_records('playercards_attempts', ['userid' => $user1->id]));
        $this->assertSame(1, $DB->count_records('playercards_attempts', ['userid' => $user2->id]));
        $this->assertSame(0, $DB->count_records('playercards_decks', ['userid' => $user1->id]));
        $this->assertSame(1, $DB->count_records('playercards_decks', ['userid' => $user2->id]));
    }

    /**
     * Tests that delete_data_for_all_users_in_context clears every user's data within
     * that instance, anonymises Lore/question authorship, and leaves another instance's
     * data untouched.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cmtarget = $this->make_instance($course);
        $cmother = $this->make_instance($course);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $loreid = $this->make_lore((int) $cmtarget->id, (int) $user1->id);
        $this->make_ownership((int) $cmtarget->id, (int) $user1->id);
        $deckid = $this->make_deck((int) $cmtarget->id, (int) $user2->id);
        $deckcardid = $this->make_deck_card($deckid);
        $this->make_attempt((int) $cmtarget->id, (int) $user2->id, $deckid);

        $otherloreid = $this->make_lore((int) $cmother->id, (int) $user1->id);
        $otherdeckid = $this->make_deck((int) $cmother->id, (int) $user1->id);
        $this->make_attempt((int) $cmother->id, (int) $user1->id, $otherdeckid);

        provider::delete_data_for_all_users_in_context(\context_module::instance($cmtarget->cmid));

        $this->assertSame('0', (string) $DB->get_field('playercards_lore', 'createdby', ['id' => $loreid]));
        $this->assertSame(0, $DB->count_records('playercards_ownership', ['playercardsid' => $cmtarget->id]));
        $this->assertSame(0, $DB->count_records('playercards_decks', ['playercardsid' => $cmtarget->id]));
        $this->assertFalse($DB->record_exists('playercards_deck_cards', ['id' => $deckcardid]));
        $this->assertSame(0, $DB->count_records('playercards_attempts', ['playercardsid' => $cmtarget->id]));

        // The other instance's data must be untouched.
        $this->assertEquals($user1->id, $DB->get_field('playercards_lore', 'createdby', ['id' => $otherloreid]));
        $this->assertSame(1, $DB->count_records('playercards_decks', ['playercardsid' => $cmother->id]));
        $this->assertSame(1, $DB->count_records('playercards_attempts', ['playercardsid' => $cmother->id]));
    }

    /**
     * Regression test: delete_data_for_all_users_in_context must never touch a global
     * AI reference deck, even when other decks in the target instance are cleared.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context_never_touches_global_ai_reference_decks(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_deck((int) $cm->id, (int) $user->id);
        $aideckid = $this->make_deck(null, null, 'hard');

        provider::delete_data_for_all_users_in_context(\context_module::instance($cm->cmid));

        $this->assertTrue($DB->record_exists('playercards_decks', ['id' => $aideckid]));
    }

    /**
     * Tests that delete_data_for_all_users_in_context is a silent no-op for a
     * non-module context.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context_ignores_non_module_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_instance($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_ownership((int) $cm->id, (int) $user->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(1, $DB->count_records('playercards_ownership', ['playercardsid' => (int) $cm->id]));
    }
}
