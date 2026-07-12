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

use restore_date_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');

/**
 * Backup/restore round-trip tests for mod_aiescape.
 *
 * Guards against regressing the bug where several aiescape fields, the
 * usagelimit/ispreview columns, and the entire aiescape_flags table were
 * silently dropped on backup/restore.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_aiescape_activity_structure_step::class)]
#[CoversClass(\restore_aiescape_activity_structure_step::class)]
final class backup_restore_test extends restore_date_testcase {
    /**
     * Every configuration field on the main activity table round-trips through backup/restore.
     */
    public function test_backup_restore_roundtrips_activity_fields(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['startdate' => $this->startdate]);
        $original = $this->getDataGenerator()->create_module('aiescape', [
            'course'              => $course->id,
            'gamestyle'           => 'persona',
            'personaname'         => 'The Merchant',
            'gamemode'            => 'combo',
            'choicesgood'         => 2,
            'choicesneutral'      => 3,
            'choicesbad'          => 1,
            'showpremise'         => 1,
            'showgoal'            => 1,
            'showchoicecounts'    => 1,
            'previewhoverhints'   => 1,
            'flagkeywords'        => "danger\nhelp",
            'partialscoreonquit'  => 1,
        ]);

        $newcourseid = $this->backup_and_restore($course);

        $restored = $DB->get_record('aiescape', ['course' => $newcourseid], '*', MUST_EXIST);

        $fields = [
            'name', 'gamestyle', 'personaname', 'gamemode',
            'choicesgood', 'choicesneutral', 'choicesbad',
            'showpremise', 'showgoal', 'showchoicecounts', 'previewhoverhints',
            'flagkeywords', 'partialscoreonquit',
        ];
        foreach ($fields as $field) {
            $this->assertEquals(
                $original->$field,
                $restored->$field,
                "Field '$field' did not round-trip through backup/restore."
            );
        }
    }

    /**
     * Buttons (including usagelimit) and attempts (including ispreview) round-trip correctly.
     */
    public function test_backup_restore_roundtrips_buttons_and_attempts(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['startdate' => $this->startdate]);
        $aiescape = $this->getDataGenerator()->create_module('aiescape', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $button = new \stdClass();
        $button->aiescape   = $aiescape->id;
        $button->label      = 'Hint';
        $button->prompt     = 'Give a hint.';
        $button->sortorder  = 0;
        $button->usagelimit = 3;
        $DB->insert_record('aiescape_buttons', $button);

        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt([
            'aiescape'   => $aiescape->id,
            'userid'     => $user->id,
            'status'     => 'inprogress',
            'stepstally' => 2,
            'ispreview'  => 1,
        ]);

        $newcourseid = $this->backup_and_restore($course);

        $newaiescape = $DB->get_record('aiescape', ['course' => $newcourseid], '*', MUST_EXIST);

        $restoredbutton = $DB->get_record('aiescape_buttons', ['aiescape' => $newaiescape->id], '*', MUST_EXIST);
        $this->assertEquals(3, $restoredbutton->usagelimit);

        $restoredattempt = $DB->get_record('aiescape_attempts', ['aiescape' => $newaiescape->id], '*', MUST_EXIST);
        $this->assertEquals(1, $restoredattempt->ispreview);
        $this->assertEquals($attempt->stepstally, $restoredattempt->stepstally);
    }

    /**
     * Encoded view.php self-links in the intro and premise are decoded again on
     * restore (pointing at the restored course module, not left as encoded tokens).
     */
    public function test_backup_restore_decodes_content_links(): void {
        global $CFG, $DB;

        $course = $this->getDataGenerator()->create_course(['startdate' => $this->startdate]);
        $aiescape = $this->getDataGenerator()->create_module('aiescape', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aiescape', $aiescape->id, 0, false, MUST_EXIST);

        $url = $CFG->wwwroot . '/mod/aiescape/view.php?id=' . $cm->id;
        $DB->update_record('aiescape', (object) [
            'id'      => $aiescape->id,
            'intro'   => 'Replay <a href="' . $url . '">the room</a> first.',
            'premise' => 'You escaped ' . $url . ' once before.',
        ]);

        $newcourseid = $this->backup_and_restore($course);

        $newaiescape = $DB->get_record('aiescape', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('aiescape', $newaiescape->id, 0, false, MUST_EXIST);

        foreach (['intro', 'premise'] as $field) {
            $this->assertStringNotContainsString(
                'AIESCAPEVIEWBYID',
                $newaiescape->$field,
                "Encoded link token left undecoded in '$field' after restore."
            );
            $this->assertStringContainsString(
                '/mod/aiescape/view.php?id=' . $newcm->id,
                $newaiescape->$field,
                "Restored '$field' should link to the restored course module."
            );
        }
    }

    /**
     * Flagged messages (the aiescape_flags table) round-trip and remain linked to the
     * correct restored message and attempt.
     */
    public function test_backup_restore_roundtrips_flags(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['startdate' => $this->startdate]);
        $aiescape = $this->getDataGenerator()->create_module('aiescape', [
            'course' => $course->id,
            'flagkeywords' => 'danger',
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescape->id, 'userid' => $user->id]);
        $message = $generator->create_message([
            'attemptid' => $attempt->id,
            'role'      => 'user',
            'message'   => 'This sounds dangerous',
        ]);
        $generator->create_flag([
            'attemptid' => $attempt->id,
            'messageid' => $message->id,
            'keyword'   => 'danger',
        ]);

        $this->assertCount(1, $DB->get_records('aiescape_flags'));

        $newcourseid = $this->backup_and_restore($course);

        $newaiescape = $DB->get_record('aiescape', ['course' => $newcourseid], '*', MUST_EXIST);
        $newattempt = $DB->get_record('aiescape_attempts', ['aiescape' => $newaiescape->id], '*', MUST_EXIST);
        $newmessage = $DB->get_record('aiescape_messages', ['attemptid' => $newattempt->id], '*', MUST_EXIST);

        $newflags = $DB->get_records('aiescape_flags', ['attemptid' => $newattempt->id]);
        $this->assertCount(1, $newflags);
        $newflag = reset($newflags);
        $this->assertEquals('danger', $newflag->keyword);
        $this->assertEquals($newmessage->id, $newflag->messageid);
    }
}
