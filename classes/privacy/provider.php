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

namespace mod_aiescape\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata describing the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('aiescape_attempts', [
            'userid'        => 'privacy:metadata:aiescape_attempts:userid',
            'status'        => 'privacy:metadata:aiescape_attempts:status',
            'stepstally'    => 'privacy:metadata:aiescape_attempts:stepstally',
            'timecreated'   => 'privacy:metadata:aiescape_attempts:timecreated',
            'timecompleted' => 'privacy:metadata:aiescape_attempts:timecompleted',
        ], 'privacy:metadata:aiescape_attempts');

        $collection->add_database_table('aiescape_messages', [
            'message'     => 'privacy:metadata:aiescape_messages:message',
            'role'        => 'privacy:metadata:aiescape_messages:role',
            'timecreated' => 'privacy:metadata:aiescape_messages:timecreated',
        ], 'privacy:metadata:aiescape_messages');

        $collection->add_database_table('aiescape_flags', [
            'keyword'     => 'privacy:metadata:aiescape_flags:keyword',
            'timecreated' => 'privacy:metadata:aiescape_flags:timecreated',
        ], 'privacy:metadata:aiescape_flags');

        $collection->add_external_location_link('aiprovider', [
            'message' => 'privacy:metadata:aiescape_messages:message',
        ], 'privacy:metadata:aiescape_externalai');

        return $collection;
    }

    /**
     * Returns the list of contexts that contain personal data for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = 'aiescape'
            INNER JOIN {aiescape} a ON a.id = cm.instance
            INNER JOIN {aiescape_attempts} aa ON aa.aiescape = a.id AND aa.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Gets the list of users within the specified context who have personal data.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT aa.userid
                  FROM {aiescape_attempts} aa
                  JOIN {aiescape} a ON a.id = aa.aiescape
                  JOIN {course_modules} cm ON cm.instance = a.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Exports personal data for the approved contexts belonging to the user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm       = get_coursemodule_from_id('aiescape', $context->instanceid);
            $aiescape = $DB->get_record('aiescape', ['id' => $cm->instance]);
            $attempts = $DB->get_records('aiescape_attempts', ['aiescape' => $aiescape->id, 'userid' => $userid]);

            foreach ($attempts as $attempt) {
                $messages = $DB->get_records('aiescape_messages', ['attemptid' => $attempt->id], 'timecreated ASC');
                $flags    = $DB->get_records('aiescape_flags', ['attemptid' => $attempt->id], 'timecreated ASC');
                $data = (object) [
                    'status'        => $attempt->status,
                    'stepstally'    => $attempt->stepstally,
                    'timecreated'   => \core_privacy\local\request\transform::datetime($attempt->timecreated),
                    'timecompleted' => $attempt->timecompleted
                        ? \core_privacy\local\request\transform::datetime($attempt->timecompleted)
                        : null,
                    'messages' => array_map(fn($m) => (object) [
                        'role'        => $m->role,
                        'message'     => $m->message,
                        'timecreated' => \core_privacy\local\request\transform::datetime($m->timecreated),
                    ], array_values($messages)),
                    'flags' => array_map(fn($f) => (object) [
                        'keyword'     => $f->keyword,
                        'timecreated' => \core_privacy\local\request\transform::datetime($f->timecreated),
                    ], array_values($flags)),
                ];
                writer::with_context($context)->export_data(['attempt_' . $attempt->id], $data);
            }
        }
    }

    /**
     * Deletes all personal data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm       = get_coursemodule_from_id('aiescape', $context->instanceid);
        $aiescape = $DB->get_record('aiescape', ['id' => $cm->instance]);

        if (!$aiescape) {
            return;
        }

        $attemptids = $DB->get_fieldset_select('aiescape_attempts', 'id', 'aiescape = ?', [$aiescape->id]);
        if ($attemptids) {
            [$insql, $params] = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('aiescape_flags', "attemptid $insql", $params);
            $DB->delete_records_select('aiescape_messages', "attemptid $insql", $params);
        }
        $DB->delete_records('aiescape_attempts', ['aiescape' => $aiescape->id]);
    }

    /**
     * Deletes all personal data for the specified user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm       = get_coursemodule_from_id('aiescape', $context->instanceid);
            $aiescape = $DB->get_record('aiescape', ['id' => $cm->instance]);
            if (!$aiescape) {
                continue;
            }

            $attemptids = $DB->get_fieldset_select(
                'aiescape_attempts',
                'id',
                'aiescape = ? AND userid = ?',
                [$aiescape->id, $userid]
            );
            if ($attemptids) {
                [$insql, $params] = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select('aiescape_flags', "attemptid $insql", $params);
                $DB->delete_records_select('aiescape_messages', "attemptid $insql", $params);
            }
            $DB->delete_records('aiescape_attempts', ['aiescape' => $aiescape->id, 'userid' => $userid]);
        }
    }

    /**
     * Deletes personal data for the given users in the specified context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm       = get_coursemodule_from_id('aiescape', $context->instanceid);
        $aiescape = $DB->get_record('aiescape', ['id' => $cm->instance]);
        if (!$aiescape) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids);
        $attemptids = $DB->get_fieldset_select(
            'aiescape_attempts',
            'id',
            "aiescape = ? AND userid $usersql",
            array_merge([$aiescape->id], $userparams)
        );

        if ($attemptids) {
            [$insql, $params] = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('aiescape_flags', "attemptid $insql", $params);
            $DB->delete_records_select('aiescape_messages', "attemptid $insql", $params);
        }
        $DB->delete_records_select(
            'aiescape_attempts',
            "aiescape = ? AND userid $usersql",
            array_merge([$aiescape->id], $userparams)
        );
    }
}
