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

/**
 * Tests for aiescape_get_user_grades().
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::aiescape_get_user_grades
 */
final class user_grades_test extends advanced_testcase {
    /**
     * Creates a course, an activity with partial score on quit, and a student.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \mod_aiescape_generator}
     */
    private function setup_activity(): array {
        $course = $this->getDataGenerator()->create_course(['summary' => '']);
        $aiescape = $this->getDataGenerator()->create_module('aiescape', [
            'course'             => $course->id,
            'grade'              => 100,
            'steps'              => 5,
            'partialscoreonquit' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        return [$aiescape, $student, $generator];
    }

    /**
     * Two abandoned attempts, low then high: the higher partial grade is kept.
     */
    public function test_best_abandoned_attempt_is_kept(): void {
        $this->resetAfterTest();
        [$aiescape, $student, $generator] = $this->setup_activity();

        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id,
            'status' => 'abandoned', 'stepstally' => 1, 'timecompleted' => 1000,
        ]);
        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id,
            'status' => 'abandoned', 'stepstally' => 4, 'timecompleted' => 2000,
        ]);

        $grades = aiescape_get_user_grades($aiescape, $student->id);
        $this->assertArrayHasKey($student->id, $grades);
        $this->assertEqualsWithDelta(80.0, $grades[$student->id]->rawgrade, 0.0001);
        $this->assertEquals(2000, $grades[$student->id]->dategraded);
    }

    /**
     * Two abandoned attempts, high then low: the earlier higher grade stays.
     */
    public function test_lower_later_abandoned_attempt_does_not_replace_best(): void {
        $this->resetAfterTest();
        [$aiescape, $student, $generator] = $this->setup_activity();

        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id,
            'status' => 'abandoned', 'stepstally' => 3, 'timecompleted' => 1000,
        ]);
        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id,
            'status' => 'abandoned', 'stepstally' => 1, 'timecompleted' => 2000,
        ]);

        $grades = aiescape_get_user_grades($aiescape);
        $this->assertEqualsWithDelta(60.0, $grades[$student->id]->rawgrade, 0.0001);
    }

    /**
     * A completed attempt keeps the full grade over any abandoned attempt.
     */
    public function test_completed_attempt_beats_abandoned(): void {
        $this->resetAfterTest();
        [$aiescape, $student, $generator] = $this->setup_activity();

        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id,
            'status' => 'completed', 'stepstally' => 5, 'timecompleted' => 1000,
        ]);
        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $student->id,
            'status' => 'abandoned', 'stepstally' => 4, 'timecompleted' => 2000,
        ]);

        $grades = aiescape_get_user_grades($aiescape, $student->id);
        $this->assertEqualsWithDelta(100.0, $grades[$student->id]->rawgrade, 0.0001);
        $this->assertEquals(1000, $grades[$student->id]->dategraded);
    }
}
