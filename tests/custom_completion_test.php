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

namespace mod_aiescape\completion;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Unit tests for \mod_aiescape\completion\custom_completion.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(custom_completion::class)]
final class custom_completion_test extends advanced_testcase {
    /**
     * get_defined_custom_rules() exposes exactly the one supported rule.
     */
    public function test_get_defined_custom_rules(): void {
        $rules = custom_completion::get_defined_custom_rules();
        $this->assertSame(['completioncompleted'], $rules);
    }

    /**
     * get_custom_rule_descriptions() provides a description for every defined rule.
     */
    public function test_get_custom_rule_descriptions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module('aiescape', ['course' => $course->id]);
        $cm = \cm_info::create(get_coursemodule_from_instance('aiescape', $aiescape->id));

        $completion = new custom_completion($cm, 0);
        $descriptions = $completion->get_custom_rule_descriptions();

        foreach (custom_completion::get_defined_custom_rules() as $rule) {
            $this->assertArrayHasKey($rule, $descriptions);
            $this->assertNotEmpty($descriptions[$rule]);
        }
    }

    /**
     * get_state() reports incomplete when the user has no completed attempt.
     */
    public function test_get_state_incomplete_when_no_completed_attempt(): void {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $aiescape = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id],
            ['completion' => COMPLETION_TRACKING_AUTOMATIC]
        );
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = \cm_info::create(get_coursemodule_from_instance('aiescape', $aiescape->id));

        $completion = new custom_completion($cm, (int) $user->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completioncompleted'));
    }

    /**
     * get_state() reports complete once the user has a completed attempt.
     */
    public function test_get_state_complete_when_attempt_completed(): void {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $aiescape = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id],
            ['completion' => COMPLETION_TRACKING_AUTOMATIC]
        );
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = \cm_info::create(get_coursemodule_from_instance('aiescape', $aiescape->id));

        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $generator->create_attempt([
            'aiescape' => $aiescape->id, 'userid' => $user->id, 'status' => 'completed',
        ]);

        $completion = new custom_completion($cm, (int) $user->id);
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completioncompleted'));
    }

    /**
     * get_state() rejects rules it doesn't define.
     */
    public function test_get_state_rejects_undefined_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module('aiescape', ['course' => $course->id]);
        $cm = \cm_info::create(get_coursemodule_from_instance('aiescape', $aiescape->id));

        $completion = new custom_completion($cm, 0);
        $this->expectException(\coding_exception::class);
        $completion->get_state('somenonexistentrule');
    }

    /**
     * aiescape_get_coursemodule_info() only exposes the rule when completion tracking is automatic.
     */
    public function test_coursemodule_info_exposes_rule_only_when_automatic(): void {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $manual = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id],
            ['completion' => COMPLETION_TRACKING_MANUAL]
        );
        $manualcm = get_coursemodule_from_instance('aiescape', $manual->id);
        $manualinfo = aiescape_get_coursemodule_info($manualcm);
        $this->assertEmpty($manualinfo->customdata['customcompletionrules'] ?? null);

        $auto = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id],
            ['completion' => COMPLETION_TRACKING_AUTOMATIC]
        );
        $autocm = get_coursemodule_from_instance('aiescape', $auto->id);
        $autoinfo = aiescape_get_coursemodule_info($autocm);
        $this->assertSame(1, $autoinfo->customdata['customcompletionrules']['completioncompleted']);
    }
}
