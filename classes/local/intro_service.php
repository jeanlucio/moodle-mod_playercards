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
 * Service tracking whether a user has already seen the automatic how-to-play intro.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Tracks the site-wide "seen intro" user preference.
 *
 * The how-to-play modal teaches the general game rules (SCOPE.md 4), not something tied
 * to a specific course or activity instance, so the preference is intentionally
 * site-wide — a student who already saw it in one course should not see it again in
 * another. Mirrors the pattern already used by mod_playerwords/mod_playercross: a
 * single user_preferences row, no dedicated table.
 */
class intro_service {
    /**
     * Name of the user preference flagging that the how-to-play modal has already been
     * shown automatically once.
     */
    private const PREFERENCE_NAME = 'mod_playercards_seenintro';

    /**
     * Whether the given user has already seen the automatic introduction.
     *
     * @param int $userid User id.
     * @return bool
     */
    public static function has_seen_intro(int $userid): bool {
        return (bool) get_user_preferences(self::PREFERENCE_NAME, false, $userid);
    }

    /**
     * Marks the automatic introduction as seen for the given user.
     *
     * @param int $userid User id.
     * @return void
     */
    public static function mark_intro_seen(int $userid): void {
        set_user_preference(self::PREFERENCE_NAME, 1, $userid);
    }

    /**
     * Name of the underlying user preference, exposed for the privacy provider and for
     * db/uninstall.php cleanup (SCOPE.md 16, Fase 5).
     *
     * @return string
     */
    public static function get_preference_name(): string {
        return self::PREFERENCE_NAME;
    }
}
