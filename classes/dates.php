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

declare(strict_types=1);

namespace mod_aiescape;

use core\activity_dates;

/**
 * Fetches the important dates in mod_aiescape for a given module instance and a user.
 *
 * Mirrors mod_quiz so the open/close dates render identically on the course
 * page and the activity page.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {
    /**
     * Returns a list of important dates in mod_aiescape.
     *
     * @return array
     */
    protected function get_dates(): array {
        $timeopen = $this->cm->customdata['timeopen'] ?? null;
        $timeclose = $this->cm->customdata['timeclose'] ?? null;

        $now = time();
        $dates = [];

        if ($timeopen) {
            $openlabelid = $timeopen > $now ? 'activitydate:opens' : 'activitydate:opened';
            $dates[] = [
                'dataid' => 'timeopen',
                'label' => get_string($openlabelid, 'core_course'),
                'timestamp' => (int) $timeopen,
            ];
        }

        if ($timeclose) {
            $closelabelid = $timeclose > $now ? 'activitydate:closes' : 'activitydate:closed';
            $dates[] = [
                'dataid' => 'timeclose',
                'label' => get_string($closelabelid, 'core_course'),
                'timestamp' => (int) $timeclose,
            ];
        }

        return $dates;
    }
}
