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

declare(strict_types=1);

namespace mod_aiescape;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/aiescape/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests for grade-based completion in mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_aiescape\attempt_manager
 */
final class grade_completion_test extends advanced_testcase {
    /**
     * Helper: create course + aiescape instance + student, with grade-based completion.
     *
     * @param bool $usepassgrade  Whether to require a passing grade (vs. any grade)
     * @param float $gradepass    The passing threshold (only used when $usepassgrade=true)
     * @return array{aiescape: \stdClass, course: \stdClass, cm: \cm_info, student: \stdClass, gradeitem: \grade_item}
     */
    private function setup_graded_activity(bool $usepassgrade = false, float $gradepass = 50.0): array {
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Completion options: require a grade (and optionally a passing grade).
        $completionopts = [
            'completion'             => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade'     => 1,
            'completionpassgrade'    => $usepassgrade ? 1 : 0,
            'completiongradeitemnumber' => 0,
        ];

        $aiescape = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id, 'grade' => 100],
            $completionopts
        );

        // Set gradepass on the grade item directly.
        $gradeitem = \grade_item::fetch([
            'courseid'     => $course->id,
            'itemtype'     => 'mod',
            'itemmodule'   => 'aiescape',
            'iteminstance' => $aiescape->id,
            'itemnumber'   => 0,
        ]);
        $this->assertNotEmpty($gradeitem, 'Grade item must exist after add_instance');

        if ($usepassgrade) {
            $gradeitem->gradepass = $gradepass;
            $gradeitem->update();
        }

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = \cm_info::create(get_coursemodule_from_instance('aiescape', $aiescape->id));

        return [
            'aiescape'  => $aiescape,
            'course'    => $course,
            'cm'        => $cm,
            'student'   => $student,
            'gradeitem' => $gradeitem,
        ];
    }

    /**
     * Grade item is created when the activity is created.
     */
    public function test_grade_item_created_on_add_instance(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module('aiescape', ['course' => $course->id, 'grade' => 100]);

        $gradeitem = \grade_item::fetch([
            'courseid'     => $course->id,
            'itemtype'     => 'mod',
            'itemmodule'   => 'aiescape',
            'iteminstance' => $aiescape->id,
            'itemnumber'   => 0,
        ]);

        $this->assertNotEmpty($gradeitem);
        $this->assertEquals(100, $gradeitem->grademax);
        $this->assertEquals(GRADE_TYPE_VALUE, $gradeitem->gradetype);
    }

    /**
     * aiescape_update_grades() writes the correct rawgrade for a completed attempt.
     */
    public function test_grade_written_when_attempt_completed(): void {
        $this->resetAfterTest();
        ['aiescape' => $aiescape, 'student' => $student, 'gradeitem' => $gradeitem]
            = $this->setup_graded_activity();

        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id, 'status' => 'completed',
        ]);

        aiescape_update_grades($aiescape, $student->id);

        $grade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student->id]);
        $this->assertNotEmpty($grade, 'A grade record must exist after update_grades');
        $this->assertEquals(100.0, (float) $grade->rawgrade);
    }

    /**
     * Completion state is COMPLETION_COMPLETE when completionusegrade=1
     * and the student has a grade.
     */
    public function test_completion_complete_when_grade_exists(): void {
        $this->resetAfterTest();
        ['aiescape' => $aiescape, 'course' => $course, 'cm' => $cm, 'student' => $student]
            = $this->setup_graded_activity(
                usepassgrade: false
            );

        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id, 'status' => 'completed',
        ]);

        $manager = new attempt_manager();
        $manager->complete_attempt($attempt, $aiescape, $course, $cm);

        $completion = new \completion_info($course);
        $data = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $data->completionstate);
    }

    /**
     * Completion state is COMPLETION_COMPLETE_PASS when completionpassgrade=1
     * and the student's grade meets the passing threshold.
     */
    public function test_completion_complete_pass_when_grade_passes(): void {
        $this->resetAfterTest();
        // Grade pass = 50; student will get 100 (full marks), so should be PASS.
        ['aiescape' => $aiescape, 'course' => $course, 'cm' => $cm, 'student' => $student]
            = $this->setup_graded_activity(
                usepassgrade: true,
                gradepass: 50.0
            );

        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id, 'status' => 'completed',
        ]);

        $manager = new attempt_manager();
        $manager->complete_attempt($attempt, $aiescape, $course, $cm);

        $completion = new \completion_info($course);
        $data = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(
            COMPLETION_COMPLETE_PASS,
            $data->completionstate,
            'Student who got full marks should have COMPLETION_COMPLETE_PASS'
        );
    }

    /**
     * Preview attempts are excluded from grade_update so a teacher previewing
     * the activity does not corrupt the gradebook.
     */
    public function test_preview_attempt_does_not_write_grade(): void {
        $this->resetAfterTest();
        ['aiescape' => $aiescape, 'student' => $student, 'gradeitem' => $gradeitem]
            = $this->setup_graded_activity();

        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape'  => $aiescape->id,
            'userid'    => $student->id,
            'status'    => 'completed',
            'ispreview' => 1,
        ]);

        // Calling complete_attempt() skips grade + completion for preview attempts.
        $cm = \cm_info::create(get_coursemodule_from_instance('aiescape', $aiescape->id));
        $course = get_course($aiescape->course);
        $manager = new attempt_manager();
        $manager->complete_attempt($attempt, $aiescape, $course, $cm);

        $grade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student->id]);
        $this->assertEmpty($grade, 'Preview attempts must not write a grade');
    }
}
