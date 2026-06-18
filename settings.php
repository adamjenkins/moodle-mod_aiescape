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
 * Admin settings for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // AI provider info.

    $settings->add(new admin_setting_heading(
        'mod_aiescape/aiinfo_heading',
        get_string('aiinfo_heading', 'mod_aiescape'),
        ''
    ));

    $settings->add(new \mod_aiescape\admin\ai_info_setting());

    $settings->add(new admin_setting_configcheckbox(
        'mod_aiescape/showaiproviderinfo',
        get_string('showaiproviderinfo', 'mod_aiescape'),
        get_string('showaiproviderinfo_desc', 'mod_aiescape'),
        0
    ));

    // Default additional buttons.

    $settings->add(new admin_setting_heading(
        'mod_aiescape/defaultbuttons_heading',
        get_string('defaultbuttonssection', 'mod_aiescape'),
        get_string('defaultbuttonssection_desc', 'mod_aiescape')
    ));

    for ($i = 1; $i <= 5; $i++) {
        $settings->add(new admin_setting_configtext(
            'mod_aiescape/defaultbutton' . $i . 'label',
            get_string('defaultbuttonlabel', 'mod_aiescape', $i),
            '',
            '',
            PARAM_TEXT
        ));
        $settings->add(new admin_setting_configtextarea(
            'mod_aiescape/defaultbutton' . $i . 'prompt',
            get_string('defaultbuttonprompt', 'mod_aiescape', $i),
            '',
            ''
        ));
        $settings->add(new admin_setting_configtext(
            'mod_aiescape/defaultbutton' . $i . 'usagelimit',
            get_string('defaultbuttonusagelimit', 'mod_aiescape', $i),
            get_string('defaultbuttonusagelimit_desc', 'mod_aiescape'),
            '',
            PARAM_RAW_TRIMMED
        ));
    }
}
