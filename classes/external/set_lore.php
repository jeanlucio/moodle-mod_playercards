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
 * External function: place a Lore card face-down.
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
 * Places a Lore card from hand face-down into an empty Lore zone slot (SCOPE.md 4.1, 4.6).
 */
class set_lore extends external_api {
    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'token' => new external_value(PARAM_ALPHANUMEXT, 'Match token'),
            'handuid' => new external_value(PARAM_ALPHANUMEXT, 'Uid of the Lore card in hand'),
            'loreslot' => new external_value(PARAM_INT, 'Target Lore slot, 0-4'),
        ]);
    }

    /**
     * Places a Lore card for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $token Match token.
     * @param string $handuid Uid of the Lore card in hand.
     * @param int $loreslot Target Lore slot, 0-4.
     * @return array Match state.
     */
    public static function execute(int $cmid, string $token, string $handuid, int $loreslot): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'token' => $token,
            'handuid' => $handuid,
            'loreslot' => $loreslot,
        ]);

        $cm = get_coursemodule_from_id('playercards', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playercards:view', $context);

        $state = match_service::set_lore(
            $params['cmid'],
            (int) $USER->id,
            $params['token'],
            $params['handuid'],
            $params['loreslot']
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
