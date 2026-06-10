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
 * Backup step library for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure for one aiescape instance.
 */
class backup_aiescape_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the XML backup structure for one aiescape activity instance.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Main activity table.
        $aiescape = new backup_nested_element('aiescape', ['id'], [
            'name', 'intro', 'introformat',
            'premise', 'premiseformat',
            'goal', 'goalformat',
            'gamemode', 'steps', 'grade',
            'maxattempts', 'showprogress', 'allowstudentreview',
            'timecreated', 'timemodified',
        ]);

        // Configurable buttons (not user data).
        $buttons = new backup_nested_element('buttons');
        $button  = new backup_nested_element('button', ['id'], ['label', 'prompt', 'sortorder']);

        // User attempt data.
        $attempts = new backup_nested_element('attempts');
        $attempt  = new backup_nested_element('attempt', ['id'], [
            'userid', 'status', 'stepstally', 'timecreated', 'timemodified', 'timecompleted',
        ]);

        $messages = new backup_nested_element('messages');
        $message  = new backup_nested_element('message', ['id'], [
            'role', 'message', 'choicetype', 'stepchange', 'timecreated',
        ]);

        // Build the tree.
        $aiescape->add_child($buttons);
        $buttons->add_child($button);

        $aiescape->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($messages);
        $messages->add_child($message);

        // Data sources.
        $aiescape->set_source_table('aiescape', ['id' => backup::VAR_ACTIVITYID]);
        $button->set_source_table('aiescape_buttons', ['aiescape' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $attempt->set_source_table('aiescape_attempts', ['aiescape' => backup::VAR_PARENTID]);
            $message->set_source_table('aiescape_messages', ['attemptid' => backup::VAR_PARENTID]);
            $attempt->annotate_ids('user', 'userid');
        }

        $aiescape->annotate_files('mod_aiescape', 'intro', null);

        return $this->prepare_activity_structure($aiescape);
    }
}
