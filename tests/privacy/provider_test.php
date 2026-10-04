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

namespace mod_aiescape\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests for mod_aiescape.
 *
 * Two aiescape activities each hold data for two users, plus a forum whose
 * course-module instance number equals the first activity's id, so a query
 * that forgets to restrict to the aiescape module would leak into it.
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass First activity. */
    private \stdClass $a1;
    /** @var \stdClass Second activity. */
    private \stdClass $a2;
    /** @var \context_module Context of the first activity. */
    private \context_module $ctx1;
    /** @var \context_module Context of the second activity. */
    private \context_module $ctx2;
    /** @var \context_module Context of a forum whose cm instance equals $a1->id. */
    private \context_module $forumctx;
    /** @var \stdClass First user. */
    private \stdClass $u1;
    /** @var \stdClass Second user. */
    private \stdClass $u2;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $this->a1 = $dg->create_module('aiescape', ['course' => $course->id]);
        $this->a2 = $dg->create_module('aiescape', ['course' => $course->id]);
        $this->ctx1 = \context_module::instance($this->a1->cmid);
        $this->ctx2 = \context_module::instance($this->a2->cmid);

        // A forum whose course module points at an instance number equal to the
        // first aiescape id (instance ids of different modules overlap freely).
        $forum = $dg->create_module('forum', ['course' => $course->id]);
        $DB->set_field('course_modules', 'instance', $this->a1->id, ['id' => $forum->cmid]);
        $this->forumctx = \context_module::instance($forum->cmid);

        $this->u1 = $dg->create_and_enrol($course, 'student');
        $this->u2 = $dg->create_and_enrol($course, 'student');
        foreach ([$this->a1, $this->a2] as $a) {
            foreach ([$this->u1, $this->u2] as $u) {
                $this->create_user_data((int) $a->id, (int) $u->id);
            }
        }
    }

    /**
     * Creates an attempt with one message and one flag.
     *
     * @param int $aiescapeid
     * @param int $userid
     */
    private function create_user_data(int $aiescapeid, int $userid): void {
        /** @var \mod_aiescape_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiescape');
        $attempt = $generator->create_attempt(['aiescape' => $aiescapeid, 'userid' => $userid, 'stepstally' => 2]);
        $message = $generator->create_message([
            'attemptid' => $attempt->id,
            'role' => 'user',
            'message' => "Message from $userid in $aiescapeid",
        ]);
        $generator->create_flag(['attemptid' => $attempt->id, 'messageid' => $message->id, 'keyword' => 'help']);
    }

    /**
     * Counts a user's attempts, messages and flags in one activity.
     *
     * @param int $aiescapeid
     * @param int $userid
     * @return int[] [attempts, messages, flags]
     */
    private function count_user_data(int $aiescapeid, int $userid): array {
        global $DB;
        $attemptids = $DB->get_fieldset_select('aiescape_attempts', 'id', 'aiescape = ? AND userid = ?', [$aiescapeid, $userid]);
        if (!$attemptids) {
            return [0, 0, 0];
        }
        [$insql, $params] = $DB->get_in_or_equal($attemptids);
        return [
            count($attemptids),
            $DB->count_records_select('aiescape_messages', "attemptid $insql", $params),
            $DB->count_records_select('aiescape_flags', "attemptid $insql", $params),
        ];
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_aiescape'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('aiescape_attempts', $names);
        $this->assertContains('aiescape_messages', $names);
        $this->assertContains('aiescape_flags', $names);
        $this->assertContains('aiprovider', $names);
    }

    public function test_get_contexts_for_userid(): void {
        $contextids = provider::get_contexts_for_userid((int) $this->u1->id)->get_contextids();
        sort($contextids);
        $expected = [$this->ctx1->id, $this->ctx2->id];
        sort($expected);
        $this->assertEquals($expected, $contextids);

        $nobody = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());
    }

    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->ctx1, 'mod_aiescape');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        sort($userids);
        $expected = [(int) $this->u1->id, (int) $this->u2->id];
        sort($expected);
        $this->assertEquals($expected, array_map('intval', $userids));
    }

    public function test_get_users_in_context_ignores_other_module_with_same_instance(): void {
        $userlist = new userlist($this->forumctx, 'mod_aiescape');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
    }

    public function test_export_user_data(): void {
        global $DB;
        $attempt = $DB->get_record('aiescape_attempts', ['aiescape' => $this->a1->id, 'userid' => $this->u1->id], '*', MUST_EXIST);

        $this->export_context_data_for_user((int) $this->u1->id, $this->ctx1, 'mod_aiescape');
        $writer = writer::with_context($this->ctx1);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data(['attempt_' . $attempt->id]);
        $this->assertEquals(2, $data->stepstally);
        $this->assertCount(1, $data->messages);
        $this->assertSame("Message from {$this->u1->id} in {$this->a1->id}", $data->messages[0]->message);
        $this->assertCount(1, $data->flags);
        $this->assertSame('help', $data->flags[0]->keyword);

        // The other user's attempt is not exported for u1.
        $other = $DB->get_record('aiescape_attempts', ['aiescape' => $this->a1->id, 'userid' => $this->u2->id], '*', MUST_EXIST);
        $this->assertEmpty($writer->get_data(['attempt_' . $other->id]));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->ctx1);

        $this->assertSame([0, 0, 0], $this->count_user_data((int) $this->a1->id, (int) $this->u1->id));
        $this->assertSame([0, 0, 0], $this->count_user_data((int) $this->a1->id, (int) $this->u2->id));
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a2->id, (int) $this->u1->id));
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a2->id, (int) $this->u2->id));
    }

    public function test_delete_data_for_all_users_in_foreign_context_deletes_nothing(): void {
        provider::delete_data_for_all_users_in_context($this->forumctx);
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a1->id, (int) $this->u1->id));
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a1->id, (int) $this->u2->id));
    }

    public function test_delete_data_for_user(): void {
        $contextlist = new approved_contextlist($this->u1, 'mod_aiescape', [$this->ctx1->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame([0, 0, 0], $this->count_user_data((int) $this->a1->id, (int) $this->u1->id));
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a1->id, (int) $this->u2->id));
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a2->id, (int) $this->u1->id));
    }

    public function test_delete_data_for_users(): void {
        $userlist = new approved_userlist($this->ctx1, 'mod_aiescape', [(int) $this->u1->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame([0, 0, 0], $this->count_user_data((int) $this->a1->id, (int) $this->u1->id));
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a1->id, (int) $this->u2->id));
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a2->id, (int) $this->u1->id));
    }

    public function test_delete_data_for_users_in_foreign_context_deletes_nothing(): void {
        $userlist = new approved_userlist($this->forumctx, 'mod_aiescape', [(int) $this->u1->id]);
        provider::delete_data_for_users($userlist);
        $this->assertSame([1, 1, 1], $this->count_user_data((int) $this->a1->id, (int) $this->u1->id));
    }
}
