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
 * Restore step library for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore structure for one aiescape instance.
 */
class restore_aiescape_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the XML path elements to restore for this activity.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('aiescape', '/activity/aiescape');
        $paths[] = new restore_path_element('aiescape_button', '/activity/aiescape/buttons/button');

        if ($userinfo) {
            $paths[] = new restore_path_element('aiescape_attempt', '/activity/aiescape/attempts/attempt');
            $paths[] = new restore_path_element(
                'aiescape_message',
                '/activity/aiescape/attempts/attempt/messages/message'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the main aiescape element.
     *
     * @param array $data
     */
    protected function process_aiescape($data) {
        global $DB;

        $data             = (object) $data;
        $data->course     = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated  = $this->apply_date_offset($data->timecreated);

        $newid = $DB->insert_record('aiescape', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Process a button element.
     *
     * @param array $data
     */
    protected function process_aiescape_button($data) {
        global $DB;

        $data           = (object) $data;
        $data->aiescape = $this->get_new_parentid('aiescape');
        $DB->insert_record('aiescape_buttons', $data);
    }

    /**
     * Process an attempt element.
     *
     * @param array $data
     */
    protected function process_aiescape_attempt($data) {
        global $DB;

        $data             = (object) $data;
        $data->aiescape   = $this->get_new_parentid('aiescape');
        $data->userid     = $this->get_mappingid('user', $data->userid);
        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if ($data->timecompleted) {
            $data->timecompleted = $this->apply_date_offset($data->timecompleted);
        }

        $newid = $DB->insert_record('aiescape_attempts', $data);
        $this->set_mapping('aiescape_attempt', $data->id, $newid);
    }

    /**
     * Process a message element.
     *
     * @param array $data
     */
    protected function process_aiescape_message($data) {
        global $DB;

        $data            = (object) $data;
        $data->attemptid = $this->get_new_parentid('aiescape_attempt');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $DB->insert_record('aiescape_messages', $data);
    }

    /**
     * Performs cleanup tasks after the restore step is executed.
     */
    protected function after_execute() {
        $this->add_related_files('mod_aiescape', 'intro', null);
    }
}
