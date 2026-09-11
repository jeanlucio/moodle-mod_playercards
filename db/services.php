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
 * External function definitions for mod_playercards.
 *
 * mark_intro_seen was added in Fase 2. start_match/mulligan/get_state (Etapa 1) and
 * muster_guardian/change_posture/declare_attack (Etapa 2) cover 6 of the 16 gameplay/
 * content endpoints from SCOPE.md 7 — the remaining ones are added across the rest of
 * Fase 3/4.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_playercards_mark_intro_seen' => [
        'classname'     => 'mod_playercards\external\mark_intro_seen',
        'description'   => 'Marks the automatic how-to-play intro as seen for the current user.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playercards:view',
        'loginrequired' => true,
    ],
    'mod_playercards_start_match' => [
        'classname'     => 'mod_playercards\external\start_match',
        'description'   => 'Starts a new match vs. the AI with the student\'s active deck.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playercards:view',
        'loginrequired' => true,
    ],
    'mod_playercards_mulligan' => [
        'classname'     => 'mod_playercards\external\mulligan',
        'description'   => 'Resolves the once-only mulligan decision and starts turn 1.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playercards:view',
        'loginrequired' => true,
    ],
    'mod_playercards_get_state' => [
        'classname'     => 'mod_playercards\external\get_state',
        'description'   => 'Reads the current match state.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/playercards:view',
        'loginrequired' => true,
    ],
    'mod_playercards_muster_guardian' => [
        'classname'     => 'mod_playercards\external\muster_guardian',
        'description'   => 'Musters a Guardian from hand, normal or by sacrifice.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playercards:view',
        'loginrequired' => true,
    ],
    'mod_playercards_change_posture' => [
        'classname'     => 'mod_playercards\external\change_posture',
        'description'   => 'Changes the posture of a Guardian already in play.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playercards:view',
        'loginrequired' => true,
    ],
    'mod_playercards_declare_attack' => [
        'classname'     => 'mod_playercards\external\declare_attack',
        'description'   => 'Declares an attack from one own Guardian.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playercards:view',
        'loginrequired' => true,
    ],
];
