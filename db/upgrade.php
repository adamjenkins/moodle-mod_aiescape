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

        $field = new xmldb_field('personaname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'gamestyle');
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

    if ($oldversion < 2026061005) {
        $table = new xmldb_table('aiescape');

        $field = new xmldb_field('showpremisegoal', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'partialscoreonquit');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('showchoicecounts', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'showpremisegoal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061005, 'aiescape');
    }

    if ($oldversion < 2026061006) {
        $table = new xmldb_table('aiescape');

        $field = new xmldb_field('buttonusagelimit', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '-1', 'showchoicecounts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061006, 'aiescape');
    }

    if ($oldversion < 2026061007) {
        $table = new xmldb_table('aiescape');

        $field = new xmldb_field('flagkeywords', XMLDB_TYPE_TEXT, null, null, null, null, null, 'buttonusagelimit');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('aiescape_flags');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('messageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('keyword', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('attemptid', XMLDB_KEY_FOREIGN, ['attemptid'], 'aiescape_attempts', ['id']);
            $table->add_key('messageid', XMLDB_KEY_FOREIGN, ['messageid'], 'aiescape_messages', ['id']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026061007, 'aiescape');
    }

    if ($oldversion < 2026061008) {
        $table = new xmldb_table('aiescape');

        // Replace the combined showpremisegoal toggle with two independent checkboxes.
        $field = new xmldb_field('showpremisegoal', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'partialscoreonquit');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'showpremise');
        }

        $field = new xmldb_field('showpremise', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'goal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('showgoal', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'showpremise');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061008, 'aiescape');
    }

    if ($oldversion < 2026061009) {
        // Replace the single activity-wide usage limit with a per-button limit.
        $table = new xmldb_table('aiescape');
        $field = new xmldb_field('buttonusagelimit', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '-1', 'showchoicecounts');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $table = new xmldb_table('aiescape_buttons');
        $field = new xmldb_field('usagelimit', XMLDB_TYPE_INTEGER, '6', null, null, null, null, 'defaultindex');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061009, 'aiescape');
    }

    if ($oldversion < 2026061010) {
        // Teachers/managers can now preview the activity; mark their attempts so they
        // are excluded from attempt limits, grading, completion, and reports.
        $table = new xmldb_table('aiescape_attempts');
        $field = new xmldb_field('ispreview', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timecompleted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // New setting: reveal choice type (good/neutral/bad) on hover while previewing.
        $table = new xmldb_table('aiescape');
        $field = new xmldb_field('previewhoverhints', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'showchoicecounts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061010, 'aiescape');
    }

    return true;
}
