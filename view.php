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
 * Main view page for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiescape/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aiescape');
$aiescape = $DB->get_record('aiescape', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiescape:view', $context);

// Log module viewed event.
$event = \mod_aiescape\event\course_module_viewed::create([
    'objectid' => $aiescape->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('aiescape', $aiescape);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/aiescape/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($aiescape->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo html_writer::start_div('aiescape-page');
echo $OUTPUT->heading(format_string($aiescape->name), 2);

// Note: the activity description is already rendered once by core's activity
// header inside $OUTPUT->header() above; do not print $aiescape->intro again here.

if (!empty($aiescape->showpremise) || !empty($aiescape->showgoal)) {
    echo $OUTPUT->box_start('generalbox mod_aiescape_premisegoal');
    if (!empty($aiescape->showpremise)) {
        echo html_writer::tag('h4', get_string('premise', 'mod_aiescape'));
        echo html_writer::div(format_text($aiescape->premise, FORMAT_PLAIN));
    }
    if (!empty($aiescape->showgoal)) {
        echo html_writer::tag('h4', get_string('goal', 'mod_aiescape'));
        echo html_writer::div(format_text($aiescape->goal, FORMAT_PLAIN));
    }
    echo $OUTPUT->box_end();
}

if (!empty($aiescape->showchoicecounts) && $aiescape->gamemode !== 'freetext') {
    echo html_writer::start_div('aiescape-choicecounts mb-3');
    echo html_writer::span(
        get_string('choicecount_good', 'mod_aiescape', $aiescape->choicesgood),
        'badge rounded-pill aiescape-count-pill aiescape-count-good'
    );
    if ($aiescape->choicesneutral > 0) {
        echo html_writer::span(
            get_string('choicecount_neutral', 'mod_aiescape', $aiescape->choicesneutral),
            'badge rounded-pill aiescape-count-pill aiescape-count-neutral'
        );
    }
    if ($aiescape->choicesbad > 0) {
        echo html_writer::span(
            get_string('choicecount_bad', 'mod_aiescape', $aiescape->choicesbad),
            'badge rounded-pill aiescape-count-pill aiescape-count-bad'
        );
    }
    echo html_writer::end_div();
}

// Teacher view: show attempt stats and link to report.
if (has_capability('mod/aiescape:viewreports', $context)) {
    $totalattempts = $DB->count_records('aiescape_attempts', ['aiescape' => $aiescape->id, 'ispreview' => 0]);
    $completed     = $DB->count_records(
        'aiescape_attempts',
        ['aiescape' => $aiescape->id, 'status' => 'completed', 'ispreview' => 0]
    );

    echo $OUTPUT->box_start('generalbox');
    echo html_writer::tag('p', get_string('attempts', 'mod_aiescape') . ': ' . $totalattempts);
    echo html_writer::tag('p', get_string('completed', 'mod_aiescape') . ': ' . $completed);
    $reporturl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id]);
    echo html_writer::link($reporturl, get_string('report', 'mod_aiescape'), ['class' => 'btn btn-secondary']);

    if (!empty($aiescape->flagkeywords)) {
        $flaggedcount = $DB->count_records_sql(
            'SELECT COUNT(f.id)
               FROM {aiescape_flags} f
               JOIN {aiescape_attempts} aa ON aa.id = f.attemptid
              WHERE aa.aiescape = :aiescape AND aa.ispreview = 0',
            ['aiescape' => $aiescape->id]
        );
        $flaggedurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id, 'flagged' => 1]);
        echo ' ' . html_writer::link(
            $flaggedurl,
            get_string('viewflaggedattempts', 'mod_aiescape', $flaggedcount),
            ['class' => 'btn btn-outline-warning']
        );
    }

    echo $OUTPUT->box_end();

    // Fall through to the game UI only if this user can also play (e.g. admin testing).
    if (!has_capability('mod/aiescape:play', $context)) {
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }
}

// Student view.
if (!has_capability('mod/aiescape:play', $context)) {
    echo $OUTPUT->notification(get_string('error:nopermission', 'mod_aiescape'), 'error');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Check attempt availability.
$atman = new \mod_aiescape\attempt_manager();

// Enforce the open/close window for students; previewing users may play any time.
if (!has_capability('mod/aiescape:viewreports', $context)) {
    if (!empty($aiescape->timeopen) && time() < $aiescape->timeopen) {
        echo $OUTPUT->notification(
            get_string('error:notopenyet', 'mod_aiescape', userdate($aiescape->timeopen)),
            'info'
        );
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }
    if (\mod_aiescape\attempt_manager::is_closed($aiescape)) {
        // Finalise any attempt the student still has open.
        if ($activeattempt = $atman->get_active_attempt($aiescape->id, $USER->id)) {
            $atman->abandon_attempt($activeattempt, $aiescape, $course, get_fast_modinfo($course)->get_cm($cm->id));
        }
        echo $OUTPUT->notification(
            get_string('error:closedon', 'mod_aiescape', userdate($aiescape->timeclose)),
            'warning'
        );
        if ($aiescape->allowstudentreview && has_capability('mod/aiescape:viewownattempts', $context)) {
            $myurl = new moodle_url('/mod/aiescape/myattempts.php', ['id' => $cm->id]);
            echo html_writer::link($myurl, get_string('viewattempts', 'mod_aiescape'), ['class' => 'btn btn-secondary mt-2']);
        }
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }
}

$canstart = $atman->can_start_new_attempt($aiescape, $USER->id);
$activeattempt = $atman->get_active_attempt($aiescape->id, $USER->id);

if (!$canstart && !$activeattempt) {
    echo $OUTPUT->notification(get_string('error:maxattemptsreached', 'mod_aiescape'), 'warning');

    if ($aiescape->allowstudentreview && has_capability('mod/aiescape:viewownattempts', $context)) {
        $myurl = new moodle_url('/mod/aiescape/myattempts.php', ['id' => $cm->id]);
        echo html_writer::link($myurl, get_string('viewattempts', 'mod_aiescape'), ['class' => 'btn btn-secondary mt-2']);
    }

    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Determine start/continue button label.
$startlabel = $activeattempt
    ? get_string('resumegame', 'mod_aiescape')
    : get_string('startgame', 'mod_aiescape');

// Optionally show AI provider info to teachers.
$aiproviderlabel = '';
$showaiinfo = get_config('mod_aiescape', 'showaiproviderinfo')
    && has_capability('mod/aiescape:viewreports', $context);
if ($showaiinfo) {
    $aimanager = \core\di::get(\core_ai\manager::class);
    $providers = $aimanager->get_provider_records(['enabled' => 1]);
    if (count($providers) === 1) {
        $provider = reset($providers);
        $actionconfig = json_decode($provider->actionconfig ?? '', true) ?? [];
        $model = $actionconfig['core_ai\\aiactions\\generate_text']['settings']['model'] ?? '';
        $aiproviderlabel = $provider->name . ($model ? ' — ' . $model : '');
    } else if (count($providers) > 1) {
        // Multiple providers are enabled; core_ai's own selection logic decides which one
        // actually handles a given request, so avoid presenting any single one as definitive.
        $aiproviderlabel = get_string('aiinfo_multipleproviders', 'mod_aiescape');
    }
}

// Progress images, ordered by file name.
$imageurls = aiescape_get_progress_image_urls($context);

// Render the game interface.
$templatecontext = [
    'cmid'            => $cm->id,
    'gamemode'        => $aiescape->gamemode,
    'showprogress'    => (bool) $aiescape->showprogress,
    'steps'           => (int) $aiescape->steps,
    'ismultichoice'   => in_array($aiescape->gamemode, ['multichoice', 'combo']),
    'isfreetext'      => in_array($aiescape->gamemode, ['freetext', 'combo']),
    'sendlabel'       => get_string('sendmessage', 'mod_aiescape'),
    'waitinglabel'    => get_string('waitingforai', 'mod_aiescape'),
    'progresslabel'   => get_string('progresslabel', 'mod_aiescape', ['tally' => 0, 'steps' => $aiescape->steps]),
    'placeholder'     => get_string('yourtextplaceholder', 'mod_aiescape'),
    'allowreview'     => $aiescape->allowstudentreview && has_capability('mod/aiescape:viewownattempts', $context),
    'myattemptsurl'   => (new moodle_url('/mod/aiescape/myattempts.php', ['id' => $cm->id]))->out(false),
    'myattemptslabel' => get_string('viewattempts', 'mod_aiescape'),
    'startlabel'      => $startlabel,
    'aiproviderlabel' => $aiproviderlabel,
    'quitlabel'       => get_string('quitattempt', 'mod_aiescape'),
    'quitconfirm'     => get_string('quitattempt_confirm', 'mod_aiescape'),
    'hasimages'       => !empty($imageurls),
    'firstimage'      => $imageurls[0] ?? '',
];

echo $OUTPUT->render_from_template('mod_aiescape/view', $templatecontext);

// While previewing, optionally reveal each choice's good/neutral/bad type on hover.
$showchoicehints = !empty($aiescape->previewhoverhints)
    && has_capability('mod/aiescape:viewreports', $context)
    && in_array($aiescape->gamemode, ['multichoice', 'combo'], true);

$PAGE->requires->js_call_amd('mod_aiescape/game', 'init', [$cm->id, $showchoicehints, $imageurls]);

echo html_writer::end_div();
echo $OUTPUT->footer();
