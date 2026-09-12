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
 * Resolves a Lore card's effect against match state.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Applies one of the known `effecttype` identifiers (SCOPE.md 4.6) to match state. Each
 * effect type has a fixed target kind — never chosen per-card, never varying by subtype:
 * a given effecttype always means the same thing whether it fires from an Info, Quiz or
 * Trap card. Subtype only decides *when* the effect fires (Info/Trap: unconditionally;
 * Quiz: only when the answering side gets the question wrong — SCOPE.md 4.6) and, for
 * `enable_promotion`, that only Info/Quiz may carry it at all (SCOPE.md 4.3).
 *
 * Stat buffs (atk_buff/def_buff) are permanent in this build — SCOPE.md 4.6's prose
 * calls them "temporary", but no turn-boundary exists yet to expire anything against
 * (Etapa 4 introduces end_turn). Revisit once that exists if a real temporary duration is
 * wanted; treated as a V1 simplification, not a final ruling. Class Promotion's own bonus
 * (SCOPE.md 4.3) is explicitly permanent regardless of this.
 */
class lore_effect_resolver {
    /** @var string Targets one of the activator's own field Guardians. */
    private const TARGET_OWN_GUARDIAN = 'own_guardian';

    /** @var string Targets one of the opponent's field Guardians. */
    private const TARGET_ENEMY_GUARDIAN = 'enemy_guardian';

    /** @var string Targets the activator's own life points. */
    private const TARGET_OWN_PLAYER = 'own_player';

    /** @var string Targets the opponent's life points. */
    private const TARGET_ENEMY_PLAYER = 'enemy_player';

    /** @var string No board target — authorises a Class Promotion instead (SCOPE.md 4.3). */
    private const TARGET_NONE = 'none';

    /** @var array<string, string> Fixed target kind per known effecttype identifier. */
    private const REGISTRY = [
        'atk_buff' => self::TARGET_OWN_GUARDIAN,
        'def_buff' => self::TARGET_OWN_GUARDIAN,
        'lp_heal' => self::TARGET_OWN_PLAYER,
        'lp_damage' => self::TARGET_ENEMY_PLAYER,
        'destroy' => self::TARGET_ENEMY_GUARDIAN,
        'enable_promotion' => self::TARGET_NONE,
    ];

    /**
     * Whether an effecttype is one this engine understands.
     *
     * @param string $effecttype Effect identifier.
     * @return bool
     */
    public static function is_known(string $effecttype): bool {
        return array_key_exists($effecttype, self::REGISTRY);
    }

    /**
     * Whether an effecttype needs a target field slot supplied by the caller.
     *
     * @param string $effecttype Effect identifier.
     * @return bool
     */
    public static function requires_target_slot(string $effecttype): bool {
        return in_array(
            self::REGISTRY[$effecttype] ?? self::TARGET_NONE,
            [self::TARGET_OWN_GUARDIAN, self::TARGET_ENEMY_GUARDIAN],
            true
        );
    }

    /**
     * Applies an effect to match state.
     *
     * @param array $state Current match state.
     * @param string $activator 'human' or 'ai' — whoever activated the Lore card. Only
     *  'human' is reachable until the AI can activate Lore of its own.
     * @param string $effecttype Effect identifier; must be is_known().
     * @param int $value Effect magnitude (ATK/DEF points, life points, or the Class
     *  Promotion bonus — ignored for 'destroy').
     * @param int|null $targetslot Field slot the effect targets, required only when
     *  requires_target_slot() is true for this effecttype.
     * @return array Updated match state.
     */
    public static function apply(array $state, string $activator, string $effecttype, int $value, ?int $targetslot): array {
        if (!self::is_known($effecttype)) {
            throw new \moodle_exception('error_unknowneffecttype', 'mod_playercards');
        }

        $ownfield = $activator === 'human' ? 'humanfield' : 'aifield';
        $enemyfield = $activator === 'human' ? 'aifield' : 'humanfield';
        $ownlife = $activator === 'human' ? 'human' : 'ai';
        $enemylife = $activator === 'human' ? 'ai' : 'human';

        switch (self::REGISTRY[$effecttype]) {
            case self::TARGET_OWN_GUARDIAN:
                $slot = self::require_guardian_slot($state, $ownfield, $targetslot);
                $key = $effecttype === 'atk_buff' ? 'atkbonus' : 'defbonus';
                $state[$ownfield][$targetslot][$key] = (int) ($slot[$key] ?? 0) + $value;
                break;

            case self::TARGET_ENEMY_GUARDIAN:
                self::require_guardian_slot($state, $enemyfield, $targetslot);
                $state[$enemyfield][$targetslot] = null;
                break;

            case self::TARGET_OWN_PLAYER:
                $state['lifepoints'][$ownlife] += $value;
                break;

            case self::TARGET_ENEMY_PLAYER:
                $state['lifepoints'][$enemylife] -= $value;
                break;

            default:
                // TARGET_NONE: enable_promotion. Authorises the activator's next
                // class_promotion call — see match_service::class_promotion().
                $state['pendingpromotion'] = ['bonus' => $value];
                break;
        }

        return $state;
    }

    /**
     * Validates that a given field slot holds a Guardian, and returns it.
     *
     * @param array $state Current state.
     * @param string $zone 'humanfield' or 'aifield'.
     * @param int|null $slot Field slot index.
     * @return array The Guardian entry.
     */
    private static function require_guardian_slot(array $state, string $zone, ?int $slot): array {
        if ($slot === null || $slot < 0 || $slot >= count($state[$zone]) || $state[$zone][$slot] === null) {
            throw new \moodle_exception('error_invalideffecttarget', 'mod_playercards');
        }
        return $state[$zone][$slot];
    }
}
