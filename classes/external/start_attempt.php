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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_aiescape\attempt_manager;

/**
 * Web service: start or resume an attempt.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_attempt extends external_api {
    /**
     * Defines the parameters for this web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Start or resume an attempt.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'aiescape');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/aiescape:play', $context);
        $ispreview = has_capability('mod/aiescape:viewreports', $context);

        $aiescape = $DB->get_record('aiescape', ['id' => $cm->instance], '*', MUST_EXIST);
        $manager  = new attempt_manager();

        // Enforce the open/close window for students; previewing users may play any time.
        if (!$ispreview) {
            if (!empty($aiescape->timeopen) && time() < $aiescape->timeopen) {
                throw new \moodle_exception('error:notopenyet', 'mod_aiescape', '', userdate($aiescape->timeopen));
            }
            if (attempt_manager::is_closed($aiescape)) {
                if ($active = $manager->get_active_attempt($aiescape->id, $USER->id)) {
                    $manager->abandon_expired_attempt($active, $aiescape);
                }
                throw new \moodle_exception('error:closedon', 'mod_aiescape', '', userdate($aiescape->timeclose));
            }
        }

        $attempt  = $manager->get_or_create_attempt($aiescape, $USER->id, $ispreview);
        $messages = $manager->get_attempt_messages($attempt->id);
        $buttons  = $DB->get_records('aiescape_buttons', ['aiescape' => $aiescape->id], 'sortorder ASC');

        $messagelist = array_map(fn($m) => [
            'role'    => $m->role,
            'message' => $m->message,
        ], $messages);

        $buttonlist = array_map(fn($b) => [
            'id'        => (int) $b->id,
            'label'     => $b->label,
            'remaining' => attempt_manager::usage_remaining($b, $messages),
        ], array_values($buttons));

        return [
            'attemptid'   => (int) $attempt->id,
            'tally'       => (int) $attempt->stepstally,
            'steps'       => (int) $aiescape->steps,
            'gamemode'    => $aiescape->gamemode,
            'showprogress' => (bool) $aiescape->showprogress,
            'messages'    => $messagelist,
            'buttons'     => $buttonlist,
            'completed'   => $attempt->status === 'completed',
            'canrestart'  => $manager->can_start_new_attempt($aiescape, $USER->id, $ispreview),
        ];
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid'    => new external_value(PARAM_INT, 'Attempt ID'),
            'tally'        => new external_value(PARAM_INT, 'Current step tally'),
            'steps'        => new external_value(PARAM_INT, 'Steps needed to complete'),
            'gamemode'     => new external_value(PARAM_ALPHA, 'Game mode'),
            'showprogress' => new external_value(PARAM_BOOL, 'Whether to show the progress bar'),
            'messages'     => new external_multiple_structure(
                new external_single_structure([
                    'role'    => new external_value(PARAM_ALPHA, 'user or assistant'),
                    'message' => new external_value(PARAM_RAW, 'Message text'),
                ])
            ),
            'buttons' => new external_multiple_structure(
                new external_single_structure([
                    'id'        => new external_value(PARAM_INT, 'Button ID'),
                    'label'     => new external_value(PARAM_TEXT, 'Button label'),
                    'remaining' => new external_value(PARAM_INT, 'Uses left this attempt; -1 means unlimited'),
                ])
            ),
            'completed'  => new external_value(PARAM_BOOL, 'Whether this attempt is already completed'),
            'canrestart' => new external_value(PARAM_BOOL, 'Whether the user can start another attempt'),
        ]);
    }
}
