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

namespace mod_aiescape\external;

use advanced_testcase;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the start_attempt external function.
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(start_attempt::class)]
final class start_attempt_test extends advanced_testcase {
    /**
     * Creates a course, an activity and an enrolled user.
     *
     * @param array $options Extra activity settings
     * @param string $role Role to enrol the user with
     * @return array{0: \stdClass, 1: \stdClass} aiescape, user
     */
    private function setup_activity(array $options = [], string $role = 'student'): array {
        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module('aiescape', array_merge(['course' => $course->id], $options));
        $user = $this->getDataGenerator()->create_and_enrol($course, $role);
        return [$aiescape, $user];
    }

    /**
     * A student starts a new attempt, and a second call resumes the same one.
     */
    public function test_start_creates_then_resumes_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, $user] = $this->setup_activity(['steps' => 4]);
        $this->setUser($user);

        $first = external_api::clean_returnvalue(start_attempt::execute_returns(), start_attempt::execute((int) $aiescape->cmid));
        $attempt = $DB->get_record('aiescape_attempts', ['id' => $first['attemptid']], '*', MUST_EXIST);
        $this->assertEquals($user->id, $attempt->userid);
        $this->assertEquals($aiescape->id, $attempt->aiescape);
        $this->assertSame('inprogress', $attempt->status);
        $this->assertEquals(0, $attempt->ispreview);
        $this->assertSame(4, $first['steps']);
        $this->assertFalse($first['completed']);

        $second = external_api::clean_returnvalue(start_attempt::execute_returns(), start_attempt::execute((int) $aiescape->cmid));
        $this->assertSame($first['attemptid'], $second['attemptid']);
        $this->assertEquals(1, $DB->count_records('aiescape_attempts', ['aiescape' => $aiescape->id]));
    }

    /**
     * A student cannot start before the open date.
     */
    public function test_student_blocked_before_open(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, $user] = $this->setup_activity(['timeopen' => time() + DAYSECS]);
        $this->setUser($user);

        try {
            start_attempt::execute((int) $aiescape->cmid);
            $this->fail('Expected error:notopenyet');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:notopenyet', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records('aiescape_attempts'));
    }

    /**
     * After the close date a student is refused and their open attempt is abandoned.
     */
    public function test_student_blocked_after_close_and_attempt_abandoned(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, $user] = $this->setup_activity(['timeclose' => time() - HOURSECS]);
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id]);
        $this->setUser($user);

        try {
            start_attempt::execute((int) $aiescape->cmid);
            $this->fail('Expected error:closedon');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:closedon', $e->errorcode);
        }
        $this->assertSame('abandoned', $DB->get_field('aiescape_attempts', 'status', ['id' => $attempt->id]));
    }

    /**
     * A teacher (viewreports) may preview a closed activity; the attempt is a preview.
     */
    public function test_teacher_previews_closed_activity(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, $user] = $this->setup_activity(['timeclose' => time() - HOURSECS], 'editingteacher');
        $this->setUser($user);

        $result = external_api::clean_returnvalue(start_attempt::execute_returns(), start_attempt::execute((int) $aiescape->cmid));
        $this->assertEquals(1, $DB->get_field('aiescape_attempts', 'ispreview', ['id' => $result['attemptid']]));
    }

    /**
     * A user without mod/aiescape:play (not enrolled) cannot start.
     */
    public function test_unenrolled_user_cannot_start(): void {
        $this->resetAfterTest();
        [$aiescape] = $this->setup_activity();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        start_attempt::execute((int) $aiescape->cmid);
    }
}
