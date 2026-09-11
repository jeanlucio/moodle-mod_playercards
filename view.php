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
 * View a playercards instance.
 *
 * Fase 2 placeholder (SCOPE.md 16): renders the intro and the how-to-play onboarding.
 * The interactive board arrives in Fase 3.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_playercards\local\intro_service;
use mod_playercards\output\view_page;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('playercards', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('playercards', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/playercards:view', $context);

$event = \mod_playercards\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('playercards', $instance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/playercards/view.php', ['id' => $cm->id]);
$PAGE->set_title($instance->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$PAGE->requires->js_call_amd('mod_playercards/intro', 'init', [
    (int) $cm->id,
    !intro_service::has_seen_intro((int) $USER->id),
]);

$intro = $instance->intro !== '' ? format_module_intro('playercards', $instance, $cm->id) : '';
$page = new view_page($intro);

echo $OUTPUT->header();
echo $OUTPUT->render($page);
echo $OUTPUT->footer();
