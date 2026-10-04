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
 * Unit tests for the quit_attempt external function.
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(quit_attempt::class)]
final class quit_attempt_test extends advanced_testcase {
    /**
     * Creates a course, an activity, two students and an in-progress attempt for the first.
     *
     * @param array $options Extra activity settings
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass} aiescape, owner, other student, attempt
     */
    private function setup_attempt(array $options = []): array {
        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module('aiescape', array_merge(['course' => $course->id], $options));
        $owner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $owner->id, 'stepstally' => 2]);
        return [$aiescape, $owner, $other, $attempt];
    }

    /**
     * The owner quits: the attempt is abandoned and, with partial scoring on,
     * the grade is the tally's share of the maximum.
     */
    public function test_owner_quits_with_partial_score(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, $owner, , $attempt] = $this->setup_attempt(['steps' => 4, 'grade' => 100, 'partialscoreonquit' => 1]);
        $this->setUser($owner);

        $result = quit_attempt::execute((int) $aiescape->cmid, $attempt->id);
        $result = external_api::clean_returnvalue(quit_attempt::execute_returns(), $result);

        $this->assertTrue($result['abandoned']);
        $this->assertEqualsWithDelta(50.0, $result['grade'], 0.001);
        $this->assertSame('abandoned', $DB->get_field('aiescape_attempts', 'status', ['id' => $attempt->id]));
    }

    /**
     * Without partial scoring, quitting awards nothing.
     */
    public function test_owner_quits_without_partial_score(): void {
        $this->resetAfterTest();
        [$aiescape, $owner, , $attempt] = $this->setup_attempt(['steps' => 4, 'partialscoreonquit' => 0]);
        $this->setUser($owner);

        $result = external_api::clean_returnvalue(
            quit_attempt::execute_returns(),
            quit_attempt::execute((int) $aiescape->cmid, $attempt->id)
        );
        $this->assertSame(0.0, (float) $result['grade']);
    }

    /**
     * Another student cannot quit someone else's attempt.
     */
    public function test_other_student_cannot_quit_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, , $other, $attempt] = $this->setup_attempt();
        $this->setUser($other);

        try {
            quit_attempt::execute((int) $aiescape->cmid, $attempt->id);
            $this->fail('Expected the foreign attempt to be rejected');
        } catch (\dml_missing_record_exception $e) {
            // Expected: the attempt is looked up scoped to the caller's own user id.
            $this->assertInstanceOf(\dml_missing_record_exception::class, $e);
        }
        $this->assertSame('inprogress', $DB->get_field('aiescape_attempts', 'status', ['id' => $attempt->id]));
    }

    /**
     * An attempt that is no longer in progress cannot be quit again.
     */
    public function test_cannot_quit_completed_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, $owner, , $attempt] = $this->setup_attempt();
        $DB->set_field('aiescape_attempts', 'status', 'completed', ['id' => $attempt->id]);
        $this->setUser($owner);

        try {
            quit_attempt::execute((int) $aiescape->cmid, $attempt->id);
            $this->fail('Expected error:invalidattempt');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:invalidattempt', $e->errorcode);
        }
        $this->assertSame('completed', $DB->get_field('aiescape_attempts', 'status', ['id' => $attempt->id]));
    }
}
