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

namespace mod_aiescape;

/**
 * Central business logic for managing attempts.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manager {
    /**
     * Choice type used for the single fallback "free turn" button offered when the
     * AI repeatedly fails to return correctly-formatted choices. Selecting it sends
     * a fixed, server-controlled message to the AI rather than a real choice.
     */
    const FREETURN_TYPE = 'freeturn';

    /**
     * Whether the activity is currently within its open/close window.
     *
     * @param stdClass $aiescape The activity record
     * @param int|null $now Timestamp to check against (defaults to now)
     * @return bool
     */
    public static function is_open(\stdClass $aiescape, ?int $now = null): bool {
        $now = $now ?? time();
        if (!empty($aiescape->timeopen) && $now < (int) $aiescape->timeopen) {
            return false;
        }
        return !self::is_closed($aiescape, $now);
    }

    /**
     * Whether the activity's close date has passed.
     *
     * @param stdClass $aiescape The activity record
     * @param int|null $now Timestamp to check against (defaults to now)
     * @return bool
     */
    public static function is_closed(\stdClass $aiescape, ?int $now = null): bool {
        return !empty($aiescape->timeclose) && ($now ?? time()) > (int) $aiescape->timeclose;
    }

    /**
     * Abandons an in-progress attempt whose activity close date has passed.
     *
     * @param stdClass $attempt  The attempt record
     * @param stdClass $aiescape The activity record
     * @return bool Whether the attempt was abandoned
     */
    public function abandon_expired_attempt(\stdClass $attempt, \stdClass $aiescape): bool {
        global $CFG;

        if ($attempt->status !== 'inprogress' || !self::is_closed($aiescape)) {
            return false;
        }
        require_once($CFG->dirroot . '/mod/aiescape/lib.php');
        $course = get_course($aiescape->course);
        $cm = get_fast_modinfo($course)->get_instances_of('aiescape')[$aiescape->id];
        $this->abandon_attempt($attempt, $aiescape, $course, $cm);
        return true;
    }

    /**
     * Returns the current in-progress attempt for a user, or null if none exists.
     *
     * @param int $aiescape Activity instance id
     * @param int $userid
     * @param bool $ispreview Whether to look up a preview attempt rather than a real one
     * @return stdClass|null
     */
    public function get_active_attempt(int $aiescape, int $userid, bool $ispreview = false): ?\stdClass {
        global $DB;
        return $DB->get_record(
            'aiescape_attempts',
            ['aiescape' => $aiescape, 'userid' => $userid, 'status' => 'inprogress', 'ispreview' => $ispreview ? 1 : 0]
        ) ?: null;
    }

    /**
     * Checks whether the user is allowed to start a new attempt.
     *
     * @param stdClass $aiescape The activity record
     * @param int      $userid
     * @param bool     $ispreview Preview attempts are never subject to the attempt limit
     * @return bool
     */
    public function can_start_new_attempt(\stdClass $aiescape, int $userid, bool $ispreview = false): bool {
        global $DB;

        if ($ispreview) {
            return true;
        }

        if ((int) $aiescape->maxattempts === -1) {
            return true;
        }

        $count = $DB->count_records(
            'aiescape_attempts',
            ['aiescape' => $aiescape->id, 'userid' => $userid, 'ispreview' => 0]
        );
        return $count < (int) $aiescape->maxattempts;
    }

    /**
     * Returns an active attempt for the user, or creates a new one.
     *
     * Throws moodle_exception if the user has exhausted their attempts.
     *
     * @param stdClass $aiescape The activity record
     * @param int      $userid
     * @param bool     $ispreview Whether this is a teacher/manager preview attempt
     * @return stdClass The attempt record
     */
    public function get_or_create_attempt(\stdClass $aiescape, int $userid, bool $ispreview = false): \stdClass {
        global $DB;

        $attempt = $this->get_active_attempt($aiescape->id, $userid, $ispreview);
        if ($attempt) {
            return $attempt;
        }

        if (!$this->can_start_new_attempt($aiescape, $userid, $ispreview)) {
            throw new \moodle_exception('error:maxattemptsreached', 'mod_aiescape');
        }

        $now = time();
        $record               = new \stdClass();
        $record->aiescape     = $aiescape->id;
        $record->userid       = $userid;
        $record->status       = 'inprogress';
        $record->stepstally   = 0;
        $record->ispreview    = $ispreview ? 1 : 0;
        $record->timecreated  = $now;
        $record->timemodified = $now;

        $record->id = $DB->insert_record('aiescape_attempts', $record);

        // Fire attempt started event.
        $cm = get_coursemodule_from_instance('aiescape', $aiescape->id);
        $context = \context_module::instance($cm->id);
        $event = \mod_aiescape\event\attempt_started::create([
            'objectid' => $record->id,
            'context'  => $context,
            'userid'   => $userid,
        ]);
        $event->trigger();

        return $record;
    }

    /**
     * Returns all attempts by a user for a given activity, newest first.
     *
     * @param int $aiescape
     * @param int $userid
     * @param bool $includepreview Whether to include teacher/manager preview attempts
     * @return stdClass[]
     */
    public function get_user_attempts(int $aiescape, int $userid, bool $includepreview = false): array {
        global $DB;
        $conditions = ['aiescape' => $aiescape, 'userid' => $userid];
        if (!$includepreview) {
            $conditions['ispreview'] = 0;
        }
        return array_values(
            $DB->get_records('aiescape_attempts', $conditions, 'timecreated DESC')
        );
    }

    /**
     * Returns all messages for an attempt, oldest first.
     *
     * @param int $attemptid
     * @return stdClass[]
     */
    public function get_attempt_messages(int $attemptid): array {
        global $DB;
        return array_values(
            $DB->get_records('aiescape_messages', ['attemptid' => $attemptid], 'timecreated ASC, id ASC')
        );
    }

    /**
     * Records a single message in the conversation history.
     *
     * @param int    $attemptid
     * @param string $role       'user' or 'assistant'
     * @param string $message    Message text
     * @param string|null $choicetype  'good', 'neutral', 'bad', or null
     * @param int|null    $stepchange  +1, 0, -1, or null
     * @return int The new record id
     */
    public function record_message(
        int $attemptid,
        string $role,
        string $message,
        ?string $choicetype = null,
        ?int $stepchange = null
    ): int {
        global $DB;

        $record               = new \stdClass();
        $record->attemptid    = $attemptid;
        $record->role         = $role;
        $record->message      = $message;
        $record->choicetype   = $choicetype;
        $record->stepchange   = $stepchange;
        $record->timecreated  = time();

        return $DB->insert_record('aiescape_messages', $record);
    }

    /**
     * Applies a step delta to the attempt tally (floor 0).
     *
     * @param stdClass $attempt  The attempt record (updated in place)
     * @param int      $stepchange
     * @return void
     */
    public function update_tally(\stdClass $attempt, int $stepchange): void {
        global $DB;

        $newtally = max(0, (int) $attempt->stepstally + $stepchange);
        $attempt->stepstally   = $newtally;
        $attempt->timemodified = time();
        $DB->update_record('aiescape_attempts', $attempt);
    }

    /**
     * Runs a single AI turn: builds the prompt from the conversation history, calls
     * the AI, and parses the response. If multichoice/combo choices don't match
     * what was configured, re-asks the AI for a complete, correctly-formatted turn
     * up to the 'choiceretrylimit' admin setting's number of times.
     *
     * If every retry still fails, the student is offered a single "free turn"
     * choice instead of invented placeholder choices (see FREETURN_TYPE). Selecting
     * it sends a fixed message and, per send_message::execute(), can never reduce
     * the student's tally — so a run of AI misbehaviour never costs them progress.
     *
     * Used by both normal turns (send_message) and additional-button turns
     * (trigger_button) so both go through identical prompt-building and
     * choice-validation logic.
     *
     * @param \stdClass        $aiescape The activity record
     * @param \context_module  $context  The module context (used for the AI action)
     * @param int              $userid   The user triggering this turn
     * @param array            $messages Conversation history, oldest first
     * @param int              $tally    Current step tally
     * @return array {narrative: string, choices: array, stepchange: int}
     */
    public function run_ai_turn(
        \stdClass $aiescape,
        \context_module $context,
        int $userid,
        array $messages,
        int $tally
    ): array {
        $builder = new \mod_aiescape\ai\prompt_builder();
        $parser  = new \mod_aiescape\ai\response_parser();
        $aimanager = \core\di::get(\core_ai\manager::class);

        $choicesgood    = max(1, (int) ($aiescape->choicesgood ?? 1));
        $choicesneutral = max(0, (int) ($aiescape->choicesneutral ?? 1));
        $choicesbad     = max(0, (int) ($aiescape->choicesbad ?? 1));
        $needschoices   = ($aiescape->gamemode === 'multichoice' || $aiescape->gamemode === 'combo');

        $prompt  = $builder->build($aiescape, $messages, $tally);
        $parsed  = null;
        $retrylimit = max(0, (int) get_config('mod_aiescape', 'choiceretrylimit'));

        for ($attempt = 0; $attempt <= $retrylimit; $attempt++) {
            $sendprompt = ($attempt === 0)
                ? $prompt
                : $builder->build_retry_prompt($prompt, $aiescape, $parsed['choices']);

            $aiaction = new \core_ai\aiactions\generate_text(
                contextid: $context->id,
                userid: $userid,
                prompttext: $sendprompt
            );
            $airesponse = $aimanager->process_action($aiaction);

            if (!$airesponse->get_success()) {
                if ($parsed !== null) {
                    // Keep the previous attempt's narrative rather than failing the whole turn.
                    break;
                }
                throw new \moodle_exception('error:aifailed', 'mod_aiescape');
            }

            $rawtext = $airesponse->get_response_data()['generatedcontent'] ?? '';
            $parsed  = $parser->parse($rawtext, $aiescape->gamemode, $choicesgood, $choicesneutral, $choicesbad);

            $valid = !$needschoices
                || $parser->choices_match_expected($parsed['choices'], $choicesgood, $choicesneutral, $choicesbad);
            if ($valid) {
                break;
            }
        }

        if ($needschoices && !$parser->choices_match_expected($parsed['choices'], $choicesgood, $choicesneutral, $choicesbad)) {
            // Exhausted all retries: offer a single fallback turn instead of invented choices.
            $parsed['choices'] = [[
                'label' => get_string('freeturnlabel', 'mod_aiescape'),
                'type'  => self::FREETURN_TYPE,
            ]];
        }

        return [
            'narrative'  => $parsed['narrative'],
            'choices'    => array_map(fn($c) => ['label' => $c['label'], 'type' => $c['type']], $parsed['choices']),
            'stepchange' => $parsed['stepchange'],
        ];
    }

    /**
     * Returns how many more times a button may be used this attempt.
     *
     * @param \stdClass $button   The aiescape_buttons record (must have label, usagelimit)
     * @param array     $messages Conversation history, oldest first
     * @return int -1 if unlimited, otherwise the number of uses remaining (never negative)
     */
    public static function usage_remaining(\stdClass $button, array $messages): int {
        if ($button->usagelimit === null) {
            return -1;
        }

        $used = count(array_filter(
            $messages,
            fn($msg) => $msg->role === 'user' && $msg->choicetype === $button->label
        ));

        return max(0, (int) $button->usagelimit - $used);
    }

    /**
     * Flags a message for teacher review when it matches a configured keyword.
     *
     * @param int    $messageid     The id of the message to potentially flag
     * @param int    $attemptid     The attempt the message belongs to
     * @param string $message       The message text to check
     * @param string $keywordsconfig One keyword/phrase per line (from aiescape->flagkeywords)
     * @return void
     */
    public function flag_message_if_matched(
        int $messageid,
        int $attemptid,
        string $message,
        string $keywordsconfig
    ): void {
        global $DB;

        $keywords = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $keywordsconfig)));
        if (empty($keywords) || $message === '') {
            return;
        }

        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                $record              = new \stdClass();
                $record->attemptid   = $attemptid;
                $record->messageid   = $messageid;
                $record->keyword     = $keyword;
                $record->timecreated = time();
                $DB->insert_record('aiescape_flags', $record);
                return;
            }
        }
    }

    /**
     * Persists the set of choices offered to the student on the last AI turn.
     *
     * Stored as a JSON array of {label, type} objects so that the next
     * send_message call can validate the client's submitted choice against
     * what the server actually offered.
     *
     * @param int   $attemptid
     * @param array $choices   Array of ['label' => string, 'type' => string]
     * @return void
     */
    public function store_offered_choices(int $attemptid, array $choices): void {
        global $DB;
        $DB->set_field('aiescape_attempts', 'lastchoicejson', json_encode($choices), ['id' => $attemptid]);
    }

    /**
     * Returns the choices that were offered to the student on the last AI turn.
     *
     * @param int $attemptid
     * @return array Array of ['label' => string, 'type' => string], empty if none stored
     */
    public function get_offered_choices(int $attemptid): array {
        global $DB;
        $json = $DB->get_field('aiescape_attempts', 'lastchoicejson', ['id' => $attemptid]);
        if (!$json) {
            return [];
        }
        return json_decode($json, true) ?? [];
    }

    /**
     * Marks an attempt as abandoned and optionally awards a partial grade.
     *
     * @param stdClass $attempt  The attempt record (must be in 'inprogress' status)
     * @param stdClass $aiescape The activity record
     * @param stdClass $course   The course record
     * @param cm_info  $cm       The course-module record
     * @return float The grade awarded (0.0 if partial scoring is disabled)
     */
    public function abandon_attempt(
        \stdClass $attempt,
        \stdClass $aiescape,
        \stdClass $course,
        \cm_info $cm
    ): float {
        global $DB;

        $now = time();
        $attempt->status        = 'abandoned';
        $attempt->timemodified  = $now;
        $attempt->timecompleted = $now;
        $DB->update_record('aiescape_attempts', $attempt);

        $grade = 0.0;
        if (
            empty($attempt->ispreview) &&
            !empty($aiescape->partialscoreonquit) &&
            (int) $aiescape->steps > 0
        ) {
            $grade = (float) $aiescape->grade
                * min(1.0, (int) $attempt->stepstally / (int) $aiescape->steps);
            aiescape_update_grades($aiescape, $attempt->userid);
        }

        $context = \context_module::instance($cm->id);
        $event = \mod_aiescape\event\attempt_abandoned::create([
            'objectid' => $attempt->id,
            'context'  => $context,
            'userid'   => $attempt->userid,
        ]);
        $event->trigger();

        return $grade;
    }

    /**
     * Marks an attempt as completed, awards the grade, and triggers Moodle completion.
     *
     * @param stdClass $attempt  The attempt record
     * @param stdClass $aiescape The activity record
     * @param stdClass $course   The course record
     * @param cm_info  $cm       The course-module record
     * @return void
     */
    public function complete_attempt(
        \stdClass $attempt,
        \stdClass $aiescape,
        \stdClass $course,
        \cm_info $cm
    ): void {
        global $DB;

        $now = time();
        $attempt->status        = 'completed';
        $attempt->timemodified  = $now;
        $attempt->timecompleted = $now;
        $DB->update_record('aiescape_attempts', $attempt);

        if (empty($attempt->ispreview)) {
            // Update the gradebook.
            aiescape_update_grades($aiescape, $attempt->userid);

            // Update Moodle activity completion.
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $attempt->userid);
            }
        }

        // Fire completion event.
        $context = \context_module::instance($cm->id);
        $event = \mod_aiescape\event\attempt_completed::create([
            'objectid' => $attempt->id,
            'context'  => $context,
            'userid'   => $attempt->userid,
        ]);
        $event->trigger();
    }
}
