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
                'Choice type: good, neutral, bad (multichoice/combo), or freeturn (fallback turn)',
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

        if ($attempt->status !== 'inprogress') {
            throw new \moodle_exception('error:invalidattempt', 'mod_aiescape');
        }

        $atman = new attempt_manager();

        // Reject preset-choice submissions in freetext mode (no choice buttons there).
        if ($aiescape->gamemode === 'freetext' && $choicetype !== '') {
            throw new \moodle_exception('error:invalidchoice', 'mod_aiescape');
        }

        // Validate the submitted choice against the server-stored offered choices so
        // that a student cannot forge a choicetype the AI never offered this turn.
        if ($choicetype !== '') {
            $offeredchoices = $atman->get_offered_choices($attempt->id);
            $matched = false;
            foreach ($offeredchoices as $c) {
                if ($c['type'] === $choicetype && $c['label'] === $choicelabel) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                throw new \moodle_exception('error:invalidchoice', 'mod_aiescape');
            }
        }

        // Determine the user-facing message text and step delta from choice.
        $isfreeturn      = ($choicetype === attempt_manager::FREETURN_TYPE);
        $usermessagetext = $message;
        $presetchange    = null;

        if ($choicetype !== '' && in_array($choicetype, ['good', 'neutral', 'bad'], true)) {
            $usermessagetext = $choicelabel ?: $choicetype;
            $presetchange    = match ($choicetype) {
                'good'    => 1,
                'neutral' => 0,
                'bad'     => -1,
            };
        } else if ($isfreeturn) {
            // The free-turn fallback always sends a fixed, server-controlled message;
            // never trust the client for what gets sent to the AI here.
            $usermessagetext = get_string('freeturnmessage', 'mod_aiescape');
        }

        // Record the student's message.
        $messageid = $atman->record_message($attemptid, 'user', $usermessagetext, $choicetype ?: null, $presetchange);

        // Flag the message for teacher review if it matches a configured keyword.
        // Only applies to free-typed responses (not fixed multichoice button labels).
        if ($choicetype === '' && $aiescape->gamemode !== 'multichoice' && !empty($aiescape->flagkeywords)) {
            $atman->flag_message_if_matched($messageid, $attemptid, $usermessagetext, $aiescape->flagkeywords);
        }

        // Build and send the AI prompt.
        $messages = $atman->get_attempt_messages($attemptid);
        $result   = $atman->run_ai_turn($aiescape, $context, $USER->id, $messages, (int) $attempt->stepstally);

        // Apply step change (prefer AI-evaluated for freetext; preset for choices).
        $stepchange = ($presetchange !== null) ? $presetchange : $result['stepchange'];
        if ($isfreeturn) {
            // The free turn is a fallback offered through no fault of the student's own;
            // it must never cost them progress, even if the AI evaluates it negatively.
            $stepchange = max(0, $stepchange);
        }
        $atman->update_tally($attempt, $stepchange);

        // Record the AI response.
        $atman->record_message($attemptid, 'assistant', $result['narrative'], null, $stepchange);

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

        // Persist the choices the AI offered this turn so the next send_message call can
        // validate the client's submission against what the server actually offered.
        // Clear on completion — the attempt is closed, so no next turn exists.
        $atman->store_offered_choices($attemptid, $completed ? [] : $result['choices']);

        return [
            'narrative'  => $result['narrative'],
            'choices'    => $result['choices'],
            'completed'  => $completed,
            'canrestart' => $atman->can_start_new_attempt($aiescape, $USER->id, (bool) $attempt->ispreview),
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
                    'type'  => new external_value(PARAM_ALPHA, 'good, neutral, bad, or freeturn'),
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
