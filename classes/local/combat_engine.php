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
 * Combat resolution engine for PlayerCards.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Pure combat resolution engine — the single source of truth for the combat table in
 * SCOPE.md 4.5. Takes only plain scalars (already-resolved Guardian stats), never touches
 * the database or game state, so it stays trivially unit-testable in isolation.
 */
class combat_engine {
    /**
     * Resolves a direct attack against a player with no Guardian in the target slot.
     *
     * @param int $attackeratk Attack points of the attacking Guardian.
     * @return int Damage dealt to the defending player's life points.
     */
    public static function resolve_direct_attack(int $attackeratk): int {
        return $attackeratk;
    }

    /**
     * Resolves combat between an attacking Guardian (always in Offensive posture, per
     * SCOPE.md 4.5) and a defending Guardian in either posture.
     *
     * @param int $attackeratk Attack points of the attacking Guardian.
     * @param int $defenderatk Attack points of the defending Guardian.
     * @param int $defenderdef Defence points of the defending Guardian.
     * @param bool $defenderoffensive Whether the defending Guardian is in Offensive
     *  posture (true) or Defensive posture (false).
     * @return array Associative array with keys attackerdestroyed, defenderdestroyed
     *  (bool), attackerdamage, defenderdamage (int, life point damage taken by each side).
     */
    public static function resolve_combat(
        int $attackeratk,
        int $defenderatk,
        int $defenderdef,
        bool $defenderoffensive
    ): array {
        $result = [
            'attackerdestroyed' => false,
            'defenderdestroyed' => false,
            'attackerdamage' => 0,
            'defenderdamage' => 0,
        ];

        if ($defenderoffensive) {
            if ($attackeratk > $defenderatk) {
                $result['defenderdestroyed'] = true;
                $result['defenderdamage'] = $attackeratk - $defenderatk;
            } else if ($attackeratk < $defenderatk) {
                $result['attackerdestroyed'] = true;
                $result['attackerdamage'] = $defenderatk - $attackeratk;
            } else {
                $result['attackerdestroyed'] = true;
                $result['defenderdestroyed'] = true;
            }

            return $result;
        }

        if ($attackeratk > $defenderdef) {
            $result['defenderdestroyed'] = true;
        } else if ($attackeratk < $defenderdef) {
            $result['attackerdamage'] = $defenderdef - $attackeratk;
        }

        return $result;
    }

    /**
     * Validates that a Guardian's power pool matches its level (SCOPE.md 4.5): attack
     * plus defence points must equal 400 times the level.
     *
     * @param int $level Guardian level, 1 to 5.
     * @param int $atk Attack points.
     * @param int $def Defence points.
     * @return bool
     */
    public static function is_valid_power_pool(int $level, int $atk, int $def): bool {
        return ($atk + $def) === (400 * $level);
    }
}
