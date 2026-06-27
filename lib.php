<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library functions for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the features this module supports.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function aiescape_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO            => true,
        FEATURE_SHOW_DESCRIPTION     => true,
        FEATURE_GRADE_HAS_GRADE      => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_BACKUP_MOODLE2       => true,
        FEATURE_MOD_PURPOSE          => MOD_PURPOSE_ASSESSMENT,
        default                      => null,
    };
}

/**
 * Populates cached course-module info, including the custom completion rule.
 *
 * There is no per-activity teacher toggle for the "complete the scenario" rule
 * (it always applies once automatic completion tracking is enabled), so it is
 * unconditionally exposed here for \mod_aiescape\completion\custom_completion
 * to evaluate.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function aiescape_get_coursemodule_info($coursemodule) {
    global $DB;

    $aiescape = $DB->get_record('aiescape', ['id' => $coursemodule->instance], 'id, name');
    if (!$aiescape) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $aiescape->name;

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completioncompleted'] = 1;
    }

    return $info;
}

/**
 * Adds a new instance of the aiescape activity.
 *
 * @param stdClass $data Data from the settings form
 * @param mod_aiescape_mod_form|null $form The form instance
 * @return int The id of the newly inserted record
 */
function aiescape_add_instance(stdClass $data, ?mod_aiescape_mod_form $form = null) {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;

    if (!isset($data->intro)) {
        $data->intro = '';
    }
    if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }

    $data->id = $DB->insert_record('aiescape', $data);

    aiescape_grade_item_update($data);
    aiescape_save_buttons($data);

    return $data->id;
}

/**
 * Updates an existing instance of the aiescape activity.
 *
 * @param stdClass $data Data from the settings form
 * @param mod_aiescape_mod_form|null $form The form instance
 * @return bool
 */
function aiescape_update_instance(stdClass $data, ?mod_aiescape_mod_form $form = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    $result = $DB->update_record('aiescape', $data);

    aiescape_grade_item_update($data);
    aiescape_save_buttons($data);

    return $result;
}

/**
 * Deletes an instance of the aiescape activity and all associated data.
 *
 * @param int $id The instance id
 * @return bool
 */
function aiescape_delete_instance($id) {
    global $DB;

    if (!$aiescape = $DB->get_record('aiescape', ['id' => $id])) {
        return false;
    }

    // Delete all messages and flags for all attempts.
    $attemptids = $DB->get_fieldset_select('aiescape_attempts', 'id', 'aiescape = ?', [$id]);
    if ($attemptids) {
        [$insql, $params] = $DB->get_in_or_equal($attemptids);
        $DB->delete_records_select('aiescape_flags', "attemptid $insql", $params);
        $DB->delete_records_select('aiescape_messages', "attemptid $insql", $params);
    }

    $DB->delete_records('aiescape_attempts', ['aiescape' => $id]);
    $DB->delete_records('aiescape_buttons', ['aiescape' => $id]);

    aiescape_grade_item_delete($aiescape);

    $DB->delete_records('aiescape', ['id' => $id]);

    return true;
}

/**
 * Saves the configurable secondary buttons for an activity instance.
 *
 * Handles both admin-defined preset buttons (defaultindex 1-5) and teacher-
 * defined custom buttons. Called from add_instance and update_instance.
 *
 * @param stdClass $data Form data
 * @return void
 */
function aiescape_save_buttons(stdClass $data) {
    global $DB;

    $DB->delete_records('aiescape_buttons', ['aiescape' => $data->id]);
    $sortorder = 0;

    // Save enabled preset (admin-defined) buttons.
    for ($i = 1; $i <= 5; $i++) {
        if (empty($data->{'presetbtn' . $i})) {
            continue;
        }
        $label  = trim((string) get_config('mod_aiescape', 'defaultbutton' . $i . 'label'));
        $prompt = trim((string) get_config('mod_aiescape', 'defaultbutton' . $i . 'prompt'));
        if ($label === '' || $prompt === '') {
            continue;
        }
        $record               = new stdClass();
        $record->aiescape     = $data->id;
        $record->label        = $label;
        $record->prompt       = $prompt;
        $record->sortorder    = $sortorder++;
        $record->defaultindex = $i;
        $record->usagelimit   = aiescape_parse_usage_limit($data->{'presetbtn' . $i . 'limit'} ?? '');
        $DB->insert_record('aiescape_buttons', $record);
    }

    // Save teacher-defined custom buttons.
    if (empty($data->buttonlabel)) {
        return;
    }

    $labels  = (array) $data->buttonlabel;
    $prompts = (array) ($data->buttonprompt ?? []);
    $limits  = (array) ($data->buttonlimit ?? []);

    foreach ($labels as $i => $label) {
        $label  = trim($label);
        $prompt = trim($prompts[$i] ?? '');
        if ($label === '' || $prompt === '') {
            continue;
        }
        $record               = new stdClass();
        $record->aiescape     = $data->id;
        $record->label        = $label;
        $record->prompt       = $prompt;
        $record->sortorder    = $sortorder++;
        $record->defaultindex = null;
        $record->usagelimit   = aiescape_parse_usage_limit($limits[$i] ?? '');
        $DB->insert_record('aiescape_buttons', $record);
    }
}

/**
 * Parses a button usage-limit form value into a database-ready value.
 *
 * @param mixed $raw The raw form value (string or number)
 * @return int|null A positive integer limit, or null for unlimited
 */
function aiescape_parse_usage_limit($raw): ?int {
    $trimmed = trim((string) $raw);
    return ($trimmed === '') ? null : (int) $trimmed;
}

/**
 * Creates or updates the grade item in the gradebook.
 *
 * @param stdClass $aiescape The activity record
 * @param mixed $grades Optional grades to write
 * @return int Grade update status
 */
function aiescape_grade_item_update($aiescape, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (property_exists($aiescape, 'cmidnumber')) {
        $item = ['itemname' => clean_param($aiescape->name, PARAM_NOTAGS), 'idnumber' => $aiescape->cmidnumber];
    } else {
        $item = ['itemname' => clean_param($aiescape->name, PARAM_NOTAGS)];
    }

    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $aiescape->grade ?? 100;
    $item['grademin']  = 0;

    if (isset($aiescape->grade) && $aiescape->grade == 0) {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update('mod/aiescape', $aiescape->course, 'mod', 'aiescape', $aiescape->id, 0, $grades, $item);
}

/**
 * Deletes the grade item from the gradebook.
 *
 * @param stdClass $aiescape The activity record
 * @return int
 */
function aiescape_grade_item_delete($aiescape) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/aiescape',
        $aiescape->course,
        'mod',
        'aiescape',
        $aiescape->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Updates the gradebook with current grades.
 *
 * @param stdClass $aiescape The activity record
 * @param int $userid Optional specific user; 0 means all users
 * @param bool $nullifnone If true, write null grade when no submission exists
 */
function aiescape_update_grades($aiescape, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($aiescape->grade == 0) {
        aiescape_grade_item_update($aiescape);
        return;
    }

    $grades = aiescape_get_user_grades($aiescape, $userid);

    if ($grades) {
        aiescape_grade_item_update($aiescape, $grades);
    } else if ($userid && $nullifnone) {
        $grade           = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        aiescape_grade_item_update($aiescape, $grade);
    } else {
        aiescape_grade_item_update($aiescape);
    }
}

/**
 * Returns grades for one or all users.
 *
 * Completed attempts always earn the full grade. Abandoned attempts earn a
 * partial grade when $aiescape->partialscoreonquit is enabled. A full grade
 * from any completed attempt takes precedence over any partial grade.
 *
 * @param stdClass $aiescape The activity record
 * @param int $userid 0 means all users
 * @return array Keyed by userid
 */
function aiescape_get_user_grades($aiescape, $userid = 0) {
    global $DB;

    // Full grade from any completed attempt.
    $params = array_merge(['aiescape' => $aiescape->id, 'status' => 'completed'], $userid ? ['userid' => $userid] : []);
    $where  = 'aiescape = :aiescape AND status = :status AND ispreview = 0' . ($userid ? ' AND userid = :userid' : '');
    $completed = $DB->get_records_select('aiescape_attempts', $where, $params, 'timecompleted ASC');
    $grades = [];

    foreach ($completed as $attempt) {
        if (!isset($grades[$attempt->userid])) {
            $grades[$attempt->userid]             = new stdClass();
            $grades[$attempt->userid]->userid     = $attempt->userid;
            $grades[$attempt->userid]->rawgrade   = (float) $aiescape->grade;
            $grades[$attempt->userid]->dategraded = $attempt->timecompleted;
        }
    }

    // Partial grade from abandoned attempts when the setting is on.
    if (!empty($aiescape->partialscoreonquit) && (int) $aiescape->steps > 0) {
        $params = array_merge(['aiescape' => $aiescape->id, 'status' => 'abandoned'], $userid ? ['userid' => $userid] : []);
        $where  = 'aiescape = :aiescape AND status = :status AND ispreview = 0' . ($userid ? ' AND userid = :userid' : '');
        $abandoned = $DB->get_records_select('aiescape_attempts', $where, $params, 'timecompleted ASC');

        foreach ($abandoned as $attempt) {
            // Only apply partial if user has no completed attempt (full grade wins).
            if (isset($grades[$attempt->userid])) {
                continue;
            }
            $partial = (float) $aiescape->grade
                * min(1.0, (int) $attempt->stepstally / (int) $aiescape->steps);
            // Keep the highest partial grade across multiple abandoned attempts.
            if (!isset($grades[$attempt->userid]) || $partial > $grades[$attempt->userid]->rawgrade) {
                $grades[$attempt->userid]             = new stdClass();
                $grades[$attempt->userid]->userid     = $attempt->userid;
                $grades[$attempt->userid]->rawgrade   = $partial;
                $grades[$attempt->userid]->dategraded = $attempt->timecompleted;
            }
        }
    }

    return $grades;
}

/**
 * Adds the AI Escape Room reset option to the course-reset form.
 *
 * @param MoodleQuickForm $mform The course reset form
 */
function aiescape_reset_course_form_definition($mform) {
    $mform->addElement('header', 'aiescapeheader', get_string('modulenameplural', 'mod_aiescape'));
    $mform->addElement('advcheckbox', 'reset_aiescape_attempts', get_string('resetattempts', 'mod_aiescape'));
}

/**
 * Returns default values for the course-reset form fields.
 *
 * @param stdClass $course The course object (unused)
 * @return array
 */
function aiescape_reset_course_form_defaults($course) {
    return ['reset_aiescape_attempts' => 1];
}

/**
 * Resets user data for course reset.
 *
 * @param stdClass $data Course reset form data
 * @return array Status items
 */
function aiescape_reset_userdata($data) {
    global $CFG, $DB;

    $status = [];

    if (!empty($data->reset_aiescape_attempts)) {
        require_once($CFG->libdir . '/gradelib.php');

        $aiescapes = $DB->get_records('aiescape', ['course' => $data->courseid], '', 'id, course');
        $aiescapeids = array_keys($aiescapes);

        if ($aiescapeids) {
            [$insql, $params] = $DB->get_in_or_equal($aiescapeids);
            $attemptids = $DB->get_fieldset_select('aiescape_attempts', 'id', "aiescape $insql", $params);
            if ($attemptids) {
                [$aisql, $aiparams] = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select('aiescape_flags', "attemptid $aisql", $aiparams);
                $DB->delete_records_select('aiescape_messages', "attemptid $aisql", $aiparams);
            }
            $DB->delete_records_select('aiescape_attempts', "aiescape $insql", $params);

            // Reset gradebook entries for all activities in the course.
            foreach ($aiescapes as $aiescape) {
                grade_update('mod/aiescape', $aiescape->course, 'mod', 'aiescape',
                    $aiescape->id, 0, null, ['reset' => 1]);
            }
        }

        $status[] = [
            'component' => get_string('modulenameplural', 'mod_aiescape'),
            'item'      => get_string('attempts', 'mod_aiescape'),
            'error'     => false,
        ];
    }

    return $status;
}

/**
 * Adds navigation nodes for teachers (Reports link).
 *
 * @param settings_navigation $settings Navigation object
 * @param navigation_node $node The module navigation node
 */
function aiescape_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    global $PAGE;

    if (has_capability('mod/aiescape:viewreports', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/aiescape/report.php', ['id' => $PAGE->cm->id]);
        $node->add(get_string('report', 'mod_aiescape'), $url, navigation_node::TYPE_SETTING);
    }
}
