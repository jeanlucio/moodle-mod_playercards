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
 * Custom completion rules for the PlayerCards activity.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_playercards\completion;

use core_completion\activity_custom_completion;

/**
 * Defines and evaluates the custom completion rule: student must reach the required
 * number of match wins (SCOPE.md 3.1, completionwins).
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetches the completion state for a given custom completion rule.
     *
     * @param string $rule The rule name.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $required = (int) $this->cm->customdata['customcompletionrules']['completionwins'];
        $winscount = $DB->count_records(
            'playercards_attempts',
            ['playercardsid' => $this->cm->instance, 'userid' => $this->userid, 'result' => 'win']
        );

        return $winscount >= $required ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Returns the list of custom completion rule names defined by this module.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionwins'];
    }

    /**
     * Returns human-readable descriptions for each custom completion rule.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $required = $this->cm->customdata['customcompletionrules']['completionwins'] ?? 0;
        return [
            'completionwins' => get_string('completionwins_desc', 'mod_playercards', $required),
        ];
    }

    /**
     * Returns the display order for all completion rules (core + custom).
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionusegrade',
            'completionwins',
        ];
    }
}
