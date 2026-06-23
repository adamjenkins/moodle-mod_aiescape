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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for \mod_aiescape\attempt_manager.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(attempt_manager::class)]
final class attempt_manager_test extends advanced_testcase {
    /**
     * Creates a course, an aiescape activity, and a student enrolled in it.
     *
     * @param array $aiescapeoptions
     * @return array{0: \stdClass, 1: \stdClass, 2: \cm_info, 3: \stdClass} aiescape, course, cm, user
     */
    private function setup_activity(array $aiescapeoptions = []): array {
        $course = $this->getDataGenerator()->create_course(
            isset($aiescapeoptions['completion']) ? ['enablecompletion' => 1] : []
        );
        $aiescape = $this->getDataGenerator()->create_module(
            'aiescape',
            array_merge(['course' => $course->id], $aiescapeoptions)
        );
        $aiescape->id = (int) $aiescape->id;
        $cm = get_coursemodule_from_instance('aiescape', $aiescape->id);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user->id = (int) $user->id;

        return [$aiescape, $course, \cm_info::create($cm), $user];
    }

    /**
     * get_active_attempt() returns null when there is no in-progress attempt.
     */
    public function test_get_active_attempt_returns_null_when_none_exists(): void {
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity();

        $manager = new attempt_manager();
        $this->assertNull($manager->get_active_attempt($aiescape->id, $user->id));
    }

    /**
     * can_start_new_attempt() allows unlimited attempts when maxattempts is -1.
     */
    public function test_can_start_new_attempt_unlimited(): void {
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity(['maxattempts' => -1]);

        $manager = new attempt_manager();
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($manager->can_start_new_attempt($aiescape, $user->id));
        }
    }

    /**
     * can_start_new_attempt() enforces the configured limit, excluding preview attempts.
     */
    public function test_can_start_new_attempt_respects_limit(): void {
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity(['maxattempts' => 2]);
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');

        $manager = new attempt_manager();
        $this->assertTrue($manager->can_start_new_attempt($aiescape, $user->id));

        $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id, 'status' => 'completed']);
        $this->assertTrue($manager->can_start_new_attempt($aiescape, $user->id));

        $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id, 'status' => 'completed']);
        $this->assertFalse($manager->can_start_new_attempt($aiescape, $user->id));

        // Preview attempts never count against the limit.
        $this->assertTrue($manager->can_start_new_attempt($aiescape, $user->id, true));
    }

    /**
     * get_or_create_attempt() reuses an existing in-progress attempt rather than duplicating it.
     */
    public function test_get_or_create_attempt_reuses_in_progress_attempt(): void {
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity();

        $manager = new attempt_manager();
        $first = $manager->get_or_create_attempt($aiescape, $user->id);
        $second = $manager->get_or_create_attempt($aiescape, $user->id);

        $this->assertEquals($first->id, $second->id);
    }

    /**
     * get_or_create_attempt() throws once the attempt limit has been exhausted.
     */
    public function test_get_or_create_attempt_throws_when_limit_reached(): void {
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity(['maxattempts' => 1]);
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id, 'status' => 'completed']);

        $manager = new attempt_manager();
        $this->expectException(\moodle_exception::class);
        $manager->get_or_create_attempt($aiescape, $user->id);
    }

    /**
     * update_tally() applies the delta but never lets the running total go negative.
     */
    public function test_update_tally_floors_at_zero(): void {
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity();
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $user->id, 'stepstally' => 0,
        ]);

        $manager = new attempt_manager();
        $manager->update_tally($attempt, -1);
        $this->assertSame(0, $attempt->stepstally);

        $manager->update_tally($attempt, 1);
        $this->assertSame(1, $attempt->stepstally);
    }

    /**
     * usage_remaining() returns -1 (unlimited) when no usage limit is configured.
     */
    public function test_usage_remaining_unlimited(): void {
        $button = (object) ['label' => 'Hint', 'usagelimit' => null];
        $this->assertSame(-1, attempt_manager::usage_remaining($button, []));
    }

    /**
     * usage_remaining() counts prior uses of the button by label and never goes negative.
     */
    public function test_usage_remaining_counts_prior_uses(): void {
        $button = (object) ['label' => 'Hint', 'usagelimit' => 2];
        $messages = [
            (object) ['role' => 'user', 'choicetype' => 'Hint'],
            (object) ['role' => 'assistant', 'choicetype' => null],
            (object) ['role' => 'user', 'choicetype' => 'Hint'],
            (object) ['role' => 'user', 'choicetype' => 'Other'],
        ];

        $this->assertSame(0, attempt_manager::usage_remaining($button, $messages));

        $button->usagelimit = 5;
        $this->assertSame(3, attempt_manager::usage_remaining($button, $messages));
    }

    /**
     * flag_message_if_matched() records a flag when a configured keyword matches, case-insensitively.
     */
    public function test_flag_message_if_matched_creates_flag_on_match(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity();
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id]);
        $message = $generator->create_message([
            'attemptid' => $attempt->id, 'role' => 'user', 'message' => 'I want to HURT someone',
        ]);

        $manager = new attempt_manager();
        $manager->flag_message_if_matched($message->id, $attempt->id, $message->message, "hurt\nself-harm");

        $flags = $DB->get_records('aiescape_flags', ['attemptid' => $attempt->id]);
        $this->assertCount(1, $flags);
        $this->assertSame('hurt', reset($flags)->keyword);
    }

    /**
     * flag_message_if_matched() does nothing when there is no keyword match.
     */
    public function test_flag_message_if_matched_no_match_no_flag(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, , , $user] = $this->setup_activity();
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id]);
        $message = $generator->create_message([
            'attemptid' => $attempt->id, 'role' => 'user', 'message' => 'Everything is fine',
        ]);

        $manager = new attempt_manager();
        $manager->flag_message_if_matched($message->id, $attempt->id, $message->message, "hurt\nself-harm");

        $this->assertCount(0, $DB->get_records('aiescape_flags', ['attemptid' => $attempt->id]));
    }

    /**
     * complete_attempt() marks the attempt completed and triggers Moodle activity completion.
     */
    public function test_complete_attempt_marks_completed_and_updates_completion(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        [$aiescape, $course, $cm, $user] = $this->setup_activity(['completion' => COMPLETION_TRACKING_AUTOMATIC]);
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $user->id, 'stepstally' => $aiescape->steps,
        ]);

        $manager = new attempt_manager();
        $manager->complete_attempt($attempt, $aiescape, $course, $cm);

        $updated = $DB->get_record('aiescape_attempts', ['id' => $attempt->id]);
        $this->assertSame('completed', $updated->status);
        $this->assertNotEmpty($updated->timecompleted);
    }

    /**
     * abandon_attempt() awards a proportional partial grade when enabled.
     */
    public function test_abandon_attempt_awards_partial_grade_when_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        [$aiescape, $course, $cm, $user] = $this->setup_activity([
            'partialscoreonquit' => 1, 'steps' => 10, 'grade' => 100,
        ]);
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $user->id, 'stepstally' => 5,
        ]);

        $manager = new attempt_manager();
        $grade = $manager->abandon_attempt($attempt, $aiescape, $course, $cm);

        $this->assertEqualsWithDelta(50.0, $grade, 0.01);
        $updated = $DB->get_record('aiescape_attempts', ['id' => $attempt->id]);
        $this->assertSame('abandoned', $updated->status);
    }

    /**
     * abandon_attempt() awards no grade when partial scoring is disabled.
     */
    public function test_abandon_attempt_no_grade_when_disabled(): void {
        $this->resetAfterTest();
        [$aiescape, $course, $cm, $user] = $this->setup_activity(['partialscoreonquit' => 0]);
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $user->id, 'stepstally' => 5,
        ]);

        $manager = new attempt_manager();
        $grade = $manager->abandon_attempt($attempt, $aiescape, $course, $cm);

        $this->assertSame(0.0, $grade);
    }
}
