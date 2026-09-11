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
 * Renderer for mod_playercards.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\output;

use plugin_renderer_base;

/**
 * Renders PlayerCards output components.
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the main activity page.
     *
     * @param view_page $page The page to render.
     * @return string
     */
    public function render_view_page(view_page $page): string {
        return $this->render_from_template('mod_playercards/view', $page->export_for_template($this));
    }
}
