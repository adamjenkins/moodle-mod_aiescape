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
 * Unit tests for the mod_aiescape settings form validation.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_aiescape_mod_form::class)]
final class mod_form_test extends advanced_testcase {
    /**
     * Builds the mod_form for an existing instance, as modedit.php would.
     *
     * @return array{0: \mod_aiescape_mod_form, 1: array} The form and its current data as an array
     */
    private function build_form(): array {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/aiescape/mod_form.php');

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        // The moodleform_mod base class resolves sections/cms against the global
        // $COURSE, which form construction re-syncs from $PAGE — so set the
        // course on the page.
        $PAGE->set_course($course);
        $aiescape = $this->getDataGenerator()->create_module('aiescape', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aiescape', $aiescape->id, 0, false, MUST_EXIST);

        [$cm, , , $data, $cw] = get_moduleinfo_data($cm, $course);
        $form = new \mod_aiescape_mod_form($data, $cw->section, $cm, $course);

        $data = (array) $data;
        // A real submission always carries the availability editor's JSON.
        $data['availabilityconditionsjson'] = '{"op":"&","c":[],"showc":[]}';

        return [$form, $data];
    }

    /**
     * Selecting a scale (stored as a negative grade value) is rejected: the
     * grading code only supports point grades.
     */
    public function test_validation_rejects_scale_grades(): void {
        $this->resetAfterTest();
        [$form, $data] = $this->build_form();

        $scale = $this->getDataGenerator()->create_scale();
        $data['grade'] = -$scale->id;

        $errors = $form->validation($data, []);

        $this->assertArrayHasKey('grade', $errors);
    }

    /**
     * A positive point grade passes validation.
     */
    public function test_validation_accepts_point_grades(): void {
        $this->resetAfterTest();
        [$form, $data] = $this->build_form();

        $data['grade'] = 100;

        $errors = $form->validation($data, []);

        $this->assertArrayNotHasKey('grade', $errors);
    }
}
