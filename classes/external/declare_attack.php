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
 * External function: declare a Guardian attack.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_playercards\local\match_service;

/**
 * Declares an attack from one own Guardian in Offensive posture (SCOPE.md 4.5) against
 * an opposing field slot, or directly against the AI's life points when its field is
 * empty.
 */
class declare_attack extends external_api {
    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'token' => new external_value(PARAM_ALPHANUMEXT, 'Match token'),
            'attackerslot' => new external_value(PARAM_INT, 'Own field slot declaring the attack, 0-4'),
            'targetslot' => new external_value(
                PARAM_INT,
                'Opposing field slot to attack, or -1 for a direct attack',
                VALUE_DEFAULT,
                -1
            ),
        ]);
    }

    /**
     * Declares an attack for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $token Match token.
     * @param int $attackerslot Own field slot declaring the attack, 0-4.
     * @param int $targetslot Opposing field slot, or -1 for a direct attack.
     * @return array Match state.
     */
    public static function execute(int $cmid, string $token, int $attackerslot, int $targetslot = -1): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'token' => $token,
            'attackerslot' => $attackerslot,
            'targetslot' => $targetslot,
        ]);

        $cm = get_coursemodule_from_id('playercards', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playercards:view', $context);

        $instance = $DB->get_record('playercards', ['id' => $cm->instance], '*', MUST_EXIST);
        $target = $params['targetslot'] >= 0 ? $params['targetslot'] : null;

        $state = match_service::declare_attack(
            $params['cmid'],
            (int) $USER->id,
            $instance,
            $params['token'],
            $params['attackerslot'],
            $target
        );

        return match_service::export_state($state);
    }

    /**
     * Returns the structure of the execute() return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return match_structures::match_state_structure();
    }
}
