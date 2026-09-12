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
 * with every eligible Guardian already in play. Deliberately simple for V1 — no
 * sacrificial muster, no Lore activation (the AI cannot activate Lore of its own yet,
 * see match_service's class docblock), no posture changes. Runs entirely within one
 * match_service::end_turn() call, since the human client has no UI to drive the AI's own
 * turn — there is nothing to persist mid-turn for the AI the way musterusedthisturn/
 * postureusedthisturn track the human's turn.
 */
class ai_player {
    /** @var int Maximum Guardian level the AI musters for free (SCOPE.md 4.2). */
    private const FREE_MUSTER_MAX_LEVEL = 3;

    /**
     * Plays the AI's entire turn.
     *
     * @param array $state Match state, with activeplayer already set to 'ai'.
     * @return array Updated match state.
     */
    public static function play_turn(array $state): array {
        $state = self::muster($state);
        $state = self::attack($state);
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

            $level = (int) $DB->get_field('playercards_guardians', 'level', ['id' => $card['cardid']], MUST_EXIST);
            if ($level > self::FREE_MUSTER_MAX_LEVEL) {
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
                $state['lifepoints']['human'] -= combat_engine::resolve_direct_attack($attackeratk);
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

                if ($result['attackerdestroyed']) {
                    $state['aifield'][$slot] = null;
                }
                if ($result['defenderdestroyed']) {
                    $state['humanfield'][$targetslot] = null;
                }
                $state['lifepoints']['ai'] -= $result['attackerdamage'];
                $state['lifepoints']['human'] -= $result['defenderdamage'];
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
