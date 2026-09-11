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
 * Fase 2 placeholder page: shows the intro and a static message. The real interactive
 * board (SCOPE.md 16, Fase 3) replaces this template's body once built — the intro
 * rendering and onboarding wiring set up here stay unchanged.
 */
class view_page implements renderable, templatable {
    /** @var string Formatted activity introduction, already passed through format_module_intro(). */
    private readonly string $intro;

    /**
     * Creates the renderable.
     *
     * @param string $intro Formatted activity introduction, already passed through
     *  format_module_intro().
     */
    public function __construct(string $intro) {
        $this->intro = $intro;
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
        ];
    }
}
