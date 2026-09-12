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
 * Shared Web Service return structures for match state.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\external;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Builds the external_single_structure definitions shared by start_match, mulligan and
 * get_state — every one of them returns the same match-state shape (SCOPE.md 7).
 */
class match_structures {
    /**
     * Structure of one hydrated card entry (a hand card, or an occupied field/Lore
     * slot). Guardian-only and Lore-only fields are always present but default to
     * empty/zero for the other cardtype, since external_single_structure cannot vary its
     * own shape by another field's value.
     *
     * @return external_single_structure
     */
    public static function card_structure(): external_single_structure {
        return new external_single_structure([
            'uid' => new external_value(PARAM_ALPHANUMEXT, 'Unique id of this physical card copy for the match'),
            'cardtype' => new external_value(PARAM_ALPHA, 'guardian | lore'),
            'cardid' => new external_value(PARAM_INT, 'Id in playercards_guardians or playercards_lore'),
            'name' => new external_value(PARAM_TEXT, 'Card name'),
            'level' => new external_value(PARAM_INT, 'Guardian level, 0 for a Lore card', VALUE_DEFAULT, 0),
            'atk' => new external_value(PARAM_INT, 'Guardian attack points, 0 for a Lore card', VALUE_DEFAULT, 0),
            'def' => new external_value(PARAM_INT, 'Guardian defence points, 0 for a Lore card', VALUE_DEFAULT, 0),
            'subtype' => new external_value(PARAM_ALPHA, 'info | quiz | trap, empty for a Guardian card', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Structure of one field or Lore zone slot: either empty, or occupied by a card.
     * Every slot in Etapa 1 is empty — no muster/set_lore Web Service exists yet — but
     * the shape already supports an occupied slot so later etapas do not need to change
     * this contract.
     *
     * @return external_single_structure
     */
    public static function slot_structure(): external_single_structure {
        return new external_single_structure([
            'occupied' => new external_value(PARAM_BOOL, 'Whether a card sits in this slot'),
            'uid' => new external_value(PARAM_ALPHANUMEXT, 'Unique id of the card in this slot', VALUE_DEFAULT, ''),
            'cardtype' => new external_value(PARAM_ALPHA, 'guardian | lore', VALUE_DEFAULT, ''),
            'cardid' => new external_value(PARAM_INT, 'Id in playercards_guardians or playercards_lore', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Card name', VALUE_DEFAULT, ''),
            'level' => new external_value(PARAM_INT, 'Guardian level', VALUE_DEFAULT, 0),
            'atk' => new external_value(PARAM_INT, 'Guardian attack points', VALUE_DEFAULT, 0),
            'def' => new external_value(PARAM_INT, 'Guardian defence points', VALUE_DEFAULT, 0),
            'subtype' => new external_value(PARAM_ALPHA, 'info | quiz | trap', VALUE_DEFAULT, ''),
            'effecttype' => new external_value(
                PARAM_ALPHANUMEXT,
                'Lore effect identifier, visible only for the viewer\'s own Lore slots',
                VALUE_DEFAULT,
                ''
            ),
            'posture' => new external_value(PARAM_ALPHA, 'attack | defense, Guardian slots only', VALUE_DEFAULT, ''),
            'facedown' => new external_value(PARAM_BOOL, 'Whether a Lore slot is still hidden', VALUE_DEFAULT, false),
            'sick' => new external_value(
                PARAM_BOOL,
                'Whether this Guardian was mustered this turn and cannot attack yet',
                VALUE_DEFAULT,
                false
            ),
            'attackedthisturn' => new external_value(
                PARAM_BOOL,
                'Whether this Guardian has already declared an attack this turn',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * The full match-state field definitions, as a plain array — shared by
     * match_state_structure() and by any Web service (activate_lore, activate_quiz) that
     * needs to return the match state plus its own extra fields in a single
     * external_single_structure.
     *
     * @return array
     */
    public static function match_state_fields(): array {
        return [
            'hasmatch' => new external_value(PARAM_BOOL, 'Whether a match is currently in progress'),
            'token' => new external_value(PARAM_ALPHANUMEXT, 'Match token', VALUE_DEFAULT, ''),
            'difficulty' => new external_value(PARAM_ALPHA, 'easy | normal | hard', VALUE_DEFAULT, ''),
            'phase' => new external_value(PARAM_ALPHA, 'mulligan | main', VALUE_DEFAULT, ''),
            'firstplayer' => new external_value(PARAM_ALPHA, 'human | ai', VALUE_DEFAULT, ''),
            'activeplayer' => new external_value(PARAM_ALPHA, 'human | ai', VALUE_DEFAULT, ''),
            'turnnumber' => new external_value(PARAM_INT, 'Current turn number, 0 during mulligan', VALUE_DEFAULT, 0),
            'finished' => new external_value(PARAM_BOOL, 'Whether the match has ended', VALUE_DEFAULT, false),
            'result' => new external_value(PARAM_ALPHA, 'win | loss, empty while the match is ongoing', VALUE_DEFAULT, ''),
            'musterusedthisturn' => new external_value(
                PARAM_BOOL,
                'Whether the active player already mustered a Guardian this turn',
                VALUE_DEFAULT,
                false
            ),
            'postureusedthisturn' => new external_value(
                PARAM_BOOL,
                'Whether the active player already changed a Guardian\'s posture this turn',
                VALUE_DEFAULT,
                false
            ),
            'haspendingpromotion' => new external_value(
                PARAM_BOOL,
                'Whether a Class Promotion authorization is currently available to spend',
                VALUE_DEFAULT,
                false
            ),
            'pendingpromotionbonus' => new external_value(
                PARAM_INT,
                'ATK/DEF bonus the pending Class Promotion would grant, 0 if none pending',
                VALUE_DEFAULT,
                0
            ),
            'lifepoints' => new external_single_structure(
                [
                    'human' => new external_value(PARAM_INT, 'Human player life points'),
                    'ai' => new external_value(PARAM_INT, 'AI life points'),
                ],
                'Life points per side',
                VALUE_DEFAULT,
                ['human' => 0, 'ai' => 0]
            ),
            'humanhand' => new external_multiple_structure(
                self::card_structure(),
                'Human player\'s own hand, full detail',
                VALUE_DEFAULT,
                []
            ),
            'aihandcount' => new external_value(PARAM_INT, 'Number of cards in the AI hand', VALUE_DEFAULT, 0),
            'humandeckcount' => new external_value(PARAM_INT, 'Cards left in the human deck', VALUE_DEFAULT, 0),
            'aideckcount' => new external_value(PARAM_INT, 'Cards left in the AI deck', VALUE_DEFAULT, 0),
            'humanfield' => new external_multiple_structure(self::slot_structure(), 'Human Guardian slots', VALUE_DEFAULT, []),
            'humanlore' => new external_multiple_structure(self::slot_structure(), 'Human Lore slots', VALUE_DEFAULT, []),
            'aifield' => new external_multiple_structure(self::slot_structure(), 'AI Guardian slots', VALUE_DEFAULT, []),
            'ailore' => new external_multiple_structure(self::slot_structure(), 'AI Lore slots', VALUE_DEFAULT, []),
        ];
    }

    /**
     * Structure of the full match state as sent to the client.
     *
     * @return external_single_structure
     */
    public static function match_state_structure(): external_single_structure {
        return new external_single_structure(self::match_state_fields());
    }
}
