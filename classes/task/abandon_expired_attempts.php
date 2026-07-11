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

namespace mod_aiescape\task;

use mod_aiescape\attempt_manager;

/**
 * Scheduled task that abandons in-progress attempts whose activity has closed.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class abandon_expired_attempts extends \core\task\scheduled_task {
    /**
     * Returns the task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskabandonexpired', 'mod_aiescape');
    }

    /**
     * Abandons every in-progress attempt belonging to an activity whose close date has passed.
     */
    public function execute(): void {
        global $DB;

        $attempts = $DB->get_recordset_sql(
            "SELECT a.*
               FROM {aiescape_attempts} a
               JOIN {aiescape} e ON e.id = a.aiescape
              WHERE a.status = 'inprogress' AND e.timeclose > 0 AND e.timeclose < :now",
            ['now' => time()]
        );

        $manager = new attempt_manager();
        $activities = [];
        $count = 0;
        foreach ($attempts as $attempt) {
            if (!isset($activities[$attempt->aiescape])) {
                $activities[$attempt->aiescape] = $DB->get_record('aiescape', ['id' => $attempt->aiescape]);
            }
            if ($manager->abandon_expired_attempt($attempt, $activities[$attempt->aiescape])) {
                $count++;
            }
        }
        $attempts->close();

        mtrace("mod_aiescape: abandoned $count expired attempt(s).");
    }
}
