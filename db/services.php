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
 * Web service definitions for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_aiescape_start_attempt' => [
        'classname'     => 'mod_aiescape\external\start_attempt',
        'methodname'    => 'execute',
        'description'   => 'Start or resume an AI Escape Room attempt.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'mod_aiescape_send_message' => [
        'classname'     => 'mod_aiescape\external\send_message',
        'methodname'    => 'execute',
        'description'   => 'Send a student message or choice and receive the AI response.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'mod_aiescape_trigger_button' => [
        'classname'     => 'mod_aiescape\external\trigger_button',
        'methodname'    => 'execute',
        'description'   => 'Trigger a secondary action button prompt without affecting the step tally.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'mod_aiescape_quit_attempt' => [
        'classname'     => 'mod_aiescape\external\quit_attempt',
        'methodname'    => 'execute',
        'description'   => 'Abandon an in-progress attempt, optionally awarding a partial grade.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
