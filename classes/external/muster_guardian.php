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
 * External function: muster a Guardian from hand.
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
 * Musters a Guardian from hand onto the field — normal (level 1-3, free) or by
 * sacrificing an own level 1-3 Guardian already in play (level 4-5, SCOPE.md 4.2).
 */
class muster_guardian extends external_api {
    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'token' => new external_value(PARAM_ALPHANUMEXT, 'Match token'),
            'handuid' => new external_value(PARAM_ALPHANUMEXT, 'Uid of the Guardian card in hand'),
            'fieldslot' => new external_value(PARAM_INT, 'Target field slot, 0-4'),
            'posture' => new external_value(PARAM_ALPHA, 'attack | defense'),
            'sacrificefieldslot' => new external_value(
                PARAM_INT,
                'Own field slot to sacrifice, required for level 4-5',
                VALUE_DEFAULT,
                -1
            ),
        ]);
    }

    /**
     * Musters a Guardian for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $token Match token.
     * @param string $handuid Uid of the Guardian card in hand.
     * @param int $fieldslot Target field slot, 0-4.
     * @param string $posture attack | defense.
     * @param int $sacrificefieldslot Own field slot to sacrifice, or -1 when not needed.
     * @return array Match state.
     */
    public static function execute(
        int $cmid,
        string $token,
        string $handuid,
        int $fieldslot,
        string $posture,
        int $sacrificefieldslot = -1
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'token' => $token,
            'handuid' => $handuid,
            'fieldslot' => $fieldslot,
            'posture' => $posture,
            'sacrificefieldslot' => $sacrificefieldslot,
        ]);

        $cm = get_coursemodule_from_id('playercards', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playercards:view', $context);

        $sacrificeslot = $params['sacrificefieldslot'] >= 0 ? $params['sacrificefieldslot'] : null;

        $state = match_service::muster_guardian(
            $params['cmid'],
            (int) $USER->id,
            $params['token'],
            $params['handuid'],
            $params['fieldslot'],
            $params['posture'],
            $sacrificeslot
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
