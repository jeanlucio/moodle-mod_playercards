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
 * Match state service.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

use cache;
use cache_store;

/**
 * Owns every match-state mutation for a PlayerCards vs. AI match: starting a match,
 * resolving the mulligan decision and reading the current state.
 *
 * Mirrors the mod_playercross/mod_playerwords pattern (round_service): the match itself
 * lives only in session state (Cache API, MODE_SESSION — SCOPE.md 5 note on "no
 * in-progress match table"), keyed per course module so a user with more than one
 * PlayerCards instance open keeps each match separate. Only a finished match becomes a
 * {playercards_attempts} row (a later stage of Fase 3/4, not built yet).
 *
 * Etapa 2 of Fase 3 (SCOPE.md 16) adds Guardian muster (normal and sacrificial), posture
 * changes and combat. Lore actions (set_lore/activate_lore/activate_quiz/
 * class_promotion) arrive in Etapa 3 — until then the Lore zone stays empty, and every
 * mutating method here only ever needs to reason about Guardian field slots.
 *
 * V1 does not enforce the Principal/Combate phase split from SCOPE.md 4.1 as two
 * distinct states: muster_guardian(), change_posture() and declare_attack() are all
 * available throughout the single 'main' phase, in any order, each already gated by its
 * own once-per-turn rule (musterusedthisturn, postureusedthisturn, a Guardian's own
 * attackedthisturn). Against a solo AI opponent, strict phase ordering mostly matters for
 * pacing/clarity rather than fairness — revisit if PvP (V2) ever needs the stricter split.
 */
class match_service {
    /** @var int Cards drawn into each player's opening hand. */
    private const HAND_SIZE = 5;

    /** @var int Starting life points per player (SCOPE.md 4.8, fixed in V1). */
    private const LIFE_POINTS = 10000;

    /** @var int Guardian field slots per player (SCOPE.md 8: 2 rows x 5 slots). */
    private const FIELD_SLOTS = 5;

    /** @var int Maximum Guardian level musterable for free, without a sacrifice (SCOPE.md 4.2). */
    private const FREE_MUSTER_MAX_LEVEL = 3;

    /** @var string[] Valid AI difficulty levels. */
    private const DIFFICULTIES = ['easy', 'normal', 'hard'];

    /** @var string[] Valid Guardian postures. */
    private const POSTURES = ['attack', 'defense'];

    /**
     * Returns the session-scoped cache backing every match state.
     *
     * @return cache
     */
    private static function get_cache(): cache {
        return cache::make_from_params(cache_store::MODE_SESSION, 'mod_playercards', 'matchstate');
    }

    /**
     * Builds the cache key for one user's match in one course module.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return string
     */
    private static function session_key(int $cmid, int $userid): string {
        return $cmid . '_' . $userid;
    }

    /**
     * Loads the current match state, or the default (no match in progress) shape.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array
     */
    public static function load_state(int $cmid, int $userid): array {
        $state = self::get_cache()->get(self::session_key($cmid, $userid));
        if ($state === false || !is_array($state) || empty($state['hasmatch'])) {
            return self::default_state();
        }
        return $state;
    }

    /**
     * Persists match state.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param array $state Current state.
     * @return void
     */
    private static function save_state(int $cmid, int $userid, array $state): void {
        self::get_cache()->set(self::session_key($cmid, $userid), $state);
    }

    /**
     * The shape returned when no match is in progress.
     *
     * @return array
     */
    private static function default_state(): array {
        return ['hasmatch' => false];
    }

    /**
     * Starts a new match vs. the AI, discarding any previous match state for this course
     * module/user pair.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string $difficulty easy | normal | hard.
     * @return array The new match state.
     */
    public static function start_match(\stdClass $instance, int $cmid, int $userid, string $difficulty): array {
        global $DB;

        if (!in_array($difficulty, self::DIFFICULTIES, true)) {
            throw new \moodle_exception('error_invaliddifficulty', 'mod_playercards');
        }

        $deck = $DB->get_record('playercards_decks', [
            'playercardsid' => $instance->id,
            'userid' => $userid,
            'active' => 1,
        ]);
        if (!$deck) {
            throw new \moodle_exception('error_noactivedeck', 'mod_playercards');
        }

        $humandeck = self::assign_uids(self::expand_deck_cards((int) $deck->id), 'h');
        $aideck = self::assign_uids(ai_deck_builder::build_deck((int) $instance->id, $difficulty), 'a');

        shuffle($humandeck);
        shuffle($aideck);

        $humanhand = array_splice($humandeck, 0, self::HAND_SIZE);
        $aihand = array_splice($aideck, 0, self::HAND_SIZE);

        $firstplayer = (random_int(0, 1) === 0) ? 'human' : 'ai';

        $state = [
            'hasmatch' => true,
            'token' => \core\uuid::generate(),
            'difficulty' => $difficulty,
            'phase' => 'mulligan',
            'firstplayer' => $firstplayer,
            'activeplayer' => $firstplayer,
            'turnnumber' => 0,
            'lifepoints' => ['human' => self::LIFE_POINTS, 'ai' => self::LIFE_POINTS],
            'humandeck' => $humandeck,
            'aideck' => $aideck,
            'humanhand' => $humanhand,
            'aihand' => $aihand,
            'humanfield' => array_fill(0, self::FIELD_SLOTS, null),
            'humanlore' => array_fill(0, self::FIELD_SLOTS, null),
            'aifield' => array_fill(0, self::FIELD_SLOTS, null),
            'ailore' => array_fill(0, self::FIELD_SLOTS, null),
        ];

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Resolves the human player's once-only mulligan decision and advances the match to
     * turn 1. The AI never mulligans in V1 — a deliberate simplification (SCOPE.md 17)
     * since a meaningful AI mulligan heuristic has no clear value without the AI's own
     * play logic (Etapa 2/3) built yet.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string $token Match token from start_match(), guarding against a stale
     *  client acting on a match that has since been discarded/restarted.
     * @param bool $keep Whether to keep the opening hand (true) or shuffle it back and
     *  draw a fresh one of the same size (false).
     * @return array Updated match state.
     */
    public static function mulligan(int $cmid, int $userid, string $token, bool $keep): array {
        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);

        if ($state['phase'] !== 'mulligan') {
            throw new \moodle_exception('error_notmulliganphase', 'mod_playercards');
        }

        if (!$keep) {
            $state['humandeck'] = array_merge($state['humandeck'], $state['humanhand']);
            shuffle($state['humandeck']);
            $state['humanhand'] = array_splice($state['humandeck'], 0, self::HAND_SIZE);
        }

        // Whoever moves first skips their own turn-1 draw (SCOPE.md 4.9); the second
        // player's normal draw, when their own first turn eventually comes around, is
        // just their ordinary per-turn draw step — nothing extra to apply here. Both
        // still need the full turn-based draw/muster/combat loop (Etapa 4) before either
        // player's turn can actually progress past this point.
        $state['phase'] = 'main';
        $state['turnnumber'] = 1;
        $state['musterusedthisturn'] = false;
        $state['postureusedthisturn'] = false;

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Returns the current match state, optionally validating it against a known token.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string|null $token Match token to validate, or null to skip validation
     *  (used by the page load path, which does not yet know a token).
     * @return array
     */
    public static function get_state(int $cmid, int $userid, ?string $token): array {
        $state = self::load_state($cmid, $userid);
        if ($token !== null && !empty($state['hasmatch'])) {
            self::validate_token($state, $token);
        }
        return $state;
    }

    /**
     * Converts internal match state into the shape sent to the client: the human
     * player's own hand is hydrated with full card metadata, while the AI's hand and
     * both decks are exposed only as counts — hidden information the client must never
     * receive in detail. Field/Lore zone slots are hydrated with display metadata,
     * batched in one pass across all four zones.
     *
     * @param array $state Internal match state.
     * @return array Client-facing state.
     */
    public static function export_state(array $state): array {
        if (empty($state['hasmatch'])) {
            return ['hasmatch' => false];
        }

        $zones = card_presenter::hydrate_zones([
            'humanfield' => $state['humanfield'],
            'humanlore' => $state['humanlore'],
            'aifield' => $state['aifield'],
            'ailore' => $state['ailore'],
        ]);

        return [
            'hasmatch' => true,
            'token' => $state['token'],
            'difficulty' => $state['difficulty'],
            'phase' => $state['phase'],
            'firstplayer' => $state['firstplayer'],
            'activeplayer' => $state['activeplayer'],
            'turnnumber' => $state['turnnumber'],
            'musterusedthisturn' => (bool) ($state['musterusedthisturn'] ?? false),
            'postureusedthisturn' => (bool) ($state['postureusedthisturn'] ?? false),
            'lifepoints' => $state['lifepoints'],
            'humanhand' => card_presenter::hydrate($state['humanhand']),
            'aihandcount' => count($state['aihand']),
            'humandeckcount' => count($state['humandeck']),
            'aideckcount' => count($state['aideck']),
            'humanfield' => $zones['humanfield'],
            'humanlore' => $zones['humanlore'],
            'aifield' => $zones['aifield'],
            'ailore' => $zones['ailore'],
        ];
    }

    /**
     * Musters a Guardian from hand onto an empty field slot: free for level 1-3
     * (SCOPE.md 4.2), or by sacrificing one own level 1-3 Guardian already in play for
     * level 4-5. Limited to once per turn regardless of which path is used. The newly
     * mustered Guardian cannot attack or change posture this turn (summoning sickness).
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string $token Match token.
     * @param string $handuid Uid of the Guardian card in hand to muster.
     * @param int $fieldslot Target field slot, 0 to FIELD_SLOTS-1.
     * @param string $posture attack | defense, chosen at muster time.
     * @param int|null $sacrificefieldslot Own field slot to sacrifice, required only when
     *  musterng a level 4-5 Guardian.
     * @return array Updated match state.
     */
    public static function muster_guardian(
        int $cmid,
        int $userid,
        string $token,
        string $handuid,
        int $fieldslot,
        string $posture,
        ?int $sacrificefieldslot = null
    ): array {
        global $DB;

        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_active_main_phase($state);

        if (!empty($state['musterusedthisturn'])) {
            throw new \moodle_exception('error_musteralreadyused', 'mod_playercards');
        }

        if (!in_array($posture, self::POSTURES, true)) {
            throw new \moodle_exception('error_invalidposture', 'mod_playercards');
        }

        if ($fieldslot < 0 || $fieldslot >= self::FIELD_SLOTS || $state['humanfield'][$fieldslot] !== null) {
            throw new \moodle_exception('error_slotoccupied', 'mod_playercards');
        }

        $handindex = self::find_hand_index($state['humanhand'], $handuid);
        if ($handindex === null || $state['humanhand'][$handindex]['cardtype'] !== 'guardian') {
            throw new \moodle_exception('error_invalidhandcard', 'mod_playercards');
        }

        $handcard = $state['humanhand'][$handindex];
        $level = (int) $DB->get_field('playercards_guardians', 'level', ['id' => $handcard['cardid']], MUST_EXIST);

        if ($level <= self::FREE_MUSTER_MAX_LEVEL) {
            if ($sacrificefieldslot !== null) {
                throw new \moodle_exception('error_sacrificenotallowed', 'mod_playercards');
            }
        } else {
            if ($sacrificefieldslot === null) {
                throw new \moodle_exception('error_sacrificerequired', 'mod_playercards');
            }
            $sacrifice = self::require_own_guardian_slot($state, $sacrificefieldslot);
            $sacrificelevel = (int) $DB->get_field(
                'playercards_guardians',
                'level',
                ['id' => $sacrifice['cardid']],
                MUST_EXIST
            );
            if ($sacrificelevel > self::FREE_MUSTER_MAX_LEVEL) {
                throw new \moodle_exception('error_invalidsacrifice', 'mod_playercards');
            }
            $state['humanfield'][$sacrificefieldslot] = null;
        }

        array_splice($state['humanhand'], $handindex, 1);
        $state['humanfield'][$fieldslot] = [
            'uid' => $handcard['uid'],
            'cardtype' => 'guardian',
            'cardid' => $handcard['cardid'],
            'posture' => $posture,
            'sick' => true,
            'attackedthisturn' => false,
        ];
        $state['musterusedthisturn'] = true;

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Changes the posture of one own Guardian already in play. Limited to once per turn,
     * and never on a Guardian mustered this same turn (SCOPE.md 4.4).
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string $token Match token.
     * @param int $fieldslot Own field slot, 0 to FIELD_SLOTS-1.
     * @return array Updated match state.
     */
    public static function change_posture(int $cmid, int $userid, string $token, int $fieldslot): array {
        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_active_main_phase($state);

        if (!empty($state['postureusedthisturn'])) {
            throw new \moodle_exception('error_posturealreadyused', 'mod_playercards');
        }

        $guardian = self::require_own_guardian_slot($state, $fieldslot);
        if (!empty($guardian['sick'])) {
            throw new \moodle_exception('error_summoningsickness', 'mod_playercards');
        }

        $state['humanfield'][$fieldslot]['posture'] = $guardian['posture'] === 'attack' ? 'defense' : 'attack';
        $state['postureusedthisturn'] = true;

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Declares an attack from one own Guardian in Offensive posture against either an
     * opposing field slot, or directly against the AI's life points when its whole field
     * is empty (SCOPE.md 4.5). A Guardian can attack at most once per turn, and never the
     * turn it was mustered.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string $token Match token.
     * @param int $attackerslot Own field slot declaring the attack.
     * @param int|null $targetslot Opposing field slot to attack, or null for a direct
     *  attack (only valid when the AI has no Guardian anywhere in play).
     * @return array Updated match state.
     */
    public static function declare_attack(
        int $cmid,
        int $userid,
        string $token,
        int $attackerslot,
        ?int $targetslot
    ): array {
        global $DB;

        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_active_main_phase($state);

        $attacker = self::require_own_guardian_slot($state, $attackerslot);
        if ($attacker['posture'] !== 'attack') {
            throw new \moodle_exception('error_mustbeattackposture', 'mod_playercards');
        }
        if (!empty($attacker['sick'])) {
            throw new \moodle_exception('error_summoningsickness', 'mod_playercards');
        }
        if (!empty($attacker['attackedthisturn'])) {
            throw new \moodle_exception('error_alreadyattacked', 'mod_playercards');
        }

        $attackercard = $DB->get_record(
            'playercards_guardians',
            ['id' => $attacker['cardid']],
            '*',
            MUST_EXIST
        );

        if ($targetslot === null) {
            if (self::field_has_guardian($state['aifield'])) {
                throw new \moodle_exception('error_musttargetguardian', 'mod_playercards');
            }
            $state['lifepoints']['ai'] -= combat_engine::resolve_direct_attack((int) $attackercard->atk);
        } else {
            if (
                $targetslot < 0
                || $targetslot >= self::FIELD_SLOTS
                || $state['aifield'][$targetslot] === null
            ) {
                throw new \moodle_exception('error_invalidtarget', 'mod_playercards');
            }

            $defender = $state['aifield'][$targetslot];
            $defendercard = $DB->get_record(
                'playercards_guardians',
                ['id' => $defender['cardid']],
                '*',
                MUST_EXIST
            );

            $result = combat_engine::resolve_combat(
                (int) $attackercard->atk,
                (int) $defendercard->atk,
                (int) $defendercard->def,
                $defender['posture'] === 'attack'
            );

            if ($result['attackerdestroyed']) {
                $state['humanfield'][$attackerslot] = null;
            }
            if ($result['defenderdestroyed']) {
                $state['aifield'][$targetslot] = null;
            }
            $state['lifepoints']['human'] -= $result['attackerdamage'];
            $state['lifepoints']['ai'] -= $result['defenderdamage'];
        }

        if ($state['humanfield'][$attackerslot] !== null) {
            $state['humanfield'][$attackerslot]['attackedthisturn'] = true;
        }

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Guards a mutating call to a Guardian action against the wrong turn/phase — every
     * one of muster_guardian()/change_posture()/declare_attack() needs exactly this
     * check.
     *
     * @param array $state Current state.
     * @return void
     */
    private static function require_active_main_phase(array $state): void {
        if ($state['phase'] !== 'main' || $state['activeplayer'] !== 'human') {
            throw new \moodle_exception('error_notyourturn', 'mod_playercards');
        }
    }

    /**
     * Finds a hand card's index by its uid.
     *
     * @param array $hand Hand entries.
     * @param string $uid Uid to find.
     * @return int|null
     */
    private static function find_hand_index(array $hand, string $uid): ?int {
        foreach ($hand as $index => $card) {
            if ($card['uid'] === $uid) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Validates that a given own field slot holds a Guardian, and returns it.
     *
     * @param array $state Current state.
     * @param int $fieldslot Field slot to check.
     * @return array The Guardian entry.
     */
    private static function require_own_guardian_slot(array $state, int $fieldslot): array {
        if ($fieldslot < 0 || $fieldslot >= self::FIELD_SLOTS || $state['humanfield'][$fieldslot] === null) {
            throw new \moodle_exception('error_emptyslot', 'mod_playercards');
        }
        return $state['humanfield'][$fieldslot];
    }

    /**
     * Whether a field zone has at least one Guardian in play.
     *
     * @param array $field Field slots (null or Guardian entries).
     * @return bool
     */
    private static function field_has_guardian(array $field): bool {
        foreach ($field as $slot) {
            if ($slot !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Expands a saved deck's {playercards_deck_cards} rows into one entry per physical
     * copy.
     *
     * @param int $deckid Deck id.
     * @return array List of ['cardtype' => 'guardian'|'lore', 'cardid' => int].
     */
    private static function expand_deck_cards(int $deckid): array {
        global $DB;

        $entries = [];
        foreach ($DB->get_records('playercards_deck_cards', ['deckid' => $deckid]) as $row) {
            for ($i = 0; $i < (int) $row->quantity; $i++) {
                $entries[] = ['cardtype' => $row->cardtype, 'cardid' => (int) $row->cardid];
            }
        }
        return $entries;
    }

    /**
     * Tags each card entry with a unique-for-this-match identifier, assigned once per
     * physical copy for the whole match's lifetime — a card keeps the same uid whether it
     * is currently in the deck, the hand, or (in later etapas) the field.
     *
     * @param array $entries Card entries.
     * @param string $prefix Distinguishes the human's copies from the AI's ('h'/'a').
     * @return array The same entries, each with an added 'uid' key.
     */
    private static function assign_uids(array $entries, string $prefix): array {
        foreach ($entries as $index => &$entry) {
            $entry['uid'] = $prefix . ($index + 1);
        }
        unset($entry);
        return $entries;
    }

    /**
     * Guards a mutating call against a stale or forged token.
     *
     * @param array $state Current state.
     * @param string $token Token to validate.
     * @return void
     */
    private static function validate_token(array $state, string $token): void {
        if (empty($state['hasmatch']) || ($state['token'] ?? null) !== $token) {
            throw new \moodle_exception('error_invalidmatchtoken', 'mod_playercards');
        }
    }
}
