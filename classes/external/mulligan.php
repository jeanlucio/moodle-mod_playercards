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
 * External function: resolve the mulligan decision.
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
 * Resolves the human player's once-only mulligan decision (SCOPE.md 4.9) and advances the
 * match to turn 1.
 */
class mulligan extends external_api {
    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'token' => new external_value(PARAM_ALPHANUMEXT, 'Match token from start_match'),
            'keep' => new external_value(PARAM_BOOL, 'Whether to keep the opening hand'),
        ]);
    }

    /**
     * Resolves the mulligan decision for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $token Match token.
     * @param bool $keep Whether to keep the opening hand.
     * @return array Match state.
     */
    public static function execute(int $cmid, string $token, bool $keep): array {
        global $USER;

        ['cmid' => $cmid, 'token' => $token, 'keep' => $keep] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'token' => $token, 'keep' => $keep]
        );

        $cm = get_coursemodule_from_id('playercards', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playercards:view', $context);

        $state = match_service::mulligan($cmid, (int) $USER->id, $token, $keep);

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
