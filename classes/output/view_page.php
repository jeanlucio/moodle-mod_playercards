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
 * Renderable for the main PlayerCards activity page.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Renders the intro plus the board container amd/src/board.js takes over (SCOPE.md 16,
 * Fase 3). The board's own markup is built entirely client-side from match state — this
 * template only provides the mount point and the data attributes board.js needs to call
 * the Web services (course module id, the instance's default AI difficulty).
 */
class view_page implements renderable, templatable {
    /** @var string Formatted activity introduction, already passed through format_module_intro(). */
    private readonly string $intro;

    /** @var int Course module id. */
    private readonly int $cmid;

    /** @var string Instance's configured default AI difficulty (easy | normal | hard). */
    private readonly string $aidifficultydefault;

    /**
     * Creates the renderable.
     *
     * @param string $intro Formatted activity introduction, already passed through
     *  format_module_intro().
     * @param int $cmid Course module id.
     * @param string $aidifficultydefault Instance's configured default AI difficulty.
     */
    public function __construct(string $intro, int $cmid, string $aidifficultydefault) {
        $this->intro = $intro;
        $this->cmid = $cmid;
        $this->aidifficultydefault = $aidifficultydefault;
    }

    /**
     * Exports data for the mustache template.
     *
     * @param renderer_base $output The renderer requesting the export.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'intro' => $this->intro,
            'hasintro' => $this->intro !== '',
            'cmid' => $this->cmid,
            'aidifficultydefault' => $this->aidifficultydefault,
        ];
    }
}
