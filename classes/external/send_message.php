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
            'cmid'        => new external_value(PARAM_INT, 'Course module ID'),
            'attemptid'   => new external_value(PARAM_INT, 'Attempt ID'),
            'message'     => new external_value(PARAM_TEXT, 'Student message text (freetext/combo modes)', VALUE_DEFAULT, ''),
            'choicelabel' => new external_value(PARAM_TEXT, 'The label of the selected choice', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Process a student message/choice and return the AI response.
     *
     * @param int    $cmid
     * @param int    $attemptid
     * @param string $message
     * @param string $choicelabel
     * @return array
     */
    public static function execute(
        int $cmid,
        int $attemptid,
        string $message,
        string $choicelabel
    ): array {
        global $DB, $USER;

        [
            'cmid'        => $cmid,
            'attemptid'   => $attemptid,
            'message'     => $message,
            'choicelabel' => $choicelabel,
        ] = self::validate_parameters(
            self::execute_parameters(),
            compact('cmid', 'attemptid', 'message', 'choicelabel')
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

        // A closed activity accepts no more messages; finalise the attempt instead.
        if (empty($attempt->ispreview) && attempt_manager::is_closed($aiescape)) {
            $atman->abandon_expired_attempt($attempt, $aiescape);
            throw new \moodle_exception('error:closedon', 'mod_aiescape', '', userdate($aiescape->timeclose));
        }

        // Choice submissions carry only the label. The server resolves the choice's
        // good/neutral/bad type from the offered set it stored last turn, so the
        // classification never exists client-side and cannot be read or forged.
        $usermessagetext = $message;
        $presetchange    = null;
        $matchedtype     = null;
        $isfreeturn      = false;

        if ($choicelabel !== '') {
            // Reject choice submissions in freetext mode (no choice buttons there).
            if ($aiescape->gamemode === 'freetext') {
                throw new \moodle_exception('error:invalidchoice', 'mod_aiescape');
            }

            $matched = null;
            foreach ($atman->get_offered_choices($attempt->id) as $c) {
                if ($c['label'] === $choicelabel) {
                    $matched = $c;
                    break;
                }
            }
            if ($matched === null) {
                throw new \moodle_exception('error:invalidchoice', 'mod_aiescape');
            }

            $matchedtype = $matched['type'];
            $isfreeturn  = ($matchedtype === attempt_manager::FREETURN_TYPE);

            if ($isfreeturn) {
                // The free-turn fallback always sends a fixed, server-controlled message;
                // never trust the client for what gets sent to the AI here.
                $usermessagetext = get_string('freeturnmessage', 'mod_aiescape');
            } else {
                $usermessagetext = $choicelabel;
                $presetchange    = match ($matchedtype) {
                    'good'    => 1,
                    'neutral' => 0,
                    'bad'     => -1,
                    default   => 0,
                };
            }
        } else if ($aiescape->gamemode === 'multichoice' && trim($message) !== '') {
            // Multichoice mode accepts no free-typed text: allowing it would let a
            // direct web-service call buy an AI-evaluated (injectable) scoring turn.
            // Only the empty opening/refresh request is a valid non-choice turn.
            throw new \moodle_exception('error:invalidchoice', 'mod_aiescape');
        }

        // A submission is a genuine student turn only when it carries a choice or
        // free text; an empty message is the opening/refresh request that just asks
        // the AI to (re)generate the current turn's narrative and choices.
        $hasuserturn = ($choicelabel !== '' || trim($message) !== '');

        // Build the AI prompt from the stored history plus the pending user turn,
        // WITHOUT persisting anything yet. If the AI call fails, nothing is written,
        // so the attempt is never left with an orphan user message that would strand
        // it (a multichoice attempt with a trailing user message has no way to act).
        $messages = $atman->get_attempt_messages($attemptid);
        if ($hasuserturn) {
            $messages[] = (object) [
                'role'    => 'user',
                'message' => $usermessagetext,
            ];
        }

        $result = $atman->run_ai_turn($aiescape, $context, $USER->id, $messages, (int) $attempt->stepstally);

        // The AI turn succeeded: now it is safe to persist the student's message.
        if ($hasuserturn) {
            $messageid = $atman->record_message($attemptid, 'user', $usermessagetext, $matchedtype, $presetchange);

            // Flag the message for teacher review if it matches a configured keyword.
            // Only applies to free-typed responses (not fixed multichoice button labels).
            if ($choicelabel === '' && $aiescape->gamemode !== 'multichoice' && !empty($aiescape->flagkeywords)) {
                $atman->flag_message_if_matched($messageid, $attemptid, $usermessagetext, $aiescape->flagkeywords);
            }
        }

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

        // Only reveal choice types when the preview hover-hints feature applies;
        // students must never receive the classification.
        $revealtypes = !empty($aiescape->previewhoverhints)
            && has_capability('mod/aiescape:viewreports', $context);

        return [
            'narrative'  => $result['narrative'],
            'choices'    => attempt_manager::export_choices($result['choices'], $revealtypes),
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
                    'label'      => new external_value(PARAM_TEXT, 'Choice label'),
                    'isfreeturn' => new external_value(PARAM_BOOL, 'Whether this is the fallback free turn'),
                    'type'       => new external_value(
                        PARAM_ALPHA,
                        'good/neutral/bad; only included for the preview hover-hints feature',
                        VALUE_OPTIONAL
                    ),
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
