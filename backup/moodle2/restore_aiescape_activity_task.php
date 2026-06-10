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
 * Restore activity task for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aiescape/backup/moodle2/restore_aiescape_stepslib.php');

/**
 * Restore task for an aiescape activity instance.
 */
class restore_aiescape_activity_task extends restore_activity_task {
    /**
     * Defines restore settings specific to this activity.
     */
    protected function define_my_settings() {
        // No custom settings.
    }

    /**
     * Defines the restore steps for this activity.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_aiescape_activity_structure_step('aiescape_structure', 'aiescape.xml'));
    }

    /**
     * Decodes URLs after restore.
     *
     * @param string $content
     * @return string
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Defines decoding rules for restored links.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('AIESCAPEVIEWBYID', '/mod/aiescape/view.php?id=$1', 'course_module'),
        ];
    }
}
