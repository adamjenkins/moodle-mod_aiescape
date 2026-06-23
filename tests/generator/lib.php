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

/**
 * mod_aiescape data generator.
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * mod_aiescape data generator class.
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aiescape_generator extends testing_module_generator {
    /**
     * Creates a new aiescape instance with sensible defaults.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) $record;

        $defaults = [
            'premise'             => 'You wake up in a small room.',
            'goal'                => 'Find the exit.',
            'gamestyle'           => 'narrative',
            'personaname'         => '',
            'gamemode'            => 'multichoice',
            'choicesgood'         => 1,
            'choicesneutral'      => 1,
            'choicesbad'          => 1,
            'steps'               => 5,
            'grade'               => 100,
            'maxattempts'         => -1,
            'showprogress'        => 1,
            'allowstudentreview'  => 0,
            'partialscoreonquit'  => 0,
            'showpremise'         => 0,
            'showgoal'            => 0,
            'showchoicecounts'    => 0,
            'previewhoverhints'   => 0,
            'flagkeywords'        => '',
        ];

        $record = array_merge($defaults, $record);

        return parent::create_instance((object) $record, (array) $options);
    }

    /**
     * Creates an attempt record directly in the database.
     *
     * @param array $record Must include 'aiescape' and 'userid'; may include 'status', 'stepstally', 'ispreview'.
     * @return stdClass The created attempt record
     */
    public function create_attempt(array $record): stdClass {
        global $DB;

        $now = time();
        $defaults = [
            'status'       => 'inprogress',
            'stepstally'   => 0,
            'ispreview'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $data = (object) array_merge($defaults, $record);
        $data->id = $DB->insert_record('aiescape_attempts', $data);

        $attempt = $DB->get_record('aiescape_attempts', ['id' => $data->id], '*', MUST_EXIST);
        $attempt->id = (int) $attempt->id;
        $attempt->aiescape = (int) $attempt->aiescape;
        $attempt->userid = (int) $attempt->userid;
        $attempt->stepstally = (int) $attempt->stepstally;

        return $attempt;
    }

    /**
     * Creates a message record directly in the database.
     *
     * @param array $record Must include 'attemptid', 'role', 'message'; may include 'choicetype', 'stepchange'.
     * @return stdClass The created message record
     */
    public function create_message(array $record): stdClass {
        global $DB;

        $defaults = [
            'choicetype'  => null,
            'stepchange'  => null,
            'timecreated' => time(),
        ];
        $data = (object) array_merge($defaults, $record);
        $data->id = $DB->insert_record('aiescape_messages', $data);

        $message = $DB->get_record('aiescape_messages', ['id' => $data->id], '*', MUST_EXIST);
        $message->id = (int) $message->id;
        $message->attemptid = (int) $message->attemptid;

        return $message;
    }

    /**
     * Creates a flag record directly in the database.
     *
     * @param array $record Must include 'attemptid', 'messageid', 'keyword'.
     * @return stdClass The created flag record
     */
    public function create_flag(array $record): stdClass {
        global $DB;

        $defaults = [
            'timecreated' => time(),
        ];
        $data = (object) array_merge($defaults, $record);
        $data->id = $DB->insert_record('aiescape_flags', $data);

        $flag = $DB->get_record('aiescape_flags', ['id' => $data->id], '*', MUST_EXIST);
        $flag->id = (int) $flag->id;
        $flag->attemptid = (int) $flag->attemptid;
        $flag->messageid = (int) $flag->messageid;

        return $flag;
    }
}
