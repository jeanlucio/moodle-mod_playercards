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
 * Teacher report of completed match attempts for a playercards instance (SCOPE.md 3.1, 6).
 *
 * Empty until match-ending logic exists (Fase 3/4, SCOPE.md 16) — the report itself is
 * fully functional from Fase 2 onward.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('playercards', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('playercards', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/playercards:viewreports', $context);

$PAGE->set_url('/mod/playercards/attemptsreport.php', ['id' => $cmid]);
$PAGE->set_title(get_string('attemptsreport', 'mod_playercards', $instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$sql = 'SELECT a.id, a.userid, a.aidifficulty, a.result, a.lpremaining, a.turnsplayed, a.score, a.timecreated
          FROM {playercards_attempts} a
          JOIN {user} u ON u.id = a.userid
         WHERE a.playercardsid = :playercardsid
      ORDER BY a.timecreated DESC';
$attempts = $DB->get_records_sql($sql, ['playercardsid' => $instance->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('attemptsreport', 'mod_playercards', $instance->name));

if (empty($attempts)) {
    echo $OUTPUT->notification(get_string('noattemptsyet', 'mod_playercards'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('fullnameuser'),
    get_string('aidifficultydefault', 'mod_playercards'),
    get_string('result', 'mod_playercards'),
    get_string('lifepoints', 'mod_playercards'),
    get_string('turnsplayed', 'mod_playercards'),
    get_string('gradenoun'),
    get_string('date'),
];

$userids = array_unique(array_map(static fn(\stdClass $attempt): int => (int) $attempt->userid, $attempts));
[$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
$users = $DB->get_records_select('user', "id $insql", $inparams, '', 'id, ' . implode(', ', \core_user\fields::get_name_fields()));

foreach ($attempts as $attempt) {
    $user = $users[$attempt->userid] ?? null;
    $table->data[] = [
        $user ? fullname($user) : '-',
        get_string('difficulty_' . $attempt->aidifficulty, 'mod_playercards'),
        get_string($attempt->result === 'win' ? 'resultwin' : 'resultloss', 'mod_playercards'),
        $attempt->lpremaining,
        $attempt->turnsplayed,
        format_float($attempt->score, 2),
        userdate($attempt->timecreated),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
