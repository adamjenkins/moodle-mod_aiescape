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
 * Teacher attempt report for mod_aiescape.
 *
 * Supports three views:
 *   1. Student list (default)
 *   2. Attempt list for a specific student (?userid=X)
 *   3. Full conversation replay (?attemptid=Y)
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiescape/lib.php');

$id        = required_param('id', PARAM_INT);
$userid    = optional_param('userid', 0, PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$flagged   = optional_param('flagged', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aiescape');
$aiescape = $DB->get_record('aiescape', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiescape:viewreports', $context);

$PAGE->set_url('/mod/aiescape/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($aiescape->name) . ': ' . get_string('report', 'mod_aiescape'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

$backtoactivity = new moodle_url('/mod/aiescape/view.php', ['id' => $cm->id]);
$atman = new \mod_aiescape\attempt_manager();

// View 4: flagged messages across all attempts for this activity.
if ($flagged) {
    echo $OUTPUT->heading(get_string('flaggedreportheading', 'mod_aiescape'), 3);

    $sql = "SELECT f.id, f.keyword, f.timecreated, m.message, f.attemptid, aa.userid
              FROM {aiescape_flags} f
              JOIN {aiescape_messages} m ON m.id = f.messageid
              JOIN {aiescape_attempts} aa ON aa.id = f.attemptid
             WHERE aa.aiescape = :aiescape AND aa.ispreview = 0
          ORDER BY f.timecreated DESC";
    $flags = $DB->get_records_sql($sql, ['aiescape' => $aiescape->id]);

    if (empty($flags)) {
        echo $OUTPUT->notification(get_string('noflagged', 'mod_aiescape'), 'info');
    } else {
        $userids = array_unique(array_map(fn($f) => $f->userid, $flags));
        $users   = $DB->get_records_list('user', 'id', $userids);

        $table = new html_table();
        $table->head = [
            get_string('student', 'mod_aiescape'),
            get_string('keyword', 'mod_aiescape'),
            get_string('messageexcerpt', 'mod_aiescape'),
            get_string('attemptstarted', 'mod_aiescape'),
            '',
        ];
        foreach ($flags as $flag) {
            $viewurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id, 'attemptid' => $flag->attemptid]);
            $table->data[] = [
                fullname($users[$flag->userid]),
                s($flag->keyword),
                s($flag->message),
                userdate($flag->timecreated),
                html_writer::link(
                    $viewurl,
                    get_string('viewattempt', 'mod_aiescape'),
                    ['class' => 'btn btn-sm btn-outline-primary']
                ),
            ];
        }
        echo html_writer::table($table);
    }

    $backurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id]);
    echo html_writer::link($backurl, get_string('backtolist', 'mod_aiescape'), ['class' => 'btn btn-secondary mt-2']);
    echo $OUTPUT->footer();
    exit;
}

// View 3: single attempt replay.
if ($attemptid) {
    $attempt = $DB->get_record('aiescape_attempts', ['id' => $attemptid, 'aiescape' => $aiescape->id], '*', MUST_EXIST);
    $user = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

    // Determine sequential attempt number for this user (not the raw DB id).
    $userattempts = $atman->get_user_attempts($aiescape->id, $attempt->userid);
    $seqnum = 1;
    foreach (array_reverse($userattempts) as $i => $a) {
        if ($a->id === $attempt->id) {
            $seqnum = $i + 1;
            break;
        }
    }
    echo $OUTPUT->heading(get_string('attemptnumber', 'mod_aiescape', $seqnum), 3);
    echo html_writer::tag('p', fullname($user) . ' &mdash; ' . userdate($attempt->timecreated));

    $messages   = $atman->get_attempt_messages($attemptid);
    $flaggedids = $DB->get_records_menu('aiescape_flags', ['attemptid' => $attemptid], '', 'messageid, keyword');

    if (empty($messages)) {
        echo $OUTPUT->notification(get_string('noattempts', 'mod_aiescape'), 'info');
    } else {
        echo html_writer::start_div(
            'aiescape-report-replay border rounded p-3 mb-3',
            ['style' => 'max-height:600px;overflow-y:auto;']
        );
        foreach ($messages as $msg) {
            $isuser    = ($msg->role === 'user');
            $align     = $isuser ? 'text-end' : 'text-start';
            $bg        = $isuser ? 'bg-primary text-white' : 'bg-light';
            $isflagged = isset($flaggedids[$msg->id]);
            $cardclass = "card $bg p-2 px-3" . ($isflagged ? ' border border-warning border-3' : '');

            echo html_writer::start_div("d-flex mb-2 " . ($isuser ? 'justify-content-end' : 'justify-content-start'));
            echo html_writer::start_tag('div', ['class' => $cardclass, 'style' => 'max-width:80%;border-radius:1rem;']);
            echo format_text($msg->message, FORMAT_PLAIN);
            if ($isflagged) {
                echo html_writer::tag(
                    'div',
                    get_string('matchedkeyword', 'mod_aiescape', s($flaggedids[$msg->id])),
                    ['class' => 'badge bg-warning text-dark mt-1']
                );
            }
            echo html_writer::end_tag('div');
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }

    $backurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id, 'userid' => $attempt->userid]);
    echo html_writer::link($backurl, get_string('backtolist', 'mod_aiescape'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

// View 2: attempts for one student.
if ($userid) {
    // Ensure the requested user has data in this activity before disclosing their name.
    if (!$DB->record_exists('aiescape_attempts', ['aiescape' => $aiescape->id, 'userid' => $userid, 'ispreview' => 0])) {
        redirect(new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id]));
    }
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    echo $OUTPUT->heading(get_string('reportheading', 'mod_aiescape', fullname($user)), 3);

    $attempts = $atman->get_user_attempts($aiescape->id, $userid);
    if (empty($attempts)) {
        echo $OUTPUT->notification(get_string('noattempts', 'mod_aiescape'), 'info');
    } else {
        $table = new html_table();
        $table->head = [
            '#',
            get_string('attemptstarted', 'mod_aiescape'),
            get_string('statuslabel', 'mod_aiescape'),
            get_string('attemptfinished', 'mod_aiescape'),
            '',
        ];
        $n = 1;
        foreach ($attempts as $attempt) {
            $statuskey = 'status_' . $attempt->status;
            $viewurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id, 'attemptid' => $attempt->id]);
            $table->data[] = [
                $n++,
                userdate($attempt->timecreated),
                get_string($statuskey, 'mod_aiescape'),
                $attempt->timecompleted ? userdate($attempt->timecompleted) : '-',
                html_writer::link(
                    $viewurl,
                    get_string('viewattempt', 'mod_aiescape'),
                    ['class' => 'btn btn-sm btn-outline-primary']
                ),
            ];
        }
        echo html_writer::table($table);
    }

    $backurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id]);
    echo html_writer::link($backurl, get_string('backtolist', 'mod_aiescape'), ['class' => 'btn btn-secondary mt-2']);
    echo $OUTPUT->footer();
    exit;
}

// View 1: student list.
echo $OUTPUT->heading(get_string('report', 'mod_aiescape'), 3);

if (!empty($aiescape->flagkeywords)) {
    $flaggedcount = $DB->count_records_sql(
        'SELECT COUNT(f.id)
           FROM {aiescape_flags} f
           JOIN {aiescape_attempts} aa ON aa.id = f.attemptid
          WHERE aa.aiescape = :aiescape',
        ['aiescape' => $aiescape->id]
    );
    $flaggedurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id, 'flagged' => 1]);
    echo html_writer::div(
        html_writer::link(
            $flaggedurl,
            get_string('viewflaggedattempts', 'mod_aiescape', $flaggedcount),
            ['class' => 'btn btn-outline-warning btn-sm']
        ),
        'mb-3'
    );
}

// Extra identity fields (e.g. email, idnumber) based on site showuseridentity setting.
$identityfields = \core_user\fields::get_identity_fields($context, false);

// Fetch enrolled students (play cap) and anyone who has attempted.
$enrolled = get_enrolled_users($context, 'mod/aiescape:play', 0, 'u.*');

// Teachers/managers also hold the play capability (so they can preview), but their
// preview attempts shouldn't make them show up in the student report list.
foreach ($enrolled as $enrolledid => $enrolleduser) {
    if (has_capability('mod/aiescape:viewreports', $context, $enrolleduser->id)) {
        unset($enrolled[$enrolledid]);
    }
}

$baseuserfields = [
    'id', 'firstname', 'lastname', 'email', 'firstnamephonetic', 'lastnamephonetic',
    'middlename', 'alternatename', 'picture', 'imagealt', 'deleted',
    'suspended', 'mnethostid', 'auth', 'confirmed',
];
$extraselect = '';
foreach ($identityfields as $field) {
    if (!in_array($field, $baseuserfields)) {
        $extraselect .= ', u.' . $field;
    }
}

$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.firstnamephonetic,
               u.lastnamephonetic, u.middlename, u.alternatename, u.picture,
               u.imagealt, u.deleted, u.suspended, u.mnethostid, u.auth, u.confirmed
               {$extraselect}
          FROM {user} u
          JOIN {aiescape_attempts} aa ON aa.userid = u.id
         WHERE aa.aiescape = :aiescape AND aa.ispreview = 0";
$attemptors = $DB->get_records_sql($sql, ['aiescape' => $aiescape->id]);
foreach ($attemptors as $attemptorid => $attemptor) {
    if (has_capability('mod/aiescape:viewreports', $context, $attemptor->id)) {
        unset($attemptors[$attemptorid]);
    }
}

$students = $enrolled + array_diff_key($attemptors, $enrolled);

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nousers', 'mod_aiescape'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Build per-student stats rows for sorting.
$rows = [];
foreach ($students as $student) {
    $userattempts = $atman->get_user_attempts($aiescape->id, $student->id);
    $numcompleted = count(array_filter($userattempts, fn($a) => $a->status === 'completed'));
    $numabandoned = count(array_filter($userattempts, fn($a) => $a->status === 'abandoned'));
    $lasttimestamp = !empty($userattempts) ? reset($userattempts)->timecreated : 0;

    if ($numcompleted > 0) {
        $gradevalue = (float) $aiescape->grade;
    } else if ($aiescape->partialscoreonquit) {
        $gradevalue = 0.0;
        foreach ($userattempts as $a) {
            if ($a->status === 'abandoned' && $aiescape->steps > 0) {
                $partial = round($aiescape->grade * min(1.0, $a->stepstally / $aiescape->steps), 1);
                $gradevalue = max($gradevalue, $partial);
            }
        }
    } else {
        $gradevalue = 0.0;
    }

    $rows[] = [
        'student'    => $student,
        'sortname'   => strtolower($student->lastname . ' ' . $student->firstname),
        'attempts'   => count($userattempts),
        'abandoned'  => $numabandoned,
        'completed'  => $numcompleted,
        'grade'      => $gradevalue,
        'lastattempt' => $lasttimestamp,
    ];
}

// Set up flexible_table for sortable headers.
$table = new flexible_table('aiescape-report-' . $cm->id);
$table->define_baseurl(new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id]));

$columns = ['fullname'];
$headers = [get_string('student', 'mod_aiescape')];
foreach ($identityfields as $field) {
    $columns[] = $field;
    $headers[] = \core_user\fields::get_display_name($field);
}
$columns = array_merge($columns, ['attempts', 'abandoned', 'completed', 'grade', 'lastattempt', 'actions']);
$headers = array_merge($headers, [
    get_string('attempts', 'mod_aiescape'),
    get_string('abandoned', 'mod_aiescape'),
    get_string('completed', 'mod_aiescape'),
    get_string('grademax', 'mod_aiescape', $aiescape->grade),
    get_string('lastattempt', 'mod_aiescape'),
    '',
]);

$table->define_columns($columns);
$table->define_headers($headers);
$table->sortable(true, 'fullname', SORT_ASC);
$table->no_sorting('actions');
foreach ($identityfields as $field) {
    $table->no_sorting($field);
}
$table->setup();

// Sort rows based on URL params set by flexible_table sort links.
$tsort = optional_param('tsort', 'fullname', PARAM_ALPHANUMEXT);
$tdir  = optional_param('tdir', SORT_ASC, PARAM_INT);

usort($rows, function ($a, $b) use ($tsort, $tdir) {
    $s = $a['student'];
    $t = $b['student'];
    $cmp = match ($tsort) {
        'attempts'    => $a['attempts'] <=> $b['attempts'],
        'abandoned'   => $a['abandoned'] <=> $b['abandoned'],
        'completed'   => $a['completed'] <=> $b['completed'],
        'grade'       => $a['grade'] <=> $b['grade'],
        'lastattempt' => $a['lastattempt'] <=> $b['lastattempt'],
        'firstname'   => strnatcasecmp($s->firstname . ' ' . $s->lastname, $t->firstname . ' ' . $t->lastname),
        'lastname'    => strnatcasecmp($s->lastname . ' ' . $s->firstname, $t->lastname . ' ' . $t->firstname),
        default       => strnatcasecmp($a['sortname'], $b['sortname']),
    };
    return ($tdir === SORT_DESC) ? -$cmp : $cmp;
});

foreach ($rows as $row) {
    $student   = $row['student'];
    $detailurl = new moodle_url('/mod/aiescape/report.php', ['id' => $cm->id, 'userid' => $student->id]);

    $cells = [fullname($student)];
    foreach ($identityfields as $field) {
        $cells[] = s($student->$field ?? '');
    }
    $cells[] = $row['attempts'] ?: '-';
    $cells[] = $row['abandoned'] ?: '-';
    $cells[] = $row['completed'] ?: '-';
    $cells[] = $row['grade'] > 0 ? $row['grade'] : '-';
    $cells[] = $row['lastattempt'] ? userdate($row['lastattempt']) : '-';
    $cells[] = html_writer::link(
        $detailurl,
        get_string('viewattempt', 'mod_aiescape'),
        ['class' => 'btn btn-sm btn-outline-primary']
    );
    $table->add_data($cells);
}

$table->finish_output();
echo $OUTPUT->footer();
