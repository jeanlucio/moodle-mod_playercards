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
 * External function: activate a Quiz Lore card.
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
 * Activates a face-down Quiz Lore card (SCOPE.md 4.6): reveals a question and resolves
 * the AI's answer immediately (SCOPE.md 17 — the AI always "answers" Quiz cards the
 * human activates, since it cannot activate Lore of its own yet).
 */
class activate_quiz extends external_api {
    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'token' => new external_value(PARAM_ALPHANUMEXT, 'Match token'),
            'loreslot' => new external_value(PARAM_INT, 'Own Lore slot to activate, 0-4'),
            'targetslot' => new external_value(
                PARAM_INT,
                'Field slot the effect targets if the AI answers wrong, or -1 when none is needed',
                VALUE_DEFAULT,
                -1
            ),
        ]);
    }

    /**
     * Activates a Quiz card for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $token Match token.
     * @param int $loreslot Own Lore slot to activate.
     * @param int $targetslot Field slot the effect targets if the AI answers wrong, or -1.
     * @return array Match state plus the question and its result.
     */
    public static function execute(int $cmid, string $token, int $loreslot, int $targetslot = -1): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'token' => $token,
            'loreslot' => $loreslot,
            'targetslot' => $targetslot,
        ]);

        $cm = get_coursemodule_from_id('playercards', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playercards:view', $context);

        $target = $params['targetslot'] >= 0 ? $params['targetslot'] : null;

        $result = match_service::activate_quiz(
            $params['cmid'],
            (int) $USER->id,
            $params['token'],
            $params['loreslot'],
            $target
        );

        return array_merge(
            match_service::export_state($result['state']),
            [
                'questiontext' => $result['questiontext'],
                'options' => $result['options'],
                'correctindex' => $result['correctindex'],
                'aicorrect' => $result['aicorrect'],
                'lpchange' => $result['lpchange'],
            ]
        );
    }

    /**
     * Returns the structure of the execute() return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(array_merge(
            match_structures::match_state_fields(),
            [
                'questiontext' => new external_value(PARAM_RAW, 'Revealed question text'),
                'options' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'One answer option'),
                    'Answer options, in order'
                ),
                'correctindex' => new external_value(PARAM_INT, 'Index of the correct option'),
                'aicorrect' => new external_value(PARAM_BOOL, 'Whether the AI answered correctly'),
                'lpchange' => new external_value(PARAM_INT, 'Signed life point change this activation caused'),
            ]
        ));
    }
}
