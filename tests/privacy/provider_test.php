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

namespace mod_doomed\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the mod_doomed privacy provider.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass first student */
    protected $user1;
    /** @var \stdClass second student */
    protected $user2;
    /** @var \stdClass first activity */
    protected $doomed1;
    /** @var \stdClass second activity */
    protected $doomed2;
    /** @var \context_module context of the first activity */
    protected $context1;
    /** @var \context_module context of the second activity */
    protected $context2;

    /**
     * Two students, two activities, attempts by both students in both activities.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->user1 = $gen->create_and_enrol($course, 'student');
        $this->user2 = $gen->create_and_enrol($course, 'student');
        $this->doomed1 = $gen->create_module('doomed', ['course' => $course->id, 'name' => 'Hangar']);
        $this->doomed2 = $gen->create_module('doomed', ['course' => $course->id, 'name' => 'Toxin refinery']);
        $this->context1 = \context_module::instance($this->doomed1->cmid);
        $this->context2 = \context_module::instance($this->doomed2->cmid);

        foreach ([$this->doomed1, $this->doomed2] as $doomed) {
            foreach ([$this->user1, $this->user2] as $user) {
                $this->add_attempt($doomed->id, $user->id, [
                    'outcome' => 'died',
                    'map' => 'E1M1',
                    'skill' => 2,
                    'timecreated' => 1700000000,
                ]);
                $this->add_attempt($doomed->id, $user->id, [
                    'outcome' => 'completed',
                    'map' => 'E1M1',
                    'skill' => 4,
                    'kills' => 12,
                    'totalkills' => 20,
                    'items' => 3,
                    'totalitems' => 9,
                    'secrets' => 1,
                    'totalsecrets' => 3,
                    'leveltime' => 95,
                    'partime' => 30,
                    'timecreated' => 1700000100,
                ]);
            }
        }
    }

    /**
     * Insert an attempt row directly.
     *
     * @param int $doomedid activity instance id
     * @param int $userid player
     * @param array $fields field overrides
     * @return int the new row id
     */
    protected function add_attempt(int $doomedid, int $userid, array $fields): int {
        global $DB;
        $fields += [
            'doomedid' => $doomedid,
            'userid' => $userid,
            'outcome' => 'completed',
            'map' => 'E1M1',
            'skill' => 3,
            'kills' => 0,
            'totalkills' => 0,
            'items' => 0,
            'totalitems' => 0,
            'secrets' => 0,
            'totalsecrets' => 0,
            'leveltime' => 0,
            'partime' => 0,
            'timecreated' => time(),
        ];
        return $DB->insert_record('doomed_attempts', (object) $fields);
    }

    /**
     * Count attempts for an activity and optionally a user.
     *
     * @param int $doomedid activity instance id
     * @param int|null $userid user, or null for all users
     * @return int
     */
    protected function count_attempts(int $doomedid, ?int $userid = null): int {
        global $DB;
        $conditions = ['doomedid' => $doomedid];
        if ($userid !== null) {
            $conditions['userid'] = $userid;
        }
        return $DB->count_records('doomed_attempts', $conditions);
    }

    /**
     * Metadata declares every personal field of doomed_attempts.
     */
    public function test_get_metadata(): void {
        global $CFG;
        require_once($CFG->libdir . '/ddllib.php');

        $collection = provider::get_metadata(new collection('mod_doomed'));
        $items = $collection->get_collection();
        $this->assertCount(2, $items);
        $table = reset($items);
        $this->assertInstanceOf(database_table::class, $table);
        $this->assertEquals('doomed_attempts', $table->get_name());
        $this->assertEquals('privacy:metadata:doomed_attempts', $table->get_summary());

        // Every column of the shipped schema except the primary key is declared. The schema is read from
        // install.xml rather than the test database so that a stale column left by a missing upgrade step
        // on the test site does not mask (or fake) drift between the schema and the provider.
        $xmldb = new \xmldb_file($CFG->dirroot . '/mod/doomed/db/install.xml');
        $this->assertTrue($xmldb->loadXMLStructure());
        $columns = [];
        foreach ($xmldb->getStructure()->getTable('doomed_attempts')->getFields() as $field) {
            $columns[] = $field->getName();
        }
        $expected = array_values(array_diff($columns, ['id']));
        $declared = array_keys($table->get_privacy_fields());
        sort($expected);
        sort($declared);
        $this->assertEquals($expected, $declared);
        foreach ($table->get_privacy_fields() as $field => $identifier) {
            $this->assertEquals('privacy:metadata:doomed_attempts:' . $field, $identifier);
        }

        $link = next($items);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\subsystem_link::class, $link);
        $this->assertEquals('core_grades', $link->get_name());

        // Every identifier must resolve to a real string, or the privacy registry shows [[...]].
        $sm = get_string_manager();
        $this->assertTrue($sm->string_exists($table->get_summary(), 'mod_doomed'));
        $this->assertTrue($sm->string_exists($link->get_summary(), 'mod_doomed'));
        foreach ($table->get_privacy_fields() as $identifier) {
            $this->assertTrue($sm->string_exists($identifier, 'mod_doomed'), $identifier);
        }
    }

    /**
     * Contexts are found for users with attempts only.
     */
    public function test_get_contexts_for_userid(): void {
        $contextids = provider::get_contexts_for_userid($this->user1->id)->get_contextids();
        sort($contextids);
        $expected = [$this->context1->id, $this->context2->id];
        sort($expected);
        $this->assertEquals($expected, array_map('intval', $contextids));

        $nobody = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($nobody->id));
    }

    /**
     * Users are found in an activity context, and nothing in other contexts.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->context1, 'mod_doomed');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        sort($userids);
        $expected = [$this->user1->id, $this->user2->id];
        sort($expected);
        $this->assertEquals($expected, array_map('intval', $userids));

        // A third activity with one player.
        $course = get_course($this->doomed1->course);
        $doomed3 = $this->getDataGenerator()->create_module('doomed', ['course' => $course->id]);
        $this->add_attempt($doomed3->id, $this->user2->id, []);
        $userlist = new userlist(\context_module::instance($doomed3->cmid), 'mod_doomed');
        provider::get_users_in_context($userlist);
        $this->assertEquals([(int) $this->user2->id], array_map('intval', $userlist->get_userids()));

        // A course context yields nobody.
        $userlist = new userlist(\context_course::instance($course->id), 'mod_doomed');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * Export writes the user's own attempts, human-readable, per activity.
     */
    public function test_export_user_data(): void {
        $this->export_context_data_for_user($this->user1->id, $this->context1, 'mod_doomed');

        $writer = writer::with_context($this->context1);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([]);
        $this->assertEquals('Hangar', $data->name);
        $this->assertCount(2, $data->attempts);

        [$died, $completed] = $data->attempts;
        $this->assertEquals(get_string('outcome_died', 'mod_doomed'), $died->outcome);
        $this->assertEquals(get_string('skill2', 'mod_doomed'), $died->skill);
        $this->assertNull($died->leveltime);
        $this->assertNull($died->partime);

        $this->assertEquals(get_string('outcome_completed', 'mod_doomed'), $completed->outcome);
        $this->assertEquals('E1M1', $completed->map);
        $this->assertEquals(get_string('skill4', 'mod_doomed'), $completed->skill);
        $this->assertSame(12, $completed->kills);
        $this->assertSame(20, $completed->totalkills);
        $this->assertSame(3, $completed->items);
        $this->assertSame(9, $completed->totalitems);
        $this->assertSame(1, $completed->secrets);
        $this->assertSame(3, $completed->totalsecrets);
        $this->assertSame(95, $completed->leveltimeseconds);
        $this->assertSame(30, $completed->partimeseconds);
        $this->assertEquals(format_time(95), $completed->leveltime);
        $this->assertEquals(format_time(30), $completed->partime);
        $this->assertEquals(\core_privacy\local\request\transform::datetime(1700000100), $completed->timecreated);

        // Only the approved context was exported.
        $this->assertFalse(writer::with_context($this->context2)->has_any_data());
    }

    /**
     * Export for a user with no attempts writes nothing.
     */
    public function test_export_user_data_no_attempts(): void {
        $nobody = $this->getDataGenerator()->create_user();
        $this->export_context_data_for_user($nobody->id, $this->context1, 'mod_doomed');
        $this->assertFalse(writer::with_context($this->context1)->has_any_data());
    }

    /**
     * Export of all contexts returned for a user covers both activities.
     */
    public function test_export_all_contexts(): void {
        $this->export_all_data_for_user($this->user2->id, 'mod_doomed');
        foreach ([$this->context1, $this->context2] as $context) {
            $data = writer::with_context($context)->get_data([]);
            $this->assertCount(2, $data->attempts);
        }
    }

    /**
     * Deleting one user's data leaves other users and other activities alone.
     */
    public function test_delete_data_for_user(): void {
        $contextlist = new approved_contextlist($this->user1, 'mod_doomed', [$this->context1->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $this->count_attempts($this->doomed1->id, $this->user1->id));
        $this->assertEquals(2, $this->count_attempts($this->doomed1->id, $this->user2->id));
        $this->assertEquals(2, $this->count_attempts($this->doomed2->id, $this->user1->id));
        $this->assertEquals(2, $this->count_attempts($this->doomed2->id, $this->user2->id));
    }

    /**
     * Deleting a list of users in one context leaves the rest alone.
     */
    public function test_delete_data_for_users(): void {
        $user3 = $this->getDataGenerator()->create_user();
        $this->add_attempt($this->doomed1->id, $user3->id, []);

        $userlist = new approved_userlist($this->context1, 'mod_doomed', [$this->user1->id, $user3->id]);
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $this->count_attempts($this->doomed1->id, $this->user1->id));
        $this->assertEquals(0, $this->count_attempts($this->doomed1->id, $user3->id));
        $this->assertEquals(2, $this->count_attempts($this->doomed1->id, $this->user2->id));
        $this->assertEquals(4, $this->count_attempts($this->doomed2->id));

        // A userlist bound to a non-module context deletes nothing.
        $course = \context_course::instance($this->doomed1->course);
        provider::delete_data_for_users(new approved_userlist($course, 'mod_doomed', [$this->user2->id]));
        $this->assertEquals(2, $this->count_attempts($this->doomed1->id, $this->user2->id));
    }

    /**
     * Deleting everything in one context leaves other activities alone.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->context1);

        $this->assertEquals(0, $this->count_attempts($this->doomed1->id));
        $this->assertEquals(4, $this->count_attempts($this->doomed2->id));

        // A course context deletes nothing.
        provider::delete_data_for_all_users_in_context(\context_course::instance($this->doomed1->course));
        $this->assertEquals(4, $this->count_attempts($this->doomed2->id));
    }
}
