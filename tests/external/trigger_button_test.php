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
use mod_aiescape\attempt_manager;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/aiescape/tests/classes/fake_ai_manager_trait.php');

/**
 * Unit tests for the trigger_button external function.
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(trigger_button::class)]
final class trigger_button_test extends advanced_testcase {
    use \mod_aiescape\fake_ai_manager_trait;

    /**
     * Choices returned after a button press are sanitised for students exactly
     * like send_message responses: label + isfreeturn only, no type, while the
     * stored offered set keeps the types for next-turn validation.
     */
    public function test_choices_sanitised_for_students(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id, 'gamemode' => 'multichoice']
        );
        $cm = get_coursemodule_from_instance('aiescape', $aiescape->id);
        $cm->id = (int) $cm->id;
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id]);

        $buttonid = $DB->insert_record('aiescape_buttons', (object) [
            'aiescape'     => $aiescape->id,
            'label'        => 'Hint',
            'prompt'       => 'Give the student a small hint.',
            'sortorder'    => 0,
            'defaultindex' => null,
            'usagelimit'   => null,
        ]);

        $turn = json_encode([
            'narrative'  => 'A hint appears.',
            'completed'  => false,
            'stepchange' => 0,
            'choices'    => [
                ['label' => 'Go on', 'type' => 'good'],
                ['label' => 'Pause', 'type' => 'neutral'],
                ['label' => 'Give up', 'type' => 'bad'],
            ],
        ]);
        $callcount = 0;
        \core\di::set(\core_ai\manager::class, $this->fake_ai_manager([$turn], $callcount));
        $this->setUser($user);

        $result = trigger_button::execute($cm->id, $attempt->id, (int) $buttonid);

        $this->assertCount(3, $result['choices']);
        foreach ($result['choices'] as $choice) {
            $this->assertArrayHasKey('label', $choice);
            $this->assertArrayHasKey('isfreeturn', $choice);
            $this->assertArrayNotHasKey('type', $choice);
        }
        external_api::clean_returnvalue(trigger_button::execute_returns(), $result);

        $stored = (new attempt_manager())->get_offered_choices($attempt->id);
        foreach ($stored as $choice) {
            $this->assertContains($choice['type'], ['good', 'neutral', 'bad']);
        }
    }

    /**
     * A failed button turn persists neither the button prompt nor a reply, so a
     * button press whose AI turn fails does not strand the attempt or silently
     * consume one of the button's uses.
     */
    public function test_failed_button_turn_persists_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('choiceretrylimit', 0, 'mod_aiescape');

        $course = $this->getDataGenerator()->create_course();
        $aiescape = $this->getDataGenerator()->create_module(
            'aiescape',
            ['course' => $course->id, 'gamemode' => 'multichoice']
        );
        $cm = get_coursemodule_from_instance('aiescape', $aiescape->id);
        $cm->id = (int) $cm->id;
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id]);

        $buttonid = $DB->insert_record('aiescape_buttons', (object) [
            'aiescape'     => $aiescape->id,
            'label'        => 'Hint',
            'prompt'       => 'Give a hint.',
            'sortorder'    => 0,
            'defaultindex' => null,
            'usagelimit'   => 2,
        ]);

        \core\di::set(\core_ai\manager::class, $this->fake_failing_ai_manager());
        $this->setUser($user);

        try {
            trigger_button::execute($cm->id, $attempt->id, (int) $buttonid);
            $this->fail('Expected error:aifailed to be thrown');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('aifailed', $e->errorcode);
        }

        $this->assertCount(
            0,
            (new attempt_manager())->get_attempt_messages($attempt->id),
            'A failed button turn must not record the button prompt.'
        );
    }

    #[\Override]
    protected function tearDown(): void {
        // Undo any \core_ai\manager DI override so it doesn't leak into other tests.
        \core\di::reset_container();
        parent::tearDown();
    }
}
