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
 * External function: execute a Class Promotion.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_playercards\local\match_service;

/**
 * Executes a Class Promotion (SCOPE.md 4.3), spending a Lore-granted authorization to
 * sacrifice 2 own Guardians and permanently boost a third.
 */
class class_promotion extends external_api {
    /**
     * Structure of one sacrifice or target reference.
     *
     * @return external_single_structure
     */
    private static function reference_structure(): external_single_structure {
        return new external_single_structure([
            'source' => new external_value(PARAM_ALPHA, 'hand | field'),
            'ref' => new external_value(PARAM_ALPHANUMEXT, 'Hand card uid, or field slot index as a string'),
        ]);
    }

    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'token' => new external_value(PARAM_ALPHANUMEXT, 'Match token'),
            'sacrifices' => new external_multiple_structure(
                self::reference_structure(),
                'Exactly 2 own Guardians to sacrifice, at least one already in play'
            ),
            'target' => self::reference_structure(),
            'statchoice' => new external_value(PARAM_ALPHA, 'atk | def'),
        ]);
    }

    /**
     * Executes a Class Promotion for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $token Match token.
     * @param array $sacrifices Exactly 2 ['source' => 'hand'|'field', 'ref' => string] entries.
     * @param array $target ['source' => 'hand'|'field', 'ref' => string].
     * @param string $statchoice atk | def.
     * @return array Match state.
     */
    public static function execute(int $cmid, string $token, array $sacrifices, array $target, string $statchoice): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'token' => $token,
            'sacrifices' => $sacrifices,
            'target' => $target,
            'statchoice' => $statchoice,
        ]);

        $cm = get_coursemodule_from_id('playercards', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playercards:view', $context);

        $state = match_service::class_promotion(
            $params['cmid'],
            (int) $USER->id,
            $params['token'],
            $params['sacrifices'],
            $params['target'],
            $params['statchoice']
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
