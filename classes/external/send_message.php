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
use mod_aiescape\ai\prompt_builder;
use mod_aiescape\ai\response_parser;

/**
 * Web service: send a student message and receive the AI response.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /**
     * Defines the parameters for this web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'attemptid'  => new external_value(PARAM_INT, 'Attempt ID'),
            'message'    => new external_value(PARAM_TEXT, 'Student message text (freetext/combo modes)', VALUE_DEFAULT, ''),
            'choicetype' => new external_value(
                PARAM_ALPHA,
                'Choice type: good, neutral, bad (multichoice/combo)',
                VALUE_DEFAULT,
                ''
            ),
            'choicelabel' => new external_value(PARAM_TEXT, 'The label of the selected choice', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Process a student message/choice and return the AI response.
     *
     * @param int    $cmid
     * @param int    $attemptid
     * @param string $message
     * @param string $choicetype
     * @param string $choicelabel
     * @return array
     */
    public static function execute(
        int $cmid,
        int $attemptid,
        string $message,
        string $choicetype,
        string $choicelabel
    ): array {
        global $DB, $USER;

        [
            'cmid'        => $cmid,
            'attemptid'   => $attemptid,
            'message'     => $message,
            'choicetype'  => $choicetype,
            'choicelabel' => $choicelabel,
        ] = self::validate_parameters(
            self::execute_parameters(),
            compact('cmid', 'attemptid', 'message', 'choicetype', 'choicelabel')
        );

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

        if ($attempt->status === 'completed') {
            throw new \moodle_exception('error:invalidattempt', 'mod_aiescape');
        }

        $atman = new attempt_manager();

        // Determine the user-facing message text and step delta from choice.
        $usermessagetext = $message;
        $presetchange    = null;

        if ($choicetype !== '' && in_array($choicetype, ['good', 'neutral', 'bad'], true)) {
            $usermessagetext = $choicelabel ?: $choicetype;
            $presetchange    = match ($choicetype) {
                'good'    => 1,
                'neutral' => 0,
                'bad'     => -1,
            };
        }

        // Record the student's message.
        $atman->record_message($attemptid, 'user', $usermessagetext, $choicetype ?: null, $presetchange);

        // Build and send the AI prompt.
        $messages = $atman->get_attempt_messages($attemptid);
        $builder  = new prompt_builder();
        $prompt   = $builder->build($aiescape, $messages, (int) $attempt->stepstally);

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
        $parsed  = $parser->parse($rawtext, $aiescape->gamemode);

        // Apply step change (prefer AI-evaluated for freetext; preset for choices).
        $stepchange = ($presetchange !== null) ? $presetchange : $parsed['stepchange'];
        $atman->update_tally($attempt, $stepchange);

        // Record the AI response.
        $atman->record_message($attemptid, 'assistant', $parsed['narrative'], null, $stepchange);

        // Refresh attempt after tally update.
        $attempt = $DB->get_record('aiescape_attempts', ['id' => $attemptid, 'aiescape' => $aiescape->id]);

        // Complete when tally reaches the required number of steps.
        $completed = false;
        if ((int) $attempt->stepstally >= (int) $aiescape->steps) {
            $course  = get_course($cm->course);
            $cminfo  = get_fast_modinfo($course)->get_cm($cm->id);
            $atman->complete_attempt($attempt, $aiescape, $course, $cminfo);
            $completed = true;
        }

        $choices = array_map(fn($c) => ['label' => $c['label'], 'type' => $c['type']], $parsed['choices']);

        return [
            'narrative'  => $parsed['narrative'],
            'choices'    => $choices,
            'completed'  => $completed,
            'canrestart' => $atman->can_start_new_attempt($aiescape, $USER->id),
            'tally'      => (int) $attempt->stepstally,
            'steps'      => (int) $aiescape->steps,
            'stepchange' => $stepchange,
        ];
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'narrative'  => new external_value(PARAM_RAW, 'AI narrative response'),
            'choices'    => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Choice label'),
                    'type'  => new external_value(PARAM_ALPHA, 'good, neutral, or bad'),
                ])
            ),
            'completed'  => new external_value(PARAM_BOOL, 'Whether the attempt is now completed'),
            'canrestart' => new external_value(PARAM_BOOL, 'Whether the user can start another attempt'),
            'tally'      => new external_value(PARAM_INT, 'Updated step tally'),
            'steps'      => new external_value(PARAM_INT, 'Steps needed to complete'),
            'stepchange' => new external_value(PARAM_INT, 'Step delta applied this turn'),
        ]);
    }
}
