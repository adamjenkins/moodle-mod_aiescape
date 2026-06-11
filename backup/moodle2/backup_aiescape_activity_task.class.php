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
 * Backup activity task for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aiescape/backup/moodle2/backup_aiescape_stepslib.php');

/**
 * Backup task for an aiescape activity instance.
 */
class backup_aiescape_activity_task extends backup_activity_task {
    /**
     * Defines backup settings specific to this activity.
     */
    protected function define_my_settings() {
        // No custom settings.
    }

    /**
     * Defines the backup steps for this activity.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_aiescape_activity_structure_step('aiescape_structure', 'aiescape.xml'));
    }

    /**
     * Encodes URLs for backup.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to module view.
        $pattern = "/$base\/mod\/aiescape\/view\.php\?id=([0-9]+)/";
        $content = preg_replace($pattern, '$@AIESCAPEVIEWBYID*$1@$', $content);

        return $content;
    }
}
