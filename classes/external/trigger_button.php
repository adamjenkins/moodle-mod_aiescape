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
use mod_aiescape\ai\prompt_builder;
use mod_aiescape\ai\response_parser;

/**
 * Web service: fire a secondary action button.
 *
 * Sends the button's prompt to the AI without recording the exchange in
 * the conversation history or modifying the step tally.
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

        // Find the last AI message for context.
        $atman = new attempt_manager();
        $messages = $atman->get_attempt_messages($attempt->id);
        $lastai = '';
        foreach (array_reverse($messages) as $msg) {
            if ($msg->role === 'assistant') {
                $lastai = $msg->message;
                break;
            }
        }

        $builder = new prompt_builder();
        $prompt  = $builder->build_button_prompt($button->prompt, $lastai, $aiescape);

        $aiaction  = new \core_ai\aiactions\generate_text(
            contextid: $context->id,
            userid: $USER->id,
            prompttext: $prompt
        );
        $aimanager = \core\di::get(\core_ai\manager::class);
        $airesponse = $aimanager->process_action($aiaction);

        if (!$airesponse->get_success()) {
            throw new \moodle_exception('error:aifailed', 'mod_aiescape');
        }

        $rawtext = $airesponse->get_response_data()['generatedcontent'] ?? '';
        $parser  = new response_parser();
        $narrative = $parser->parse_button_response($rawtext);

        return ['narrative' => $narrative];
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'narrative' => new external_value(PARAM_RAW, 'AI response to the button prompt'),
        ]);
    }
}
