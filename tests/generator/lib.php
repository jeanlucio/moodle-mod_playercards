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
 * Data generator for mod_playercards.
 *
 * @package    mod_playercards
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator class for the playercards activity module.
 *
 * No field defaults are needed beyond what testing_module_generator itself supplies:
 * every playercards-specific column has a database-level default (SCOPE.md 5), and
 * playercards_add_instance()/playercards_update_instance() (lib.php) already tolerate a
 * $data object missing questionsource_own/questionsource_bank/completionwinsenabled —
 * the same optional checkbox fields mod_form.php submits.
 */
class mod_playercards_generator extends testing_module_generator {
}
