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
 * Student attempt history page for mod_aiescape.
 *
 * Only accessible when the teacher has enabled allowstudentreview.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiescape/lib.php');

$id        = required_param('id', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aiescape');
$aiescape = $DB->get_record('aiescape', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiescape:view', $context);
require_capability('mod/aiescape:viewownattempts', $context);

if (!$aiescape->allowstudentreview) {
    redirect(new moodle_url('/mod/aiescape/view.php', ['id' => $cm->id]));
}

$PAGE->set_url('/mod/aiescape/myattempts.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($aiescape->name) . ': ' . get_string('myattempts', 'mod_aiescape'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('myattemptsheading', 'mod_aiescape'), 3);

$atman = new \mod_aiescape\attempt_manager();

// Single attempt replay.
if ($attemptid) {
    $attempt = $DB->get_record(
        'aiescape_attempts',
        ['id' => $attemptid, 'aiescape' => $aiescape->id, 'userid' => $USER->id],
        '*',
        MUST_EXIST
    );

    $messages = $atman->get_attempt_messages($attemptid);

    echo html_writer::tag('p', get_string('attemptstarted', 'mod_aiescape') . ': ' . userdate($attempt->timecreated));

    if (empty($messages)) {
        echo $OUTPUT->notification(get_string('noattempts', 'mod_aiescape'), 'info');
    } else {
        echo html_writer::start_div(
            'aiescape-report-replay border rounded p-3 mb-3',
            ['style' => 'max-height:600px;overflow-y:auto;']
        );
        foreach ($messages as $msg) {
            $isuser = ($msg->role === 'user');
            $bg     = $isuser ? 'bg-primary text-white' : 'bg-light';
            echo html_writer::start_div("d-flex mb-2 " . ($isuser ? 'justify-content-end' : 'justify-content-start'));
            echo html_writer::tag(
                'div',
                format_text($msg->message, FORMAT_PLAIN),
                ['class' => "card $bg p-2 px-3", 'style' => 'max-width:80%;border-radius:1rem;']
            );
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }

    $backurl = new moodle_url('/mod/aiescape/myattempts.php', ['id' => $cm->id]);
    echo html_writer::link($backurl, get_string('backtolist', 'mod_aiescape'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

// List all own attempts.
$attempts = $atman->get_user_attempts($aiescape->id, $USER->id);

if (empty($attempts)) {
    echo $OUTPUT->notification(get_string('noattempts', 'mod_aiescape'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        '#',
        get_string('attemptstarted', 'mod_aiescape'),
        get_string('statuslabel', 'mod_aiescape'),
        '',
    ];
    $n = 1;
    foreach ($attempts as $attempt) {
        $statuskey = 'status_' . $attempt->status;
        $viewurl   = new moodle_url('/mod/aiescape/myattempts.php', ['id' => $cm->id, 'attemptid' => $attempt->id]);
        $table->data[] = [
            $n++,
            userdate($attempt->timecreated),
            get_string($statuskey, 'mod_aiescape'),
            html_writer::link($viewurl, get_string('viewattempt', 'mod_aiescape'), ['class' => 'btn btn-sm btn-outline-primary']),
        ];
    }
    echo html_writer::table($table);
}

$backurl = new moodle_url('/mod/aiescape/view.php', ['id' => $cm->id]);
echo html_writer::link($backurl, '&laquo; ' . get_string('modulename', 'mod_aiescape'), ['class' => 'btn btn-secondary mt-2']);

echo $OUTPUT->footer();
