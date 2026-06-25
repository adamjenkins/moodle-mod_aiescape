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
 * Web service: fire a secondary action button.
 *
 * Persists the button's prompt as part of the conversation history (so it
 * affects this and all subsequent AI turns) and returns the AI's single
 * response. Does not modify the step tally.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_button extends external_api {
    /**
     * Defines the parameters for this web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'      => new external_value(PARAM_INT, 'Course module ID'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt ID'),
            'buttonid'  => new external_value(PARAM_INT, 'Button ID'),
        ]);
    }

    /**
     * Trigger a button and return the AI narrative.
     *
     * @param int $cmid
     * @param int $attemptid
     * @param int $buttonid
     * @return array
     */
    public static function execute(int $cmid, int $attemptid, int $buttonid): array {
        global $DB, $USER;

        ['cmid' => $cmid, 'attemptid' => $attemptid, 'buttonid' => $buttonid]
            = self::validate_parameters(self::execute_parameters(), compact('cmid', 'attemptid', 'buttonid'));

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
        $button   = $DB->get_record('aiescape_buttons', ['id' => $buttonid, 'aiescape' => $aiescape->id], '*', MUST_EXIST);

        if ($attempt->status !== 'inprogress') {
            throw new \moodle_exception('error:invalidattempt', 'mod_aiescape');
        }

        $atman    = new attempt_manager();
        $messages = $atman->get_attempt_messages($attempt->id);

        // Defense in depth: the client disables exhausted buttons proactively, but
        // enforce the limit here too in case of a stale UI state or direct API use.
        if (attempt_manager::usage_remaining($button, $messages) === 0) {
            throw new \moodle_exception('error:buttonlimitreached', 'mod_aiescape');
        }

        // Persist the button's instruction into the conversation history so it
        // affects this turn and every subsequent turn for the rest of the attempt.
        $atman->record_message($attemptid, 'user', $button->prompt, $button->label, null);
        $messages = $atman->get_attempt_messages($attempt->id);

        $result = $atman->run_ai_turn($aiescape, $context, $USER->id, $messages, (int) $attempt->stepstally);

        // Record the AI response without applying any step change.
        $atman->record_message($attemptid, 'assistant', $result['narrative'], null, null);

        $choices = in_array($aiescape->gamemode, ['multichoice', 'combo'], true) ? $result['choices'] : [];

        return [
            'narrative' => $result['narrative'],
            'choices'   => $choices,
            'remaining' => attempt_manager::usage_remaining($button, $messages),
        ];
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'narrative' => new external_value(PARAM_RAW, 'AI response to the button prompt'),
            'choices'   => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Choice label'),
                    'type'  => new external_value(PARAM_ALPHA, 'good, neutral, bad, or freeturn'),
                ])
            ),
            'remaining' => new external_value(PARAM_INT, 'Uses left this attempt; -1 means unlimited'),
        ]);
    }
}
