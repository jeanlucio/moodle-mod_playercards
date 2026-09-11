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
 * Only mark_intro_seen exists so far (Fase 2, SCOPE.md 16) — the 16 gameplay/content
 * endpoints from SCOPE.md 7 are added here in Fase 3/4.
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
];
