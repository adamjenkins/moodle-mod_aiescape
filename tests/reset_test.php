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
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/aiescape/lib.php');

/**
 * Course reset tests for mod_aiescape (aiescape_reset_userdata).
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('aiescape_reset_userdata')]
final class reset_test extends advanced_testcase {
    /**
     * Creates an activity with an attempt, a message and a flag in a new course.
     *
     * @param array $options Extra activity settings
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass} course, aiescape, attempt
     */
    private function create_activity_with_data(array $options = []): array {
        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module('aiescape', array_merge(['course' => $course->id], $options));
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id]);
        $message = $generator->create_message(['attemptid' => $attempt->id, 'role' => 'user', 'message' => 'help me']);
        $generator->create_flag(['attemptid' => $attempt->id, 'messageid' => $message->id, 'keyword' => 'help']);
        return [$course, $aiescape, $attempt];
    }

    /**
     * Resetting with a new start date rolls the open/close dates by the same shift,
     * leaves unset dates at 0, and does not touch other courses.
     */
    public function test_reset_shifts_open_and_close_dates(): void {
        global $DB;
        $this->resetAfterTest();

        $timeopen = 1700000000;
        $timeclose = $timeopen + 7 * DAYSECS;
        [$course, $dated] = $this->create_activity_with_data(['timeopen' => $timeopen, 'timeclose' => $timeclose]);
        $undated = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id, 'timeopen' => 0, 'timeclose' => 0]
        );
        [, $otheractivity] = $this->create_activity_with_data(['timeopen' => $timeopen, 'timeclose' => $timeclose]);

        $shift = 365 * DAYSECS;
        $status = aiescape_reset_userdata((object) [
            'courseid' => $course->id,
            'timeshift' => $shift,
            'reset_aiescape_attempts' => 0,
        ]);

        $row = $DB->get_record('aiescape', ['id' => $dated->id], '*', MUST_EXIST);
        $this->assertEquals($timeopen + $shift, $row->timeopen);
        $this->assertEquals($timeclose + $shift, $row->timeclose);
        $row = $DB->get_record('aiescape', ['id' => $undated->id], '*', MUST_EXIST);
        $this->assertEquals(0, $row->timeopen);
        $this->assertEquals(0, $row->timeclose);
        $row = $DB->get_record('aiescape', ['id' => $otheractivity->id], '*', MUST_EXIST);
        $this->assertEquals($timeopen, $row->timeopen);
        $this->assertEquals($timeclose, $row->timeclose);

        $items = array_column($status, 'item');
        $this->assertContains(get_string('datechanged'), $items);
        // Attempts were not requested for reset, so they remain.
        $this->assertEquals(2, $DB->count_records('aiescape_attempts'));
    }

    /**
     * Without a time shift the dates are left alone.
     */
    public function test_reset_without_timeshift_keeps_dates(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $aiescape] = $this->create_activity_with_data(['timeopen' => 1700000000, 'timeclose' => 1700100000]);
        $status = aiescape_reset_userdata((object) [
            'courseid' => $course->id,
            'timeshift' => 0,
            'reset_aiescape_attempts' => 0,
        ]);

        $row = $DB->get_record('aiescape', ['id' => $aiescape->id], '*', MUST_EXIST);
        $this->assertEquals(1700000000, $row->timeopen);
        $this->assertEquals(1700100000, $row->timeclose);
        $this->assertSame([], $status);
    }

    /**
     * Resetting attempts removes attempts, messages and flags of this course only.
     */
    public function test_reset_attempts_deletes_only_this_course(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, , $attempt] = $this->create_activity_with_data();
        [, , $otherattempt] = $this->create_activity_with_data();

        aiescape_reset_userdata((object) [
            'courseid' => $course->id,
            'timeshift' => 0,
            'reset_aiescape_attempts' => 1,
        ]);

        $this->assertFalse($DB->record_exists('aiescape_attempts', ['id' => $attempt->id]));
        $this->assertFalse($DB->record_exists('aiescape_messages', ['attemptid' => $attempt->id]));
        $this->assertFalse($DB->record_exists('aiescape_flags', ['attemptid' => $attempt->id]));
        $this->assertTrue($DB->record_exists('aiescape_attempts', ['id' => $otherattempt->id]));
        $this->assertTrue($DB->record_exists('aiescape_messages', ['attemptid' => $otherattempt->id]));
        $this->assertTrue($DB->record_exists('aiescape_flags', ['attemptid' => $otherattempt->id]));
    }
}
