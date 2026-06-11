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
 * Upgrade steps for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs upgrade steps between versions.
 *
 * @param int $oldversion Plugin version being upgraded from
 * @return bool
 */
function xmldb_aiescape_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061002) {
        $table = new xmldb_table('aiescape');
        $field = new xmldb_field(
            'partialscoreonquit',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'allowstudentreview'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('aiescape_buttons');
        $field = new xmldb_field('defaultindex', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061002, 'aiescape');
    }

    if ($oldversion < 2026061003) {
        $table = new xmldb_table('aiescape');

        $field = new xmldb_field('gamestyle', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'narrative', 'allowstudentreview');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('personaname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'gamestyle');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061003, 'aiescape');
    }

    if ($oldversion < 2026061004) {
        $table = new xmldb_table('aiescape');

        $field = new xmldb_field('choicesgood', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'gamemode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('choicesneutral', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'choicesgood');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('choicesbad', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'choicesneutral');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061004, 'aiescape');
    }

    return true;
}
