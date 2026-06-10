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
     * Returns the current in-progress attempt for a user, or null if none exists.
     *
     * @param int $aiescape Activity instance id
     * @param int $userid
     * @return stdClass|null
     */
    public function get_active_attempt(int $aiescape, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record(
            'aiescape_attempts',
            ['aiescape' => $aiescape, 'userid' => $userid, 'status' => 'inprogress']
        ) ?: null;
    }

    /**
     * Checks whether the user is allowed to start a new attempt.
     *
     * @param stdClass $aiescape The activity record
     * @param int      $userid
     * @return bool
     */
    public function can_start_new_attempt(\stdClass $aiescape, int $userid): bool {
        global $DB;

        if ((int) $aiescape->maxattempts === -1) {
            return true;
        }

        $count = $DB->count_records('aiescape_attempts', ['aiescape' => $aiescape->id, 'userid' => $userid]);
        return $count < (int) $aiescape->maxattempts;
    }

    /**
     * Returns an active attempt for the user, or creates a new one.
     *
     * Throws moodle_exception if the user has exhausted their attempts.
     *
     * @param stdClass $aiescape The activity record
     * @param int      $userid
     * @return stdClass The attempt record
     */
    public function get_or_create_attempt(\stdClass $aiescape, int $userid): \stdClass {
        global $DB;

        $attempt = $this->get_active_attempt($aiescape->id, $userid);
        if ($attempt) {
            return $attempt;
        }

        if (!$this->can_start_new_attempt($aiescape, $userid)) {
            throw new \moodle_exception('error:maxattemptsreached', 'mod_aiescape');
        }

        $now = time();
        $record               = new \stdClass();
        $record->aiescape     = $aiescape->id;
        $record->userid       = $userid;
        $record->status       = 'inprogress';
        $record->stepstally   = 0;
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
     * @return stdClass[]
     */
    public function get_user_attempts(int $aiescape, int $userid): array {
        global $DB;
        return array_values(
            $DB->get_records('aiescape_attempts', ['aiescape' => $aiescape, 'userid' => $userid], 'timecreated DESC')
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
        if (!empty($aiescape->partialscoreonquit) && (int) $aiescape->steps > 0) {
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

        // Update the gradebook.
        aiescape_update_grades($aiescape, $attempt->userid);

        // Update Moodle activity completion.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $attempt->userid);
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
