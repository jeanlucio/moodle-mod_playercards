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
 * Etapa 3 of Fase 3 (SCOPE.md 16) adds Lore cards: set_lore(), activate_lore() (Info/Trap),
 * activate_quiz() (Quiz, with the AI auto-answering — see ai_quiz_answer) and
 * class_promotion(). submit_quiz_answer (SCOPE.md 7) is not built yet: the only reachable
 * activation path in V1 so far is the human activating their own Lore, which the AI
 * always "answers" itself via ai_quiz_answer's probability roll — a real pending question
 * for the human to answer only becomes reachable once the AI can activate Lore on its own
 * turn, which needs the AI play logic a later etapa builds.
 *
 * Etapa 4 adds end_turn(): a single call processes the human's Fase Final, the AI's
 * entire turn (draw, a minimal muster/attack via ai_player — see its own docblock for why
 * this is deliberately simple), and the human's own next draw, since the client has no UI
 * to drive the AI's turn step by step. A match ends (state['finished']) on a life-point
 * knockout, a deck-out, or SCOPE.md 4.9's optional turn limit — see finish_match().
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

    /** @var int Maximum hand size enforced at the end of each turn (SCOPE.md 4.9). */
    private const MAX_HAND_SIZE = 6;

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
     * Persists match state. Strips aiturnevents (ai_player::play_turn()'s log of what
     * the AI just did) before persisting — it only ever describes the single AI turn the
     * caller's own response is reporting on, never something that should resurface on a
     * later, unrelated get_state()/action call once loaded back from the cache.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param array $state Current state.
     * @return void
     */
    private static function save_state(int $cmid, int $userid, array $state): void {
        unset($state['aiturnevents']);
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
            'deckid' => (int) $deck->id,
            'difficulty' => $difficulty,
            'phase' => 'mulligan',
            'firstplayer' => $firstplayer,
            'activeplayer' => $firstplayer,
            'turnnumber' => 0,
            'finished' => false,
            'result' => '',
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
     * play logic built yet.
     *
     * If the coin toss (start_match()) put the AI first, turn 1 belongs to the AI and
     * nothing else would ever process it — the client only ever calls end_turn() to
     * close the *human's* turn, so an AI-first match would otherwise sit stuck forever on
     * "AI's turn" with no way to progress. This method processes the AI's turn 1 itself
     * (via run_ai_turn_then_open_human(), the same helper end_turn() uses for every later
     * AI turn) before returning, whenever activeplayer is 'ai' at this point.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance (for maxturns, only reachable when the
     *  AI goes first and its own turn 1 must be processed here).
     * @param string $token Match token from start_match(), guarding against a stale
     *  client acting on a match that has since been discarded/restarted.
     * @param bool $keep Whether to keep the opening hand (true) or shuffle it back and
     *  draw a fresh one of the same size (false).
     * @return array Updated match state.
     */
    public static function mulligan(int $cmid, int $userid, \stdClass $instance, string $token, bool $keep): array {
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

        // Whoever moves first skips their own turn-1 draw (SCOPE.md 4.9) — enforced by
        // run_ai_turn_then_open_human()/end_turn() only drawing when turnnumber !== 1, so
        // nothing extra needs to happen here for either side.
        $state['phase'] = 'main';
        $state['turnnumber'] = 1;
        $state['musterusedthisturn'] = false;
        $state['postureusedthisturn'] = false;

        if ($state['activeplayer'] === 'ai') {
            $state = self::reset_turn_start($state, 'ai');
            $state = self::run_ai_turn_then_open_human($cmid, $userid, $instance, $state, (int) $instance->maxturns);
            if (!empty($state['finished'])) {
                return $state;
            }
        }

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

        $zones = card_presenter::hydrate_zones(
            [
                'humanfield' => $state['humanfield'],
                'humanlore' => $state['humanlore'],
                'aifield' => $state['aifield'],
                'ailore' => $state['ailore'],
            ],
            ['humanfield', 'humanlore']
        );

        return [
            'hasmatch' => true,
            'token' => $state['token'],
            'difficulty' => $state['difficulty'],
            'phase' => $state['phase'],
            'firstplayer' => $state['firstplayer'],
            'activeplayer' => $state['activeplayer'],
            'turnnumber' => $state['turnnumber'],
            'finished' => (bool) ($state['finished'] ?? false),
            'result' => $state['result'] ?? '',
            'aiturnevents' => $state['aiturnevents'] ?? [],
            'musterusedthisturn' => (bool) ($state['musterusedthisturn'] ?? false),
            'postureusedthisturn' => (bool) ($state['postureusedthisturn'] ?? false),
            'haspendingpromotion' => isset($state['pendingpromotion']),
            'pendingpromotionbonus' => (int) ($state['pendingpromotion']['bonus'] ?? 0),
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
     * mustered Guardian can attack this same turn (real Yu-Gi-Oh has no restriction on
     * that), but cannot change battle position until the following turn — see
     * change_posture().
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
            'atkbonus' => 0,
            'defbonus' => 0,
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
     * is empty (SCOPE.md 4.5). A Guardian can attack at most once per turn — including
     * the turn it was mustered (real Yu-Gi-Oh has no "summoning sickness" restriction on
     * attacking, unlike Magic: The Gathering; only changing battle position is blocked
     * the turn a Guardian arrives — see change_posture()). The player who goes first has
     * no Battle Phase at all on turn 1 (SCOPE.md 4.9), a separate real Yu-Gi-Oh rule from
     * the one above — turnnumber === 1 can only ever be that player's own first turn,
     * whichever side that happens to be.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance (only needed to finish the match if
     *  this attack knocks either side's life points to 0 or below).
     * @param string $token Match token.
     * @param int $attackerslot Own field slot declaring the attack.
     * @param int|null $targetslot Opposing field slot to attack, or null for a direct
     *  attack (only valid when the AI has no Guardian anywhere in play).
     * @return array Updated match state.
     */
    public static function declare_attack(
        int $cmid,
        int $userid,
        \stdClass $instance,
        string $token,
        int $attackerslot,
        ?int $targetslot
    ): array {
        global $DB;

        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_active_main_phase($state);

        if ((int) $state['turnnumber'] === 1) {
            throw new \moodle_exception('error_nobattlephaseturn1', 'mod_playercards');
        }

        $attacker = self::require_own_guardian_slot($state, $attackerslot);
        if ($attacker['posture'] !== 'attack') {
            throw new \moodle_exception('error_mustbeattackposture', 'mod_playercards');
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
        [$attackeratk] = card_presenter::effective_stats($attackercard, $attacker);

        if ($targetslot === null) {
            if (self::field_has_guardian($state['aifield'])) {
                throw new \moodle_exception('error_musttargetguardian', 'mod_playercards');
            }
            $state['lifepoints']['ai'] -= combat_engine::resolve_direct_attack($attackeratk);
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
            [$defenderatk, $defenderdef] = card_presenter::effective_stats($defendercard, $defender);

            $result = combat_engine::resolve_combat(
                $attackeratk,
                $defenderatk,
                $defenderdef,
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

        $finished = self::check_lifepoint_knockout($cmid, $userid, $instance, $state);
        if ($finished !== null) {
            return $finished;
        }

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Places a Lore card from hand face-down into an empty Lore zone slot. Only limited
     * by available slots (SCOPE.md 4.1) — unlike muster, there is no once-per-turn cap.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string $token Match token.
     * @param string $handuid Uid of the Lore card in hand.
     * @param int $loreslot Target Lore slot, 0 to FIELD_SLOTS-1.
     * @return array Updated match state.
     */
    public static function set_lore(int $cmid, int $userid, string $token, string $handuid, int $loreslot): array {
        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_active_main_phase($state);

        if ($loreslot < 0 || $loreslot >= self::FIELD_SLOTS || $state['humanlore'][$loreslot] !== null) {
            throw new \moodle_exception('error_slotoccupied', 'mod_playercards');
        }

        $handindex = self::find_hand_index($state['humanhand'], $handuid);
        if ($handindex === null || $state['humanhand'][$handindex]['cardtype'] !== 'lore') {
            throw new \moodle_exception('error_invalidhandcard', 'mod_playercards');
        }

        $handcard = $state['humanhand'][$handindex];
        array_splice($state['humanhand'], $handindex, 1);
        $state['humanlore'][$loreslot] = [
            'uid' => $handcard['uid'],
            'cardtype' => 'lore',
            'cardid' => $handcard['cardid'],
            'facedown' => true,
        ];

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Activates a face-down Info or Trap Lore card (SCOPE.md 4.6): reveals its content
     * (Info only) and applies its effect unconditionally, then discards it. Quiz cards
     * must go through activate_quiz() instead.
     *
     * Not gated by whose turn it is — Lore activates at instant speed, any time
     * (SCOPE.md 4.6). V1 only reaches this from the human's own Lore zone, since the AI
     * cannot activate Lore of its own yet.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance (only needed to finish the match if
     *  this Lore card's effect knocks either side's life points to 0 or below).
     * @param string $token Match token.
     * @param int $loreslot Own Lore slot to activate.
     * @param int|null $targetslot Field slot the effect targets, required only for
     *  effects that need one (atk_buff/def_buff/destroy).
     * @return array ['state' => array, 'revealedcontent' => string].
     */
    public static function activate_lore(
        int $cmid,
        int $userid,
        \stdClass $instance,
        string $token,
        int $loreslot,
        ?int $targetslot
    ): array {
        global $DB;

        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_match_started($state);

        $slot = self::require_own_lore_slot($state, $loreslot);
        $lorecard = $DB->get_record('playercards_lore', ['id' => $slot['cardid']], '*', MUST_EXIST);

        if ($lorecard->subtype === 'quiz') {
            throw new \moodle_exception('error_useactivatequiz', 'mod_playercards');
        }

        $revealedcontent = $lorecard->subtype === 'info' ? lore_content_sampler::sample_info($lorecard) : '';

        $state = lore_effect_resolver::apply(
            $state,
            'human',
            $lorecard->effecttype,
            (int) $lorecard->effectvalue,
            $targetslot
        );
        $state['humanlore'][$loreslot] = null;

        $finished = self::check_lifepoint_knockout($cmid, $userid, $instance, $state);
        if ($finished !== null) {
            return ['state' => $finished, 'revealedcontent' => $revealedcontent];
        }

        self::save_state($cmid, $userid, $state);

        return ['state' => $state, 'revealedcontent' => $revealedcontent];
    }

    /**
     * Activates a face-down Quiz Lore card (SCOPE.md 4.6): reveals a question sampled
     * from its category and immediately resolves the AI's answer via
     * ai_quiz_answer::guess_probability(). A correct AI answer discards the card and
     * heals the AI by the card's own difficulty-scaled bonus; a wrong answer applies the
     * card's effect against the AI (via lore_effect_resolver, same as activate_lore) and
     * discards it either way.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance (only needed to finish the match if
     *  this Quiz card's outcome knocks either side's life points to 0 or below).
     * @param string $token Match token.
     * @param int $loreslot Own Lore slot to activate.
     * @param int|null $targetslot Field slot the effect targets if the AI answers wrong,
     *  required only for effects that need one.
     * @return array ['state' => array, 'questiontext' => string, 'options' => string[],
     *  'correctindex' => int, 'aicorrect' => bool, 'lpchange' => int].
     */
    public static function activate_quiz(
        int $cmid,
        int $userid,
        \stdClass $instance,
        string $token,
        int $loreslot,
        ?int $targetslot
    ): array {
        global $DB;

        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_match_started($state);

        $slot = self::require_own_lore_slot($state, $loreslot);
        $lorecard = $DB->get_record('playercards_lore', ['id' => $slot['cardid']], '*', MUST_EXIST);

        if ($lorecard->subtype !== 'quiz') {
            throw new \moodle_exception('error_notquizcard', 'mod_playercards');
        }

        $question = lore_content_sampler::sample_quiz($lorecard);
        $qtype = count($question['options']) === 2 ? 'truefalse' : 'multichoice';
        $probability = ai_quiz_answer::guess_probability($state['difficulty'], $qtype);
        $aicorrect = ai_quiz_answer::is_correct($probability, mt_rand() / mt_getrandmax());

        if ($aicorrect) {
            $lpchange = ai_quiz_answer::correct_answer_bonus($lorecard->difficulty);
            $state['lifepoints']['ai'] += $lpchange;
        } else {
            $lpchange = -(int) $lorecard->effectvalue;
            $state = lore_effect_resolver::apply(
                $state,
                'human',
                $lorecard->effecttype,
                (int) $lorecard->effectvalue,
                $targetslot
            );
        }
        $state['humanlore'][$loreslot] = null;

        $finished = self::check_lifepoint_knockout($cmid, $userid, $instance, $state);
        if ($finished !== null) {
            return [
                'state' => $finished,
                'questiontext' => $question['questiontext'],
                'options' => $question['options'],
                'correctindex' => $question['correctindex'],
                'aicorrect' => $aicorrect,
                'lpchange' => $lpchange,
            ];
        }

        self::save_state($cmid, $userid, $state);

        return [
            'state' => $state,
            'questiontext' => $question['questiontext'],
            'options' => $question['options'],
            'correctindex' => $question['correctindex'],
            'aicorrect' => $aicorrect,
            'lpchange' => $lpchange,
        ];
    }

    /**
     * Executes a Class Promotion (SCOPE.md 4.3), spending a previously-granted
     * authorization (from an Info Lore's unconditional activation, or a Quiz Lore's
     * wrong AI answer — both set state['pendingpromotion'] via lore_effect_resolver).
     * Sacrifices 2 own Guardians (at least one already in play) and permanently boosts a
     * third's ATK or DEF. If the target came from hand, its entry to the field is a
     * Special Summon: it does not consume the turn's normal muster, and — like any
     * newly-arrived Guardian — cannot change battle position this same turn, though it
     * can still attack (see change_posture()/declare_attack()).
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param string $token Match token.
     * @param array $sacrifices Exactly 2 entries, each ['source' => 'hand'|'field', 'ref' => string|int]
     *  (hand uid, or field slot index).
     * @param array $target ['source' => 'hand'|'field', 'ref' => string|int] — the Guardian to boost.
     * @param string $statchoice 'atk' or 'def'.
     * @return array Updated match state.
     */
    public static function class_promotion(
        int $cmid,
        int $userid,
        string $token,
        array $sacrifices,
        array $target,
        string $statchoice
    ): array {
        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_active_main_phase($state);

        if (!isset($state['pendingpromotion'])) {
            throw new \moodle_exception('error_promotionnotauthorized', 'mod_playercards');
        }
        if (count($sacrifices) !== 2) {
            throw new \moodle_exception('error_needtwosacrifices', 'mod_playercards');
        }
        if (!in_array($statchoice, ['atk', 'def'], true)) {
            throw new \moodle_exception('error_invalidposture', 'mod_playercards');
        }

        $fieldsacrifices = array_filter($sacrifices, static fn(array $s): bool => $s['source'] === 'field');
        if (count($fieldsacrifices) < 1) {
            throw new \moodle_exception('error_needfieldsacrifice', 'mod_playercards');
        }

        // Resolve and remove both sacrifices before touching the target, so a target
        // coming from hand can land in a slot one of them just vacated.
        foreach ($sacrifices as $sacrifice) {
            if ($sacrifice['source'] === 'field') {
                self::require_own_guardian_slot($state, (int) $sacrifice['ref']);
                $state['humanfield'][(int) $sacrifice['ref']] = null;
            } else {
                $handindex = self::find_hand_index($state['humanhand'], (string) $sacrifice['ref']);
                if ($handindex === null || $state['humanhand'][$handindex]['cardtype'] !== 'guardian') {
                    throw new \moodle_exception('error_invalidhandcard', 'mod_playercards');
                }
                array_splice($state['humanhand'], $handindex, 1);
            }
        }

        $bonus = (int) $state['pendingpromotion']['bonus'];
        $bonuskey = $statchoice === 'atk' ? 'atkbonus' : 'defbonus';

        if ($target['source'] === 'field') {
            $targetslot = (int) $target['ref'];
            $entry = self::require_own_guardian_slot($state, $targetslot);
            $entry[$bonuskey] = (int) ($entry[$bonuskey] ?? 0) + $bonus;
            $state['humanfield'][$targetslot] = $entry;
        } else {
            $handindex = self::find_hand_index($state['humanhand'], (string) $target['ref']);
            if ($handindex === null || $state['humanhand'][$handindex]['cardtype'] !== 'guardian') {
                throw new \moodle_exception('error_invalidhandcard', 'mod_playercards');
            }
            $handcard = $state['humanhand'][$handindex];
            array_splice($state['humanhand'], $handindex, 1);

            $emptyslot = self::find_empty_slot($state['humanfield']);
            if ($emptyslot === null) {
                throw new \moodle_exception('error_slotoccupied', 'mod_playercards');
            }
            $state['humanfield'][$emptyslot] = [
                'uid' => $handcard['uid'],
                'cardtype' => 'guardian',
                'cardid' => $handcard['cardid'],
                'posture' => 'attack',
                'sick' => true,
                'attackedthisturn' => false,
                'atkbonus' => $statchoice === 'atk' ? $bonus : 0,
                'defbonus' => $statchoice === 'def' ? $bonus : 0,
            ];
        }

        unset($state['pendingpromotion']);

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Ends the human's turn: closes their Fase Final (hand limit), then plays the AI's
     * entire turn (draw, ai_player::play_turn()) and opens the human's next turn (hand
     * limit for the AI, draw for the human) — all in one call, since the client has no
     * UI to drive the AI's turn step by step (SCOPE.md 7, "turno da IA processado").
     *
     * Checks for a match-ending condition after every life-point-changing or deck-
     * emptying step: a Guardian's own attack during the AI's turn, the AI's own deck-out,
     * the configured turn limit (SCOPE.md 4.9), and the human's own deck-out. Whichever
     * triggers first ends the match immediately — later steps never run.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance (for maxturns and finish_match()).
     * @param string $token Match token.
     * @return array Updated match state.
     */
    public static function end_turn(int $cmid, int $userid, \stdClass $instance, string $token): array {
        $state = self::load_state($cmid, $userid);
        self::validate_token($state, $token);
        self::require_active_main_phase($state);

        $state = self::trim_hand($state, 'humanhand');

        $state['turnnumber']++;
        $state['activeplayer'] = 'ai';
        $state = self::reset_turn_start($state, 'ai');

        $state = self::run_ai_turn_then_open_human($cmid, $userid, $instance, $state, (int) $instance->maxturns);
        if (!empty($state['finished'])) {
            return $state;
        }

        self::save_state($cmid, $userid, $state);

        return $state;
    }

    /**
     * Plays out the AI's entire turn — from whatever turnnumber/activeplayer the caller
     * already set — and opens the human's next turn. Shared by end_turn() (closing an
     * ordinary human turn) and mulligan() (when the AI won the coin toss and its very
     * first turn needs to be processed immediately, since no other entry point ever
     * would — see mulligan()'s own docblock).
     *
     * Draws for whichever side is opening a turn are skipped whenever that turn's own
     * turnnumber is exactly 1 — the only time this can be true is a side's own first
     * turn, matching "whoever moves first skips their own turn-1 draw" (SCOPE.md 4.9)
     * regardless of which side that happens to be.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance.
     * @param array $state Current state, with activeplayer/turnnumber already set to the
     *  AI's turn being played out.
     * @param int $maxturns Configured turn limit, 0 for unlimited.
     * @return array Updated match state — check ['finished'] before using further, since
     *  a triggered end condition already saved and returned early.
     */
    private static function run_ai_turn_then_open_human(
        int $cmid,
        int $userid,
        \stdClass $instance,
        array $state,
        int $maxturns
    ): array {
        if ($maxturns > 0 && $state['turnnumber'] > $maxturns) {
            return self::finish_match($cmid, $userid, $instance, $state, self::result_by_lifepoints($state));
        }
        if (count($state['aideck']) === 0) {
            return self::finish_match($cmid, $userid, $instance, $state, 'win');
        }
        if ($state['turnnumber'] !== 1) {
            $state['aihand'][] = array_shift($state['aideck']);
        }

        $state = ai_player::play_turn($state);
        $finished = self::check_lifepoint_knockout($cmid, $userid, $instance, $state);
        if ($finished !== null) {
            return $finished;
        }

        $state = self::trim_hand($state, 'aihand');

        $state['turnnumber']++;
        $state['activeplayer'] = 'human';
        $state = self::reset_turn_start($state, 'human');

        if ($maxturns > 0 && $state['turnnumber'] > $maxturns) {
            return self::finish_match($cmid, $userid, $instance, $state, self::result_by_lifepoints($state));
        }
        if (count($state['humandeck']) === 0) {
            return self::finish_match($cmid, $userid, $instance, $state, 'loss');
        }
        if ($state['turnnumber'] !== 1) {
            $state['humanhand'][] = array_shift($state['humandeck']);
        }

        return $state;
    }

    /**
     * Resolves a turn-limit tie by remaining life points (SCOPE.md 4.9). The schema only
     * has 'win'/'loss' for {playercards_attempts}.result — an exact tie (no real winner)
     * is recorded as a loss, since it does not represent a genuine win worth completion
     * credit either way; a V1 simplification, not a hard ruling.
     *
     * @param array $state Current state.
     * @return string 'win' or 'loss'.
     */
    private static function result_by_lifepoints(array $state): string {
        return $state['lifepoints']['human'] > $state['lifepoints']['ai'] ? 'win' : 'loss';
    }

    /**
     * Ends the match immediately if this action just knocked either side's life points
     * to 0 or below (SCOPE.md 4.5: victory is reducing the opponent's life points to 0).
     * end_turn()/mulligan() already check this after the AI's own attack step via
     * run_ai_turn_then_open_human() — this is the equivalent check for every other
     * action that can change life points outside that flow: the human's own
     * declare_attack(), and a Lore card's lp_damage/lp_heal effect (activate_lore(),
     * activate_quiz()). Without it here, a match could carry on indefinitely past a
     * losing side's life points going negative.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance.
     * @param array $state Current state, after the life-point change has been applied.
     * @return array|null The finished match state if this action ended the match, or
     *  null if the match is still ongoing.
     */
    private static function check_lifepoint_knockout(int $cmid, int $userid, \stdClass $instance, array $state): ?array {
        if ($state['lifepoints']['ai'] <= 0) {
            return self::finish_match($cmid, $userid, $instance, $state, 'win');
        }
        if ($state['lifepoints']['human'] <= 0) {
            return self::finish_match($cmid, $userid, $instance, $state, 'loss');
        }
        return null;
    }

    /**
     * Resets whichever side's turn is starting: clears summoning sickness and the
     * once-per-turn attack flag on every one of their own Guardians in play (SCOPE.md
     * 4.2), and — only for the human, since the AI's whole turn resolves within a single
     * end_turn() call with no need to persist its own per-turn flags — the muster/
     * posture once-per-turn flags.
     *
     * @param array $state Current state.
     * @param string $side 'human' or 'ai'.
     * @return array Updated state.
     */
    private static function reset_turn_start(array $state, string $side): array {
        $fieldkey = $side . 'field';
        foreach ($state[$fieldkey] as $index => $slot) {
            if ($slot !== null) {
                $state[$fieldkey][$index]['sick'] = false;
                $state[$fieldkey][$index]['attackedthisturn'] = false;
            }
        }

        if ($side === 'human') {
            $state['musterusedthisturn'] = false;
            $state['postureusedthisturn'] = false;
        }

        return $state;
    }

    /**
     * Trims a hand down to the maximum allowed size at the end of a turn (SCOPE.md 4.9).
     * Discards from the end of the hand array — V1 has no "choose what to discard" UI/WS
     * yet, so this is an automatic, deterministic simplification.
     *
     * @param array $state Current state.
     * @param string $handkey 'humanhand' or 'aihand'.
     * @return array Updated state.
     */
    private static function trim_hand(array $state, string $handkey): array {
        $state[$handkey] = array_slice($state[$handkey], 0, self::MAX_HAND_SIZE);
        return $state;
    }

    /**
     * Ends the match: records the {playercards_attempts} row, updates the gradebook and
     * recomputes completion — mirroring mod_playercross\local\round_service::
     * finish_round()'s equivalent block.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param \stdClass $instance Activity instance.
     * @param array $state Current state.
     * @param string $result 'win' or 'loss'.
     * @return array Updated (finished) match state.
     */
    private static function finish_match(
        int $cmid,
        int $userid,
        \stdClass $instance,
        array $state,
        string $result
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/playercards/lib.php');

        $state['finished'] = true;
        $state['result'] = $result;

        $DB->insert_record('playercards_attempts', (object) [
            'playercardsid' => $instance->id,
            'userid' => $userid,
            'deckid' => (int) $state['deckid'],
            'aidifficulty' => $state['difficulty'],
            'result' => $result,
            'lpremaining' => max(0, (int) $state['lifepoints']['human']),
            'turnsplayed' => (int) $state['turnnumber'],
            'score' => $result === 'win' ? 100.0 : 0.0,
            'timecreated' => time(),
        ]);

        playercards_update_grades($instance, $userid);

        $cm = get_coursemodule_from_id('playercards', $cmid, 0, false, MUST_EXIST);
        $course = get_course($instance->course);
        $completioninfo = new \completion_info($course);
        if ($completioninfo->is_enabled($cm)) {
            $completioninfo->update_state($cm, COMPLETION_COMPLETE, $userid);
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
        if (!empty($state['finished'])) {
            throw new \moodle_exception('error_matchfinished', 'mod_playercards');
        }
        if ($state['phase'] !== 'main' || $state['activeplayer'] !== 'human') {
            throw new \moodle_exception('error_notyourturn', 'mod_playercards');
        }
    }

    /**
     * Guards a Lore activation, which is instant-speed and not restricted to the
     * activator's own turn (SCOPE.md 4.6) — only requires the match to have actually
     * started (past the mulligan).
     *
     * @param array $state Current state.
     * @return void
     */
    private static function require_match_started(array $state): void {
        if (!empty($state['finished'])) {
            throw new \moodle_exception('error_matchfinished', 'mod_playercards');
        }
        if ($state['phase'] !== 'main') {
            throw new \moodle_exception('error_notyourturn', 'mod_playercards');
        }
    }

    /**
     * Validates that a given own Lore slot holds a face-down card, and returns it.
     *
     * @param array $state Current state.
     * @param int $loreslot Lore slot to check.
     * @return array The Lore entry.
     */
    private static function require_own_lore_slot(array $state, int $loreslot): array {
        if ($loreslot < 0 || $loreslot >= self::FIELD_SLOTS || $state['humanlore'][$loreslot] === null) {
            throw new \moodle_exception('error_emptyslot', 'mod_playercards');
        }
        return $state['humanlore'][$loreslot];
    }

    /**
     * Finds the first empty slot in a field zone.
     *
     * @param array $field Field slots (null or Guardian entries).
     * @return int|null
     */
    private static function find_empty_slot(array $field): ?int {
        foreach ($field as $index => $slot) {
            if ($slot === null) {
                return $index;
            }
        }
        return null;
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
