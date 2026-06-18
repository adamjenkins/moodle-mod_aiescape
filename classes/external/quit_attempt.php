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

namespace mod_aiescape\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_aiescape\attempt_manager;

/**
 * Web service: abandon an in-progress attempt.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quit_attempt extends external_api {
    /**
     * Defines the parameters for this web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'      => new external_value(PARAM_INT, 'Course module ID'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt ID'),
        ]);
    }

    /**
     * Abandon an in-progress attempt.
     *
     * @param int $cmid
     * @param int $attemptid
     * @return array
     */
    public static function execute(int $cmid, int $attemptid): array {
        global $DB, $USER;

        ['cmid' => $cmid, 'attemptid' => $attemptid] =
            self::validate_parameters(self::execute_parameters(), compact('cmid', 'attemptid'));

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'aiescape');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/aiescape:play', $context);

        $aiescape = $DB->get_record('aiescape', ['id' => $cm->instance], '*', MUST_EXIST);
        $attempt  = $DB->get_record(
            'aiescape_attempts',
            ['id' => $attemptid, 'userid' => $USER->id, 'aiescape' => $aiescape->id],
            '*',
            MUST_EXIST
        );

        if ($attempt->status !== 'inprogress') {
            throw new \moodle_exception('error:invalidattempt', 'mod_aiescape');
        }

        $course = get_course($cm->course);
        $cminfo = get_fast_modinfo($course)->get_cm($cm->id);

        $manager = new attempt_manager();
        $grade   = $manager->abandon_attempt($attempt, $aiescape, $course, $cminfo);

        return [
            'abandoned' => true,
            'grade'     => $grade,
            'canrestart' => $manager->can_start_new_attempt($aiescape, $USER->id, (bool) $attempt->ispreview),
        ];
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'abandoned'  => new external_value(PARAM_BOOL, 'Whether the attempt was abandoned'),
            'grade'      => new external_value(PARAM_FLOAT, 'Partial grade awarded (0 if none)'),
            'canrestart' => new external_value(PARAM_BOOL, 'Whether the user can start a new attempt'),
        ]);
    }
}
