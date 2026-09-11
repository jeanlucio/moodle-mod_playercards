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
 * Teacher screen to manage Lore cards and the own question pool for a playercards
 * instance (SCOPE.md 3.1, 6). Classic moodleform CRUD, not AJAX (SCOPE.md 17) — the AI
 * generation button arrives alongside classes/local/lore_ai_generator.php in Fase 4.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use mod_playercards\form\edit_lore_form;
use mod_playercards\form\edit_question_form;

$cmid = required_param('id', PARAM_INT);
$action = optional_param('action', 'list', PARAM_ALPHA);
$loreid = optional_param('loreid', 0, PARAM_INT);
$questionid = optional_param('questionid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('playercards', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('playercards', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/playercards:managelore', $context);

$listurl = new moodle_url('/mod/playercards/manage.php', ['id' => $cmid]);

$PAGE->set_url('/mod/playercards/manage.php', ['id' => $cmid]);
$PAGE->set_title(get_string('managelore', 'mod_playercards', $instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Lore card actions.
if ($action === 'deletelore' && $loreid > 0) {
    require_sesskey();
    $lore = $DB->get_record('playercards_lore', ['id' => $loreid, 'playercardsid' => $instance->id], '*', MUST_EXIST);
    $DB->delete_records('playercards_lore', ['id' => $lore->id]);
    redirect($listurl, get_string('loredeleted', 'mod_playercards'));
}

if ($action === 'addlore' || $action === 'editlore') {
    $lore = null;
    if ($loreid > 0) {
        $lore = $DB->get_record('playercards_lore', ['id' => $loreid, 'playercardsid' => $instance->id], '*', MUST_EXIST);
    }

    // Moodleform's default action strips the querystring, so id/action/loreid must be
    // passed explicitly here — otherwise the POST back to this same page loses the
    // course module id and required_param('id') fails on submit.
    $formaction = new moodle_url('/mod/playercards/manage.php', ['id' => $cmid, 'action' => $action, 'loreid' => $loreid]);
    $form = new edit_lore_form($formaction, [
        'subtypes' => [
            'info' => get_string('loreinfo', 'mod_playercards'),
            'quiz' => get_string('lorequiz', 'mod_playercards'),
            'trap' => get_string('loretrap', 'mod_playercards'),
        ],
    ]);

    if ($form->is_cancelled()) {
        redirect($listurl);
    } else if ($data = $form->get_data()) {
        $record = (object) [
            'playercardsid' => $instance->id,
            'subtype' => $data->subtype,
            'name' => $data->name,
            'content' => $data->content,
            'effecttype' => $data->effecttype,
            'effectvalue' => (int) $data->effectvalue,
            'maxcopies' => max(1, (int) $data->maxcopies),
            'difficulty' => $data->subtype === 'quiz' ? $data->difficulty : null,
            'questionsource' => $data->subtype !== 'trap' ? $data->questionsource : null,
            'questioncategory' => $data->subtype !== 'trap' ? trim($data->questioncategory) : null,
            'timemodified' => time(),
        ];

        if (!empty($data->loreid)) {
            $record->id = $data->loreid;
            $DB->update_record('playercards_lore', $record);
        } else {
            $record->createdby = (int) $USER->id;
            $record->timecreated = time();
            $DB->insert_record('playercards_lore', $record);
        }

        redirect($listurl, get_string('loresaved', 'mod_playercards'));
    }

    if ($lore) {
        $form->set_data([
            'loreid' => $lore->id,
            'cmid' => $cmid,
            'name' => $lore->name,
            'subtype' => $lore->subtype,
            'content' => $lore->content,
            'effecttype' => $lore->effecttype,
            'effectvalue' => $lore->effectvalue,
            'maxcopies' => $lore->maxcopies,
            'difficulty' => $lore->difficulty,
            'questionsource' => $lore->questionsource,
            'questioncategory' => $lore->questioncategory,
        ]);
    } else {
        $form->set_data(['loreid' => 0, 'cmid' => $cmid]);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managelore', 'mod_playercards', $instance->name));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Question pool actions.
if ($action === 'deletequestion' && $questionid > 0) {
    require_sesskey();
    $question = $DB->get_record(
        'playercards_questions',
        ['id' => $questionid, 'playercardsid' => $instance->id],
        '*',
        MUST_EXIST
    );
    $DB->delete_records('playercards_questions', ['id' => $question->id]);
    redirect($listurl, get_string('questiondeleted', 'mod_playercards'));
}

if ($action === 'approvequestion' && $questionid > 0) {
    require_sesskey();
    $question = $DB->get_record(
        'playercards_questions',
        ['id' => $questionid, 'playercardsid' => $instance->id],
        '*',
        MUST_EXIST
    );
    $question->approved = $question->approved ? 0 : 1;
    $question->timemodified = time();
    $DB->update_record('playercards_questions', $question);
    redirect($listurl);
}

if ($action === 'addquestion' || $action === 'editquestion') {
    $question = null;
    if ($questionid > 0) {
        $question = $DB->get_record(
            'playercards_questions',
            ['id' => $questionid, 'playercardsid' => $instance->id],
            '*',
            MUST_EXIST
        );
    }

    // See the analogous comment on the Lore form above — same required_param('id') issue.
    $formaction = new moodle_url(
        '/mod/playercards/manage.php',
        ['id' => $cmid, 'action' => $action, 'questionid' => $questionid]
    );
    $form = new edit_question_form($formaction);

    if ($form->is_cancelled()) {
        redirect($listurl);
    } else if ($data = $form->get_data()) {
        $answers = null;
        if ($data->qtype !== 'description') {
            $answers = [];
            $lines = array_filter(array_map('trim', explode("\n", $data->answerlines)));
            foreach ($lines as $line) {
                $correct = str_starts_with($line, '*');
                $answers[] = [
                    'text' => $correct ? ltrim(substr($line, 1)) : $line,
                    'correct' => $correct,
                ];
            }
        }

        $record = (object) [
            'playercardsid' => $instance->id,
            'category' => $data->category,
            'qtype' => $data->qtype,
            'questiontext' => $data->questiontext,
            'answers' => $answers !== null ? json_encode($answers) : null,
            'approved' => (int) $data->approved,
            'timemodified' => time(),
        ];

        if (!empty($data->questionid)) {
            $record->id = $data->questionid;
            $DB->update_record('playercards_questions', $record);
        } else {
            $record->addedby = (int) $USER->id;
            $record->timecreated = time();
            $DB->insert_record('playercards_questions', $record);
        }

        redirect($listurl, get_string('questionsaved', 'mod_playercards'));
    }

    if ($question) {
        $answerlines = '';
        if ($question->answers !== null) {
            $decoded = json_decode($question->answers, true) ?? [];
            $lines = array_map(
                static fn(array $answer): string => ($answer['correct'] ? '*' : '') . $answer['text'],
                $decoded
            );
            $answerlines = implode("\n", $lines);
        }

        $form->set_data([
            'questionid' => $question->id,
            'cmid' => $cmid,
            'category' => $question->category,
            'qtype' => $question->qtype,
            'questiontext' => $question->questiontext,
            'answerlines' => $answerlines,
            'approved' => $question->approved,
        ]);
    } else {
        $form->set_data(['questionid' => 0, 'cmid' => $cmid]);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managelore', 'mod_playercards', $instance->name));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// No action requested, or an unrecognised one — show the Lore/question list instead.
$lorerecords = $DB->get_records('playercards_lore', ['playercardsid' => $instance->id], 'name ASC');
$questionrecords = $DB->get_records('playercards_questions', ['playercardsid' => $instance->id], 'category ASC');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managelore', 'mod_playercards', $instance->name));

echo $OUTPUT->heading(get_string('lore', 'mod_playercards'), 3);
echo html_writer::link(
    new moodle_url('/mod/playercards/manage.php', ['id' => $cmid, 'action' => 'addlore']),
    get_string('addlorecard', 'mod_playercards'),
    ['class' => 'btn btn-primary mb-3']
);

$loretable = new html_table();
$loretable->head = [
    get_string('lorename', 'mod_playercards'),
    get_string('lore', 'mod_playercards'),
    get_string('loredifficulty', 'mod_playercards'),
    get_string('actions'),
];
foreach ($lorerecords as $lore) {
    $editurl = new moodle_url('/mod/playercards/manage.php', ['id' => $cmid, 'action' => 'editlore', 'loreid' => $lore->id]);
    $deleteurl = new moodle_url(
        '/mod/playercards/manage.php',
        ['id' => $cmid, 'action' => 'deletelore', 'loreid' => $lore->id, 'sesskey' => sesskey()]
    );
    $loretable->data[] = [
        format_string($lore->name),
        get_string('lore' . $lore->subtype, 'mod_playercards'),
        $lore->difficulty ? get_string('difficulty_' . $lore->difficulty, 'mod_playercards') : '-',
        html_writer::link($editurl, get_string('edit'))
            . ' | ' . $OUTPUT->action_link(
                $deleteurl,
                get_string('delete'),
                new confirm_action(get_string('confirmdeletelore', 'mod_playercards'))
            ),
    ];
}
echo html_writer::table($loretable);

echo $OUTPUT->heading(get_string('questionpool', 'mod_playercards'), 3);
echo html_writer::link(
    new moodle_url('/mod/playercards/manage.php', ['id' => $cmid, 'action' => 'addquestion']),
    get_string('addquestion', 'mod_playercards'),
    ['class' => 'btn btn-primary mb-3']
);

$questiontable = new html_table();
$questiontable->head = [
    get_string('questioncategorylabel', 'mod_playercards'),
    get_string('questiontype', 'mod_playercards'),
    get_string('questionapproved', 'mod_playercards'),
    get_string('actions'),
];
foreach ($questionrecords as $question) {
    $editurl = new moodle_url(
        '/mod/playercards/manage.php',
        ['id' => $cmid, 'action' => 'editquestion', 'questionid' => $question->id]
    );
    $deleteurl = new moodle_url(
        '/mod/playercards/manage.php',
        ['id' => $cmid, 'action' => 'deletequestion', 'questionid' => $question->id, 'sesskey' => sesskey()]
    );
    $approveurl = new moodle_url(
        '/mod/playercards/manage.php',
        ['id' => $cmid, 'action' => 'approvequestion', 'questionid' => $question->id, 'sesskey' => sesskey()]
    );
    $approvelabel = $question->approved
        ? get_string('unapprove', 'mod_playercards')
        : get_string('approve', 'mod_playercards');

    $questiontable->data[] = [
        format_string($question->category),
        get_string('qtype_' . $question->qtype, 'mod_playercards'),
        $question->approved ? get_string('yes') : get_string('no'),
        html_writer::link($editurl, get_string('edit'))
            . ' | ' . html_writer::link($approveurl, $approvelabel)
            . ' | ' . $OUTPUT->action_link(
                $deleteurl,
                get_string('delete'),
                new confirm_action(get_string('confirmdeletequestion', 'mod_playercards'))
            ),
    ];
}
echo html_writer::table($questiontable);

echo $OUTPUT->footer();
