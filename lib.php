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
 * Library functions for mod_playercards.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** AI opponent difficulty: easy. */
define('PLAYERCARDS_DIFFICULTY_EASY', 'easy');

/** AI opponent difficulty: normal. */
define('PLAYERCARDS_DIFFICULTY_NORMAL', 'normal');

/** AI opponent difficulty: hard. */
define('PLAYERCARDS_DIFFICULTY_HARD', 'hard');

/** Question source bit flag: the plugin's own question/content pool. */
define('PLAYERCARDS_QUESTIONSOURCE_OWN', 1);

/** Question source bit flag: the real Moodle question bank (core_question). */
define('PLAYERCARDS_QUESTIONSOURCE_BANK', 2);

/** Grade aggregation: highest score across all matches. */
define('PLAYERCARDS_GRADE_HIGHEST', 1);

/** Grade aggregation: average score across all matches. */
define('PLAYERCARDS_GRADE_AVERAGE', 2);

/** Grade aggregation: score from the first match. */
define('PLAYERCARDS_GRADE_FIRST', 3);

/** Grade aggregation: score from the last match. */
define('PLAYERCARDS_GRADE_LAST', 4);

/**
 * Builds the questionsource bitmask from form data.
 *
 * @param stdClass $data Form data.
 * @return int
 */
function playercards_build_questionsource(stdClass $data): int {
    $source = 0;

    if (!empty($data->questionsource_own)) {
        $source |= PLAYERCARDS_QUESTIONSOURCE_OWN;
    }
    if (!empty($data->questionsource_bank)) {
        $source |= PLAYERCARDS_QUESTIONSOURCE_BANK;
    }

    return $source;
}

/**
 * Returns the available grading method options, keyed by their PLAYERCARDS_GRADE_*
 * constant.
 *
 * @return array<int, string>
 */
function playercards_get_grademethod_options(): array {
    return [
        PLAYERCARDS_GRADE_HIGHEST => get_string('grademethod_highest', 'mod_playercards'),
        PLAYERCARDS_GRADE_AVERAGE => get_string('grademethod_average', 'mod_playercards'),
        PLAYERCARDS_GRADE_FIRST   => get_string('grademethod_first', 'mod_playercards'),
        PLAYERCARDS_GRADE_LAST    => get_string('grademethod_last', 'mod_playercards'),
    ];
}

/**
 * Calculates a single user's final grade from their completed match attempts.
 *
 * @param stdClass $instance Activity instance.
 * @param array $attempts playercards_attempts records for this user, ordered by
 *  timecreated ASC.
 * @return float
 */
function playercards_calculate_user_grade(stdClass $instance, array $attempts): float {
    if (empty($attempts)) {
        return 0.0;
    }

    $scores = array_map(static fn(stdClass $attempt): float => (float) $attempt->score, $attempts);
    $grademethod = (int) ($instance->grademethod ?? PLAYERCARDS_GRADE_HIGHEST);

    switch ($grademethod) {
        case PLAYERCARDS_GRADE_AVERAGE:
            return array_sum($scores) / count($scores);
        case PLAYERCARDS_GRADE_FIRST:
            return $scores[array_key_first($scores)];
        case PLAYERCARDS_GRADE_LAST:
            return $scores[array_key_last($scores)];
        case PLAYERCARDS_GRADE_HIGHEST:
        default:
            return max($scores);
    }
}

/**
 * Creates or updates the grade item for a playercards instance.
 *
 * @param stdClass $instance Activity instance (must have id, course, name, grade,
 *  gradepass).
 * @param mixed $grades Grade object(s), null to update the item only, or 'reset' to
 *  reset grades.
 * @return int GRADE_UPDATE_OK or an error constant.
 */
function playercards_grade_item_update(stdClass $instance, mixed $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $instance->name,
        'idnumber' => $instance->cmidnumber ?? '',
    ];

    if ((int) $instance->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = (float) $instance->grade;
        $params['grademin']  = 0.0;
    } else if ((int) $instance->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -(int) $instance->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    $isreset = $grades === 'reset';
    if ($isreset) {
        $params['reset'] = true;
        $grades = null;
    }

    $result = grade_update('mod/playercards', $instance->course, 'mod', 'playercards', $instance->id, 0, $grades, $params);

    // The core grade_update() (lib/gradelib.php) silently drops a 'gradepass' key from
    // $itemdetails — it is not in its internal allow-list. Applied directly on the
    // grade_item instead, mirroring mod_workshop/mod_playerwords.
    if ($result === GRADE_UPDATE_OK && !$isreset && !empty($instance->gradepass)) {
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'playercards',
            'iteminstance' => $instance->id,
            'itemnumber' => 0,
            'courseid' => $instance->course,
        ]);
        if ($gradeitem && (float) $gradeitem->gradepass !== (float) $instance->gradepass) {
            $gradeitem->gradepass = (float) $instance->gradepass;
            $gradeitem->update();
        }
    }

    return $result;
}

/**
 * Updates gradebook grades for one or all users of a playercards instance.
 *
 * @param stdClass $instance Activity instance.
 * @param int $userid User id, 0 to update all users.
 * @return void
 */
function playercards_update_grades(stdClass $instance, int $userid = 0): void {
    global $DB;

    $sql = 'SELECT a.id, a.userid, a.score
              FROM {playercards_attempts} a
             WHERE a.playercardsid = :instanceid';
    $params = ['instanceid' => $instance->id];

    if ($userid > 0) {
        $sql .= ' AND a.userid = :userid';
        $params['userid'] = $userid;
    }

    $sql .= ' ORDER BY a.timecreated ASC';
    $attempts = $DB->get_records_sql($sql, $params);

    if (empty($attempts)) {
        if ($userid > 0) {
            $grade = new stdClass();
            $grade->userid = $userid;
            $grade->rawgrade = null;
            playercards_grade_item_update($instance, [$userid => $grade]);
        } else {
            playercards_grade_item_update($instance);
        }
        return;
    }

    $userattempts = [];
    foreach ($attempts as $attempt) {
        $userattempts[$attempt->userid][] = $attempt;
    }

    $grades = [];
    foreach ($userattempts as $uid => $userattemptlist) {
        $grade = new stdClass();
        $grade->userid = $uid;
        $grade->rawgrade = playercards_calculate_user_grade($instance, $userattemptlist);
        $grades[$uid] = $grade;
    }

    playercards_grade_item_update($instance, $grades);
}

/**
 * Adds a new instance of playercards into the database.
 *
 * @param stdClass $data Submitted data from the form.
 * @param ?moodleform $mform The form instance.
 * @return int The new instance id.
 */
function playercards_add_instance(stdClass $data, ?moodleform $mform = null): int {
    global $DB;

    if (empty($data->completionwinsenabled)) {
        $data->completionwins = 0;
    }
    unset($data->completionwinsenabled);

    $data->gradepass = isset($data->gradepass) ? (float) $data->gradepass : 0.0;
    $data->questionsource = playercards_build_questionsource($data);
    unset($data->questionsource_own, $data->questionsource_bank);

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('playercards', $data);

    playercards_grade_item_update($data);

    return $data->id;
}

/**
 * Updates an instance of playercards in the database.
 *
 * @param stdClass $data Submitted data from the form.
 * @param ?moodleform $mform The form instance.
 * @return bool True if successful.
 */
function playercards_update_instance(stdClass $data, ?moodleform $mform = null): bool {
    global $DB;

    if (empty($data->completionwinsenabled)) {
        $data->completionwins = 0;
    }
    unset($data->completionwinsenabled);

    $data->gradepass = isset($data->gradepass) ? (float) $data->gradepass : 0.0;
    $data->questionsource = playercards_build_questionsource($data);
    unset($data->questionsource_own, $data->questionsource_bank);

    $data->id = $data->instance;
    $data->timemodified = time();
    $result = $DB->update_record('playercards', $data);

    playercards_grade_item_update($data);

    return $result;
}

/**
 * Deletes an instance of playercards from the database, including every child table
 * keyed by playercardsid (SCOPE.md 5) — the global Guardian catalog and the AI reference
 * decks (playercardsid null) are never touched here.
 *
 * @param int $id ID of the module instance.
 * @return bool True if successful.
 */
function playercards_delete_instance(int $id): bool {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $instance = $DB->get_record('playercards', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    $DB->delete_records_select(
        'playercards_deck_cards',
        'deckid IN (SELECT id FROM {playercards_decks} WHERE playercardsid = :pcid)',
        ['pcid' => $id]
    );
    $DB->delete_records('playercards_decks', ['playercardsid' => $id]);
    $DB->delete_records('playercards_attempts', ['playercardsid' => $id]);
    $DB->delete_records('playercards_ownership', ['playercardsid' => $id]);
    $DB->delete_records('playercards_questions', ['playercardsid' => $id]);
    $DB->delete_records('playercards_lore', ['playercardsid' => $id]);

    grade_update('mod/playercards', $instance->course, 'mod', 'playercards', $id, 0, null, ['deleted' => 1]);

    $DB->delete_records('playercards', ['id' => $id]);

    return true;
}

/**
 * Returns the features this module supports.
 *
 * @param string $feature FEATURE_xx constant for the requested feature.
 * @return mixed True/false, a purpose string for FEATURE_MOD_PURPOSE/
 *  FEATURE_MOD_OTHERPURPOSE, or null if unknown.
 */
function playercards_supports(string $feature): mixed {
    // FEATURE_MOD_OTHERPURPOSE only exists from Moodle 5.1 onwards (MDL-85598); this
    // plugin also targets Moodle 4.5, where referencing the undefined constant as a
    // switch case label would still be a fatal error, guard or not.
    if (defined('FEATURE_MOD_OTHERPURPOSE') && $feature === FEATURE_MOD_OTHERPURPOSE) {
        return MOD_PURPOSE_ASSESSMENT;
    }

    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_INTERACTIVECONTENT;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        // Not yet implemented: no backup/moodle2/ steplib yet (SCOPE.md 16, Fase 5).
        // Flip on only alongside the real implementation.
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

/**
 * Populates the course module info object with custom completion rule data.
 *
 * @param stdClass $coursemodule The raw course_modules row (id, instance, ...).
 * @return cached_cm_info|false A populated info object, or false on failure.
 */
function playercards_get_coursemodule_info(stdClass $coursemodule): cached_cm_info|false {
    global $DB;

    $fields = 'id, name, completionwins';
    $instance = $DB->get_record('playercards', ['id' => $coursemodule->instance], $fields);
    if (!$instance) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionwins'] = (int) $instance->completionwins;
    }

    return $info;
}

/**
 * Describes the active custom completion rules.
 *
 * @param stdClass|cm_info $cm The course module info.
 * @return array An array of active completion rule descriptions.
 */
function playercards_get_completion_active_rule_descriptions(stdClass|cm_info $cm): array {
    $descriptions = [];

    $rules = $cm->customdata['customcompletionrules'] ?? [];
    if (!empty($rules['completionwins'])) {
        $descriptions[] = get_string('completionwins_desc', 'mod_playercards', $rules['completionwins']);
    }

    return $descriptions;
}
