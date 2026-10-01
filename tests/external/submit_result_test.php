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

namespace mod_doomed\external;

use advanced_testcase;
use context_module;
use core_external\external_api;
use mod_doomed\event\attempt_submitted;
use mod_doomed\local\grading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Tests for the submit_result external function.
 *
 * The activity used throughout grades by percentage out of 50 with equal
 * weights and a 10-point time bonus, so the default submission below
 * (half of every statistic, 100 s on a 120 s par) earns
 * (0.5 + 0.5 + 0.5) / 3 = 50 % + 10 = 60 % of 50 = 30 points.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(submit_result::class)]
#[CoversClass(attempt_submitted::class)]
final class submit_result_test extends advanced_testcase {
    /** @var stdClass course */
    private stdClass $course;

    /** @var stdClass activity record */
    private stdClass $doomed;

    /** @var stdClass student */
    private stdClass $student;

    /** @var \frozen_clock clock; submit() advances it so results are not throttled */
    private \frozen_clock $clock;

    /**
     * A course with completion, one graded activity and an enrolled student.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->clock = $this->mock_clock_with_frozen(1790000000);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['enablecompletion' => 1]);
        $this->doomed = $generator->create_module('doomed', [
            'course' => $this->course->id,
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 50,
            'timebonus' => 10,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmap' => 1,
        ]);
        $this->student = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * Call the function the way the web service layer does.
     *
     * @param array $overrides parameter overrides
     * @param int $advance seconds to move the frozen clock on before submitting
     * @return array cleaned return value
     */
    private function submit(array $overrides = [], int $advance = 60): array {
        // Real games report at most every few seconds; move time on so the throttle does not apply.
        $this->clock->bump($advance);
        $p = $overrides + [
            'cmid' => $this->doomed->cmid,
            'outcome' => 'completed',
            'map' => 'E1M1',
            'skill' => 3,
            'kills' => 5,
            'totalkills' => 10,
            'items' => 2,
            'totalitems' => 4,
            'secrets' => 1,
            'totalsecrets' => 2,
            // 100 s and 17 tics: intdiv(3517, 35) = 100.
            'timetics' => 3517,
            // 120 s par: intdiv(4200, 35) = 120.
            'partics' => 4200,
        ];
        $result = submit_result::execute(
            $p['cmid'],
            $p['outcome'],
            $p['map'],
            $p['skill'],
            $p['kills'],
            $p['totalkills'],
            $p['items'],
            $p['totalitems'],
            $p['secrets'],
            $p['totalsecrets'],
            $p['timetics'],
            $p['partics']
        );
        return external_api::clean_returnvalue(submit_result::execute_returns(), $result);
    }

    /**
     * The student's grade in the gradebook, or null.
     *
     * @return float|null
     */
    private function gradebook_grade(): ?float {
        $grades = grade_get_grades($this->course->id, 'mod', 'doomed', $this->doomed->id, $this->student->id);
        $grade = $grades->items[0]->grades[$this->student->id]->grade ?? null;
        return $grade === null ? null : (float) $grade;
    }

    /**
     * The student's completion state of the activity.
     *
     * @return int
     */
    private function completion_state(): int {
        $cm = get_fast_modinfo($this->course->id)->get_cm($this->doomed->cmid);
        $completion = new \completion_info($this->course);
        return (int) $completion->get_data($cm, false, $this->student->id)->completionstate;
    }

    /**
     * A completion is stored, graded, pushed to the gradebook, completes the activity and fires the event.
     */
    public function test_happy_path(): void {
        global $DB;
        $this->setUser($this->student);
        $this->assertNull($this->gradebook_grade());
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion_state());

        $sink = $this->redirectEvents();
        // Lower-case map is accepted and stored upper case.
        $result = $this->submit(['map' => 'e1m1']);
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof attempt_submitted));
        $sink->close();

        $this->assertTrue($result['counted']);
        $this->assertEqualsWithDelta(30.0, $result['grade'], 1e-9);
        $this->assertEqualsWithDelta(50.0, $result['maxgrade'], 1e-9);

        $row = $DB->get_record('doomed_attempts', ['id' => $result['attemptid']], '*', MUST_EXIST);
        $this->assertEquals($this->doomed->id, $row->doomedid);
        $this->assertEquals($this->student->id, $row->userid);
        $this->assertSame('completed', $row->outcome);
        $this->assertSame('E1M1', $row->map);
        $this->assertEquals(3, $row->skill);
        $this->assertEquals(5, $row->kills);
        $this->assertEquals(10, $row->totalkills);
        $this->assertEquals(2, $row->items);
        $this->assertEquals(4, $row->totalitems);
        $this->assertEquals(1, $row->secrets);
        $this->assertEquals(2, $row->totalsecrets);
        $this->assertEquals(100, $row->leveltime);
        $this->assertEquals(120, $row->partime);
        $this->assertSame($this->clock->time(), (int) $row->timecreated);

        $this->assertEqualsWithDelta(30.0, $this->gradebook_grade(), 1e-9);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion_state());

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertEquals($result['attemptid'], $event->objectid);
        $this->assertEquals(context_module::instance($this->doomed->cmid)->id, $event->get_context()->id);
        $this->assertEquals($this->student->id, $event->userid);
        $this->assertSame(['outcome' => 'completed', 'map' => 'E1M1'], $event->other);
        $this->assertSame('doomed_attempts', $event->objecttable);
    }

    /**
     * Kills above the level total are allowed (spawned monsters) and capped by grading.
     */
    public function test_kills_over_total_allowed(): void {
        $this->setUser($this->student);
        // Kills 12/10 -> 1, items 4/4 -> 1, secrets 2/2 -> 1: 100 %, + 10 capped at 100: 50 points.
        $result = $this->submit(['kills' => 12, 'totalkills' => 10, 'items' => 4, 'secrets' => 2]);
        $this->assertTrue($result['counted']);
        $this->assertEqualsWithDelta(50.0, $result['grade'], 1e-9);
    }

    /**
     * A death is stored but earns nothing and does not complete the activity.
     */
    public function test_died_attempt(): void {
        global $DB;
        $this->setUser($this->student);
        $sink = $this->redirectEvents();
        $result = $this->submit(['outcome' => 'died', 'timetics' => 0]);
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof attempt_submitted));
        $sink->close();

        $this->assertFalse($result['counted']);
        $this->assertNull($result['grade']);
        $this->assertEqualsWithDelta(50.0, $result['maxgrade'], 1e-9);
        $row = $DB->get_record('doomed_attempts', ['id' => $result['attemptid']], '*', MUST_EXIST);
        $this->assertSame('died', $row->outcome);
        $this->assertEquals(0, $row->leveltime);
        $this->assertNull($this->gradebook_grade());
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion_state());
        $this->assertCount(1, $events);
        $this->assertSame(['outcome' => 'died', 'map' => 'E1M1'], $events[0]->other);
    }

    /**
     * Another map, or an easier skill, is stored but does not count.
     */
    public function test_other_map_and_easier_skill(): void {
        global $DB;
        $this->setUser($this->student);

        $result = $this->submit(['map' => 'E1M2']);
        $this->assertFalse($result['counted']);
        $this->assertNull($result['grade']);
        $this->assertSame('E1M2', $DB->get_field('doomed_attempts', 'map', ['id' => $result['attemptid']]));

        $result = $this->submit(['skill' => 2]);
        $this->assertFalse($result['counted']);
        $this->assertNull($result['grade']);

        $this->assertEquals(2, $DB->count_records('doomed_attempts', ['doomedid' => $this->doomed->id]));
        $this->assertNull($this->gradebook_grade());
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion_state());
    }

    /**
     * An ungraded activity still records and counts the attempt.
     */
    public function test_ungraded_activity(): void {
        $generator = $this->getDataGenerator();
        $ungraded = $generator->create_module('doomed', ['course' => $this->course->id, 'grademode' => grading::MODE_NONE]);
        $this->setUser($this->student);
        $result = $this->submit(['cmid' => $ungraded->cmid]);
        $this->assertTrue($result['counted']);
        $this->assertNull($result['grade']);
        $this->assertNull($result['maxgrade']);
    }

    /**
     * Values no real game can produce.
     *
     * @return array name => [parameter overrides]
     */
    public static function invalid_provider(): array {
        return [
            'items over total' => [['items' => 5, 'totalitems' => 4]],
            'secrets over total' => [['secrets' => 3, 'totalsecrets' => 2]],
            'negative kills' => [['kills' => -1]],
            'negative total kills' => [['totalkills' => -1]],
            'negative items' => [['items' => -1]],
            'negative secrets' => [['secrets' => -1]],
            'count above the maximum' => [['kills' => submit_result::MAX_COUNT + 1]],
            'skill 0' => [['skill' => 0]],
            'skill 6' => [['skill' => 6]],
            'not a map name' => [['map' => 'E1M10']],
            'short map name' => [['map' => 'MAP1']],
            'map with punctuation' => [['map' => 'E1M1;']],
            'unknown outcome' => [['outcome' => 'quit']],
            'completed in no time' => [['timetics' => 0]],
            'negative time' => [['outcome' => 'died', 'timetics' => -35]],
            'time above a day' => [['timetics' => submit_result::MAX_TICS + 1]],
            'negative par' => [['partics' => -1]],
        ];
    }

    /**
     * Impossible values are rejected and nothing is stored.
     *
     * @param array $overrides parameter overrides
     */
    #[DataProvider('invalid_provider')]
    public function test_invalid_rejected(array $overrides): void {
        global $DB;
        $this->setUser($this->student);
        try {
            $this->submit($overrides);
            $this->fail('invalid_parameter_exception expected');
        } catch (\invalid_parameter_exception $e) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $e);
        }
        $this->assertSame(0, $DB->count_records('doomed_attempts'));
    }

    /**
     * A non-editing teacher has no mod/doomed:play.
     */
    public function test_teacher_refused(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($teacher);
        try {
            $this->submit();
            $this->fail('required_capability_exception expected');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
            $this->assertStringContainsString(get_string('doomed:play', 'mod_doomed'), $e->getMessage());
        }
        $this->assertSame(0, $DB->count_records('doomed_attempts'));
    }

    /**
     * A student whose play capability is prohibited is refused.
     */
    public function test_student_without_capability_refused(): void {
        global $DB;
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $context = context_module::instance($this->doomed->cmid);
        assign_capability('mod/doomed:play', CAP_PROHIBIT, $studentrole, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('mod/doomed:play', $context, $this->student));

        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        $this->submit();
    }

    /**
     * A user not enrolled in the course is refused at login.
     */
    public function test_not_enrolled_refused(): void {
        global $DB;
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        try {
            $this->submit();
            $this->fail('require_login_exception expected');
        } catch (\require_login_exception $e) {
            $this->assertInstanceOf(\require_login_exception::class, $e);
        }
        $this->assertSame(0, $DB->count_records('doomed_attempts'));
    }

    /**
     * Two results within MIN_INTERVAL seconds are refused; after it they are accepted.
     */
    public function test_throttled(): void {
        global $DB;
        $this->setUser($this->student);
        $this->submit();
        try {
            $this->submit([], submit_result::MIN_INTERVAL - 1);
            $this->fail('A second result within the interval must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('submittoosoon', $e->errorcode);
        }
        $this->assertSame(1, $DB->count_records('doomed_attempts', ['userid' => $this->student->id]));
        // MIN_INTERVAL - 1 has already passed; one more second reaches the interval.
        $this->submit([], 1);
        $this->assertSame(2, $DB->count_records('doomed_attempts', ['userid' => $this->student->id]));
    }

    /**
     * A user who already has MAX_ATTEMPTS results cannot store more.
     */
    public function test_attempt_cap(): void {
        global $DB;
        $this->setUser($this->student);
        $rows = [];
        for ($i = 0; $i < submit_result::MAX_ATTEMPTS; $i++) {
            $rows[] = ['doomedid' => $this->doomed->id, 'userid' => $this->student->id, 'outcome' => 'died',
                'map' => 'E1M1', 'skill' => 3, 'timecreated' => 1000 + $i];
        }
        $DB->insert_records('doomed_attempts', $rows);
        try {
            $this->submit();
            $this->fail('A result beyond the cap must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('submittoomany', $e->errorcode);
        }
        $this->assertSame(submit_result::MAX_ATTEMPTS, $DB->count_records('doomed_attempts', ['userid' => $this->student->id]));
    }
}
