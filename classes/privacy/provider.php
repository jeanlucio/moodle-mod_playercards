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
 * Privacy provider implementation for mod_playercards.
 *
 * Personal data stored:
 *   - playercards_lore: createdby (the teacher who authored the card), plus the content
 *     they wrote (subtype, name, content). effecttype/effectvalue/maxcopies/difficulty/
 *     questionsource/questioncategory are catalog/game-mechanics configuration, not personal
 *     data; timemodified is excluded because any teacher with the managelore capability can
 *     edit another teacher's card, so it does not reliably trace the createdby user's own
 *     action (see manage.php, which never updates createdby/timecreated on an edit).
 *   - playercards_questions: addedby (the teacher who added the item), plus the content they
 *     added (category, qtype, questiontext, answers). approved is a moderation flag any
 *     teacher can toggle and timemodified can be touched by another teacher's edit, so
 *     neither reliably traces the addedby user's own action (same rationale as above).
 *   - playercards_ownership: userid, the student's card collection.
 *   - playercards_decks: userid, the student's saved decks. The 3 global AI reference decks
 *     have userid and playercardsid both null, so they never match a userid-scoped query.
 *   - playercards_deck_cards: the composition of a deck. It carries no userid column of its
 *     own — it is linked via deckid to playercards_decks — so it is always exported/deleted
 *     alongside its parent deck rather than looked up independently by user.
 *   - playercards_attempts: userid, the student's completed match history. deckid is a
 *     structural foreign key to playercards_decks, excluded like every other structural id.
 *   - the site-wide "seen intro" user preference (intro_service), mirroring the pattern
 *     already used by mod_playerwords/mod_playercross.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_playercards\local\intro_service;

/**
 * Privacy provider implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('playercards_lore', [
            'createdby'   => 'privacy:metadata:playercards_lore:createdby',
            'subtype'     => 'privacy:metadata:playercards_lore:subtype',
            'name'        => 'privacy:metadata:playercards_lore:name',
            'content'     => 'privacy:metadata:playercards_lore:content',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playercards_lore');

        $collection->add_database_table('playercards_questions', [
            'addedby'      => 'privacy:metadata:playercards_questions:addedby',
            'category'     => 'privacy:metadata:playercards_questions:category',
            'qtype'        => 'privacy:metadata:playercards_questions:qtype',
            'questiontext' => 'privacy:metadata:playercards_questions:questiontext',
            'answers'      => 'privacy:metadata:playercards_questions:answers',
            'timecreated'  => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playercards_questions');

        $collection->add_database_table('playercards_ownership', [
            'userid'       => 'privacy:metadata:userid',
            'cardtype'     => 'privacy:metadata:cardtype',
            'cardid'       => 'privacy:metadata:cardid',
            'quantity'     => 'privacy:metadata:quantity',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:playercards_ownership');

        $collection->add_database_table('playercards_decks', [
            'userid'       => 'privacy:metadata:userid',
            'name'         => 'privacy:metadata:playercards_decks:name',
            'size'         => 'privacy:metadata:playercards_decks:size',
            'active'       => 'privacy:metadata:playercards_decks:active',
            'aidifficulty' => 'privacy:metadata:aidifficulty',
            'timecreated'  => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:playercards_decks');

        $collection->add_database_table('playercards_deck_cards', [
            'cardtype' => 'privacy:metadata:cardtype',
            'cardid'   => 'privacy:metadata:cardid',
            'quantity' => 'privacy:metadata:quantity',
        ], 'privacy:metadata:playercards_deck_cards');

        $collection->add_database_table('playercards_attempts', [
            'userid'       => 'privacy:metadata:userid',
            'aidifficulty' => 'privacy:metadata:aidifficulty',
            'result'       => 'privacy:metadata:playercards_attempts:result',
            'lpremaining'  => 'privacy:metadata:playercards_attempts:lpremaining',
            'turnsplayed'  => 'privacy:metadata:playercards_attempts:turnsplayed',
            'score'        => 'privacy:metadata:playercards_attempts:score',
            'timecreated'  => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playercards_attempts');

        $collection->add_user_preference(
            intro_service::get_preference_name(),
            'privacy:metadata:preference:seenintro'
        );

        return $collection;
    }

    #[\Override]
    public static function export_user_preferences(int $userid): void {
        if (!intro_service::has_seen_intro($userid)) {
            return;
        }

        writer::export_user_preference(
            'mod_playercards',
            intro_service::get_preference_name(),
            transform::yesno(true),
            get_string('privacy:metadata:preference:seenintro', 'mod_playercards')
        );
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sources = [
            ['playercards_lore', 'pl', 'createdby'],
            ['playercards_questions', 'pq', 'addedby'],
            ['playercards_ownership', 'po', 'userid'],
            ['playercards_decks', 'pd', 'userid'],
            ['playercards_attempts', 'pa', 'userid'],
        ];
        foreach ($sources as [$table, $alias, $field]) {
            $sql = "SELECT ctx.id
                      FROM {context} ctx
                      JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {playercards} pc ON pc.id = cm.instance
                      JOIN {" . $table . "} $alias ON $alias.playercardsid = pc.id
                     WHERE $alias.$field = :userid";
            $contextlist->add_from_sql($sql, [
                'ctxlevel' => CONTEXT_MODULE,
                'modname'  => 'playercards',
                'userid'   => $userid,
            ]);
        }

        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('playercards', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $params = ['pid' => (int) $cm->instance];

        $sources = [
            ['playercards_lore', 'createdby'],
            ['playercards_questions', 'addedby'],
            ['playercards_ownership', 'userid'],
            ['playercards_decks', 'userid'],
            ['playercards_attempts', 'userid'],
        ];
        foreach ($sources as [$table, $field]) {
            $userlist->add_from_sql(
                'userid',
                "SELECT $field AS userid FROM {" . $table . "} WHERE playercardsid = :pid",
                $params
            );
        }
    }

    /**
     * Bulk-resolves the playercards instance id for every context_module context in the
     * list in a single query, instead of calling get_coursemodule_from_id() once per
     * context — shared by export_user_data() and delete_data_for_user().
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return array Course module id (int) mapped to playercards instance id (int).
     */
    private static function get_instance_ids_by_cmid(approved_contextlist $contextlist): array {
        global $DB;

        $cmids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                $cmids[] = $context->instanceid;
            }
        }
        if (empty($cmids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql(
            "SELECT cm.id, cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
              WHERE cm.id $insql",
            array_merge(['modname' => 'playercards'], $inparams)
        );

        $map = [];
        foreach ($records as $record) {
            $map[(int) $record->id] = (int) $record->instance;
        }
        return $map;
    }

    /**
     * Bulk-loads every playercards_deck_cards row for the given deck ids, grouped by deckid.
     *
     * @param int[] $deckids Deck ids to load the composition of.
     * @return array Deckid (int) mapped to an array of deck card row objects.
     */
    private static function get_deck_cards_by_deckid(array $deckids): array {
        global $DB;

        if (empty($deckids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($deckids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('playercards_deck_cards', "deckid $insql", $inparams);

        $grouped = [];
        foreach ($records as $record) {
            $grouped[(int) $record->deckid][] = $record;
        }
        return $grouped;
    }

    /**
     * Exports the Lore cards a teacher authored in a context, if any.
     *
     * @param \context $context Context to export into.
     * @param \stdClass[] $rows Rows already scoped to this context and user.
     * @return void
     */
    private static function export_lore(\context $context, array $rows): void {
        if (empty($rows)) {
            return;
        }

        $data = array_values(array_map(function (\stdClass $row): array {
            return [
                'subtype'     => $row->subtype,
                'name'        => $row->name,
                'content'     => $row->content,
                'timecreated' => transform::datetime($row->timecreated),
            ];
        }, $rows));

        writer::with_context($context)->export_data(
            [get_string('privacy:metadata:playercards_lore', 'mod_playercards')],
            (object) ['lore' => $data]
        );
    }

    /**
     * Exports the question pool items a teacher added in a context, if any.
     *
     * @param \context $context Context to export into.
     * @param \stdClass[] $rows Rows already scoped to this context and user.
     * @return void
     */
    private static function export_questions(\context $context, array $rows): void {
        if (empty($rows)) {
            return;
        }

        $data = array_values(array_map(function (\stdClass $row): array {
            return [
                'category'     => $row->category,
                'qtype'        => $row->qtype,
                'questiontext' => $row->questiontext,
                'answers'      => $row->answers,
                'timecreated'  => transform::datetime($row->timecreated),
            ];
        }, $rows));

        writer::with_context($context)->export_data(
            [get_string('privacy:metadata:playercards_questions', 'mod_playercards')],
            (object) ['questions' => $data]
        );
    }

    /**
     * Exports a student's card collection in a context, if any.
     *
     * @param \context $context Context to export into.
     * @param \stdClass[] $rows Rows already scoped to this context and user.
     * @return void
     */
    private static function export_ownership(\context $context, array $rows): void {
        if (empty($rows)) {
            return;
        }

        $data = array_values(array_map(function (\stdClass $row): array {
            return [
                'cardtype'     => $row->cardtype,
                'cardid'       => (int) $row->cardid,
                'quantity'     => (int) $row->quantity,
                'timemodified' => transform::datetime($row->timemodified),
            ];
        }, $rows));

        writer::with_context($context)->export_data(
            [get_string('privacy:metadata:playercards_ownership', 'mod_playercards')],
            (object) ['ownership' => $data]
        );
    }

    /**
     * Exports a student's saved decks in a context, if any, each with its card composition.
     *
     * @param \context $context Context to export into.
     * @param \stdClass[] $rows Deck rows already scoped to this context and user.
     * @param array $deckcardsbydeck Deck card row objects, keyed by deckid (int).
     * @return void
     */
    private static function export_decks(\context $context, array $rows, array $deckcardsbydeck): void {
        if (empty($rows)) {
            return;
        }

        $data = array_values(array_map(function (\stdClass $row) use ($deckcardsbydeck): array {
            $cards = array_values(array_map(function (\stdClass $card): array {
                return [
                    'cardtype' => $card->cardtype,
                    'cardid'   => (int) $card->cardid,
                    'quantity' => (int) $card->quantity,
                ];
            }, $deckcardsbydeck[(int) $row->id] ?? []));

            return [
                'name'         => $row->name,
                'size'         => (int) $row->size,
                'active'       => transform::yesno($row->active),
                'aidifficulty' => $row->aidifficulty,
                'timecreated'  => transform::datetime($row->timecreated),
                'timemodified' => transform::datetime($row->timemodified),
                'cards'        => $cards,
            ];
        }, $rows));

        writer::with_context($context)->export_data(
            [get_string('privacy:metadata:playercards_decks', 'mod_playercards')],
            (object) ['decks' => $data]
        );
    }

    /**
     * Exports a student's completed match attempts in a context, if any.
     *
     * @param \context $context Context to export into.
     * @param \stdClass[] $rows Rows already scoped to this context and user.
     * @return void
     */
    private static function export_attempts(\context $context, array $rows): void {
        if (empty($rows)) {
            return;
        }

        $data = array_values(array_map(function (\stdClass $row): array {
            return [
                'aidifficulty' => $row->aidifficulty,
                'result'       => $row->result,
                'lpremaining'  => (int) $row->lpremaining,
                'turnsplayed'  => (int) $row->turnsplayed,
                'score'        => (float) $row->score,
                'timecreated'  => transform::datetime($row->timecreated),
            ];
        }, $rows));

        writer::with_context($context)->export_data(
            [get_string('privacy:metadata:playercards_attempts', 'mod_playercards')],
            (object) ['attempts' => $data]
        );
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $instanceidsbycmid = self::get_instance_ids_by_cmid($contextlist);
        $instanceids = array_values($instanceidsbycmid);
        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);

        $lorebyinstance = [];
        $lore = $DB->get_records_select(
            'playercards_lore',
            "createdby = :userid AND playercardsid $insql",
            array_merge(['userid' => $userid], $inparams),
            'timecreated ASC'
        );
        foreach ($lore as $row) {
            $lorebyinstance[(int) $row->playercardsid][] = $row;
        }

        $questionsbyinstance = [];
        $questions = $DB->get_records_select(
            'playercards_questions',
            "addedby = :userid AND playercardsid $insql",
            array_merge(['userid' => $userid], $inparams),
            'timecreated ASC'
        );
        foreach ($questions as $row) {
            $questionsbyinstance[(int) $row->playercardsid][] = $row;
        }

        $ownershipbyinstance = [];
        $ownership = $DB->get_records_select(
            'playercards_ownership',
            "userid = :userid AND playercardsid $insql",
            array_merge(['userid' => $userid], $inparams)
        );
        foreach ($ownership as $row) {
            $ownershipbyinstance[(int) $row->playercardsid][] = $row;
        }

        $decksbyinstance = [];
        $decks = $DB->get_records_select(
            'playercards_decks',
            "userid = :userid AND playercardsid $insql",
            array_merge(['userid' => $userid], $inparams),
            'timecreated ASC'
        );
        foreach ($decks as $row) {
            $decksbyinstance[(int) $row->playercardsid][] = $row;
        }
        $deckcardsbydeck = self::get_deck_cards_by_deckid(array_keys($decks));

        $attemptsbyinstance = [];
        $attempts = $DB->get_records_select(
            'playercards_attempts',
            "userid = :userid AND playercardsid $insql",
            array_merge(['userid' => $userid], $inparams),
            'timecreated ASC'
        );
        foreach ($attempts as $row) {
            $attemptsbyinstance[(int) $row->playercardsid][] = $row;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $instanceid = $instanceidsbycmid[$context->instanceid] ?? null;
            if ($instanceid === null) {
                continue;
            }

            self::export_lore($context, $lorebyinstance[$instanceid] ?? []);
            self::export_questions($context, $questionsbyinstance[$instanceid] ?? []);
            self::export_ownership($context, $ownershipbyinstance[$instanceid] ?? []);
            self::export_decks($context, $decksbyinstance[$instanceid] ?? [], $deckcardsbydeck);
            self::export_attempts($context, $attemptsbyinstance[$instanceid] ?? []);
        }
    }

    /**
     * Deletes playercards_deck_cards rows for every deck matching a WHERE clause on
     * playercards_decks. Call before deleting the parent decks.
     *
     * @param string $deckswhere WHERE clause against {playercards_decks}.
     * @param array $params Named parameters for the clause.
     * @return void
     */
    private static function delete_deck_cards_for_decks(string $deckswhere, array $params): void {
        global $DB;

        $DB->delete_records_select(
            'playercards_deck_cards',
            "deckid IN (SELECT id FROM {playercards_decks} WHERE $deckswhere)",
            $params
        );
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('playercards', $context->instanceid);
        if (!$cm) {
            return;
        }

        $instanceid = (int) $cm->instance;

        $DB->set_field('playercards_lore', 'createdby', 0, ['playercardsid' => $instanceid]);
        $DB->set_field('playercards_questions', 'addedby', 0, ['playercardsid' => $instanceid]);
        $DB->delete_records('playercards_ownership', ['playercardsid' => $instanceid]);
        self::delete_deck_cards_for_decks('playercardsid = :pid', ['pid' => $instanceid]);
        $DB->delete_records('playercards_decks', ['playercardsid' => $instanceid]);
        $DB->delete_records('playercards_attempts', ['playercardsid' => $instanceid]);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $instanceidsbycmid = self::get_instance_ids_by_cmid($contextlist);

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $instanceid = $instanceidsbycmid[$context->instanceid] ?? null;
            if ($instanceid === null) {
                continue;
            }

            $DB->set_field_select(
                'playercards_lore',
                'createdby',
                0,
                'createdby = :userid AND playercardsid = :pid',
                ['userid' => $userid, 'pid' => $instanceid]
            );
            $DB->set_field_select(
                'playercards_questions',
                'addedby',
                0,
                'addedby = :userid AND playercardsid = :pid',
                ['userid' => $userid, 'pid' => $instanceid]
            );
            $DB->delete_records('playercards_ownership', ['userid' => $userid, 'playercardsid' => $instanceid]);
            self::delete_deck_cards_for_decks(
                'userid = :userid AND playercardsid = :pid',
                ['userid' => $userid, 'pid' => $instanceid]
            );
            $DB->delete_records('playercards_decks', ['userid' => $userid, 'playercardsid' => $instanceid]);
            $DB->delete_records('playercards_attempts', ['userid' => $userid, 'playercardsid' => $instanceid]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('playercards', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $instanceid = (int) $cm->instance;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        $DB->set_field_select(
            'playercards_lore',
            'createdby',
            0,
            "createdby $insql AND playercardsid = :pid",
            array_merge($inparams, ['pid' => $instanceid])
        );
        $DB->set_field_select(
            'playercards_questions',
            'addedby',
            0,
            "addedby $insql AND playercardsid = :pid",
            array_merge($inparams, ['pid' => $instanceid])
        );
        $DB->delete_records_select(
            'playercards_ownership',
            "userid $insql AND playercardsid = :pid",
            array_merge($inparams, ['pid' => $instanceid])
        );
        self::delete_deck_cards_for_decks(
            "userid $insql AND playercardsid = :pid",
            array_merge($inparams, ['pid' => $instanceid])
        );
        $DB->delete_records_select(
            'playercards_decks',
            "userid $insql AND playercardsid = :pid",
            array_merge($inparams, ['pid' => $instanceid])
        );
        $DB->delete_records_select(
            'playercards_attempts',
            "userid $insql AND playercardsid = :pid",
            array_merge($inparams, ['pid' => $instanceid])
        );
    }
}
