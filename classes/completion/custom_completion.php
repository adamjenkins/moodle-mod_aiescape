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

namespace mod_aiescape\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetches the completion state for the given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $aiescape = $DB->get_record('aiescape', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $status = $DB->record_exists('aiescape_attempts', [
            'aiescape'  => $aiescape->id,
            'userid'    => $this->userid,
            'status'    => 'completed',
            'ispreview' => 0,
        ]);

        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Returns the list of completion rules defined by this activity.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completioncompleted'];
    }

    /**
     * Returns user-facing descriptions for each defined completion rule.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completioncompleted' => get_string('completiondetail:completed', 'mod_aiescape'),
        ];
    }

    /**
     * Returns the display order for completion rules.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionusegrade',
            'completioncompleted',
        ];
    }
}
