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
 * Minimal AI opponent turn logic.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Plays one full AI turn: a single free (level 1-3) muster if possible, then an attack
 * with every eligible Guardian already in play whose attack would not backfire (see
 * attack()'s own docblock). Deliberately simple for V1 — no sacrificial muster, no Lore
 * activation (the AI cannot activate Lore of its own yet, see match_service's class
 * docblock), no posture changes. Runs entirely within one match_service::end_turn()
 * call, since the human client has no UI to drive the AI's own turn — there is nothing
 * to persist mid-turn for the AI the way musterusedthisturn/postureusedthisturn track
 * the human's turn.
 *
 * Every muster/attack this turn is recorded into state['aiturnevents'] (cleared at the
 * start of every play_turn() call, and stripped by match_service::save_state() before
 * persisting — it only ever describes the AI turn the current response is reporting on,
 * never a stale one from before) so the client can show the human what just happened —
 * otherwise the entire AI turn resolves silently server-side with no way to tell what
 * the AI did (SCOPE.md 17).
 */
class ai_player {
    /** @var int Maximum Guardian level the AI musters for free (SCOPE.md 4.2). */
    private const FREE_MUSTER_MAX_LEVEL = 3;

    /**
     * Plays the AI's entire turn. Mustering is always allowed, but the attack step is
     * skipped entirely when this is turn 1 — the player who goes first has no Battle
     * Phase at all on their very first turn (SCOPE.md 4.9), a real Yu-Gi-Oh rule; since
     * turnnumber can only ever be 1 during whichever side's own first turn, this single
     * check correctly covers the AI going first just as much as the human.
     *
     * @param array $state Match state, with activeplayer already set to 'ai'.
     * @return array Updated match state, with a fresh state['aiturnevents'] describing
     *  every muster/attack this call made.
     */
    public static function play_turn(array $state): array {
        $state['aiturnevents'] = [];
        $state = self::muster($state);
        if ((int) $state['turnnumber'] !== 1) {
            $state = self::attack($state);
        }
        return $state;
    }

    /**
     * Musters the first level 1-3 Guardian in hand into the first empty field slot, if
     * any. Only one muster per turn, same as the human (SCOPE.md 4.2).
     *
     * @param array $state Match state.
     * @return array Updated match state.
     */
    private static function muster(array $state): array {
        global $DB;

        $emptyslot = self::find_empty_slot($state['aifield']);
        if ($emptyslot === null) {
            return $state;
        }

        foreach ($state['aihand'] as $index => $card) {
            if ($card['cardtype'] !== 'guardian') {
                continue;
            }

            $guardian = $DB->get_record('playercards_guardians', ['id' => $card['cardid']], '*', MUST_EXIST);
            if ((int) $guardian->level > self::FREE_MUSTER_MAX_LEVEL) {
                continue;
            }

            array_splice($state['aihand'], $index, 1);
            $state['aifield'][$emptyslot] = [
                'uid' => $card['uid'],
                'cardtype' => 'guardian',
                'cardid' => $card['cardid'],
                'posture' => 'attack',
                'sick' => true,
                'attackedthisturn' => false,
                'atkbonus' => 0,
                'defbonus' => 0,
            ];
            $state['aiturnevents'][] = [
                'type' => 'muster',
                'cardname' => $guardian->name,
                'targetname' => '',
                'damage' => 0,
                'defenderdestroyed' => false,
            ];
            break;
        }

        return $state;
    }

    /**
     * Attacks with every AI Guardian eligible to (Attack posture, has not already
     * attacked this turn — including one just mustered this same turn by muster() above,
     * since real Yu-Gi-Oh has no restriction on attacking the turn a monster is
     * Summoned), against the first human Guardian in play, or directly if the human's
     * field is empty (SCOPE.md 4.5).
     *
     * A direct attack is always attempted (pure upside, no defender to lose against).
     * Against a defender, the AI first works out the combat_engine result and skips the
     * attack entirely — leaving the Guardian idle this turn — whenever it would destroy
     * or damage the attacker itself (SCOPE.md 17): found live (a real match, not a test)
     * repeatedly attacking a stronger defender for no gain and taking the ATK/DEF
     * difference as self-inflicted damage every single time. This is still not real
     * target selection — it only ever considers the one fixed target
     * find_first_occupied() already picked — just a minimal check of whether attacking
     * that target is worth it at all.
     *
     * @param array $state Match state.
     * @return array Updated match state.
     */
    private static function attack(array $state): array {
        global $DB;

        foreach ($state['aifield'] as $slot => $attacker) {
            if (
                $attacker === null
                || $attacker['posture'] !== 'attack'
                || !empty($attacker['attackedthisturn'])
            ) {
                continue;
            }

            $attackercard = $DB->get_record('playercards_guardians', ['id' => $attacker['cardid']], '*', MUST_EXIST);
            [$attackeratk] = card_presenter::effective_stats($attackercard, $attacker);

            $targetslot = self::find_first_occupied($state['humanfield']);
            if ($targetslot === null) {
                $damage = combat_engine::resolve_direct_attack($attackeratk);
                $state['lifepoints']['human'] -= $damage;
                $state['aiturnevents'][] = [
                    'type' => 'attackdirect',
                    'cardname' => $attackercard->name,
                    'targetname' => '',
                    'damage' => $damage,
                    'defenderdestroyed' => false,
                ];
            } else {
                $defender = $state['humanfield'][$targetslot];
                $defendercard = $DB->get_record('playercards_guardians', ['id' => $defender['cardid']], '*', MUST_EXIST);
                [$defenderatk, $defenderdef] = card_presenter::effective_stats($defendercard, $defender);

                $result = combat_engine::resolve_combat(
                    $attackeratk,
                    $defenderatk,
                    $defenderdef,
                    $defender['posture'] === 'attack'
                );

                if ($result['attackerdestroyed'] || $result['attackerdamage'] > 0) {
                    continue;
                }

                if ($result['defenderdestroyed']) {
                    $state['humanfield'][$targetslot] = null;
                }
                $state['lifepoints']['human'] -= $result['defenderdamage'];
                $state['aiturnevents'][] = [
                    'type' => 'attackcombat',
                    'cardname' => $attackercard->name,
                    'targetname' => $defendercard->name,
                    'damage' => $result['defenderdamage'],
                    'defenderdestroyed' => $result['defenderdestroyed'],
                ];
            }

            if ($state['aifield'][$slot] !== null) {
                $state['aifield'][$slot]['attackedthisturn'] = true;
            }
        }

        return $state;
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
     * Finds the first occupied slot in a field zone.
     *
     * @param array $field Field slots (null or Guardian entries).
     * @return int|null
     */
    private static function find_first_occupied(array $field): ?int {
        foreach ($field as $index => $slot) {
            if ($slot !== null) {
                return $index;
            }
        }
        return null;
    }
}
