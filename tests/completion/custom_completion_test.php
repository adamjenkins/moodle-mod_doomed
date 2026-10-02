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

namespace mod_doomed\completion;

use advanced_testcase;
use cm_info;
use mod_doomed\local\grading;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Tests for the custom completion rules.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(custom_completion::class)]
final class custom_completion_test extends advanced_testcase {
    /** @var stdClass course */
    private stdClass $course;

    /** @var stdClass student */
    private stdClass $student;

    /**
     * A course with completion enabled and an enrolled student.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Create an activity with automatic completion.
     *
     * Percentage grading out of 50 with only kills weighing, so an attempt
     * earns kills / totalkills * 50 points.
     *
     * @param array $fields activity overrides
     * @return stdClass activity record with cmid
     */
    private function create_doomed(array $fields = []): stdClass {
        return $this->getDataGenerator()->create_module('doomed', $fields + [
            'course' => $this->course->id,
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 50,
            'weightitems' => 0,
            'weightsecrets' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmap' => 1,
            'completionmingrade' => 30,
        ]);
    }

    /**
     * Store an attempt for the student.
     *
     * @param stdClass $doomed activity
     * @param array $fields attempt overrides
     * @return void
     */
    private function add_attempt(stdClass $doomed, array $fields = []): void {
        global $DB;
        $DB->insert_record('doomed_attempts', (object) ($fields + [
            'doomedid' => $doomed->id,
            'userid' => $this->student->id,
            'outcome' => grading::OUTCOME_COMPLETED,
            'map' => 'E1M1',
            'skill' => 3,
            'kills' => 0,
            'totalkills' => 10,
            'items' => 0,
            'totalitems' => 0,
            'secrets' => 0,
            'totalsecrets' => 0,
            'leveltime' => 60,
            'partime' => 0,
            'timecreated' => 1000,
        ]));
    }

    /**
     * The cm_info of an activity.
     *
     * @param stdClass $doomed activity
     * @return cm_info
     */
    private function cm(stdClass $doomed): cm_info {
        return get_fast_modinfo($this->course->id)->get_cm($doomed->cmid);
    }

    /**
     * Completion object for the student.
     *
     * @param stdClass $doomed activity
     * @return custom_completion
     */
    private function completion(stdClass $doomed): custom_completion {
        return new custom_completion($this->cm($doomed), (int) $this->student->id);
    }

    /**
     * completionmap is complete only after an attempt that counts.
     */
    public function test_completionmap(): void {
        $doomed = $this->create_doomed();
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmap'));

        $this->add_attempt($doomed, ['outcome' => grading::OUTCOME_DIED]);
        $this->add_attempt($doomed, ['map' => 'E1M2']);
        $this->add_attempt($doomed, ['skill' => 2]);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmap'));

        // Lower-case map still counts; zero kills does not matter for this rule.
        $this->add_attempt($doomed, ['map' => 'e1m1']);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion($doomed)->get_state('completionmap'));
    }

    /**
     * With several levels, completionmap needs every one of them completed.
     */
    public function test_completionmap_all_levels(): void {
        $doomed = $this->create_doomed(['levels' => 'E1M1,E1M2']);
        $this->add_attempt($doomed, ['map' => 'E1M1']);
        // An unlisted level does not stand in for a listed one.
        $this->add_attempt($doomed, ['map' => 'E1M3']);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmap'));

        $this->add_attempt($doomed, ['map' => 'E1M2']);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion($doomed)->get_state('completionmap'));
    }

    /**
     * completionmap is per user.
     */
    public function test_completionmap_other_user(): void {
        $doomed = $this->create_doomed();
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->add_attempt($doomed, ['userid' => $other->id]);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmap'));
    }

    /**
     * completionmingrade compares the aggregated grade, in points, to the minimum.
     */
    public function test_completionmingrade_highest(): void {
        $doomed = $this->create_doomed();
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmingrade'));

        // 5/10 kills = 50 % of 50 = 25 points < 30.
        $this->add_attempt($doomed, ['kills' => 5, 'timecreated' => 100]);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmingrade'));

        // A death earns nothing even with every kill.
        $this->add_attempt($doomed, ['kills' => 10, 'outcome' => grading::OUTCOME_DIED, 'timecreated' => 150]);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmingrade'));

        // 6/10 kills = 60 % of 50 = 30 points >= 30: the boundary completes.
        $this->add_attempt($doomed, ['kills' => 6, 'timecreated' => 200]);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion($doomed)->get_state('completionmingrade'));

        // Highest keeps 30 after a worse attempt (2/10 = 10 points).
        $this->add_attempt($doomed, ['kills' => 2, 'timecreated' => 300]);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion($doomed)->get_state('completionmingrade'));
    }

    /**
     * With the last-attempt method a worse later attempt loses the completion.
     */
    public function test_completionmingrade_last(): void {
        $doomed = $this->create_doomed(['grademethod' => grading::METHOD_LAST]);
        // 9/10 = 45 points, then 2/10 = 10 points: the last (10) is below 30.
        $this->add_attempt($doomed, ['kills' => 9, 'timecreated' => 100]);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion($doomed)->get_state('completionmingrade'));
        $this->add_attempt($doomed, ['kills' => 2, 'timecreated' => 200]);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmingrade'));
    }

    /**
     * The minimum grade is in points, not percent: 60 % of a 200-point activity passes 100.
     */
    public function test_completionmingrade_is_points(): void {
        $doomed = $this->create_doomed(['grade' => 200, 'completionmingrade' => 100]);
        // 6/10 = 60 % of 200 = 120 points >= 100.
        $this->add_attempt($doomed, ['kills' => 6]);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion($doomed)->get_state('completionmingrade'));

        $doomed = $this->create_doomed(['grade' => 200, 'completionmingrade' => 150]);
        // Same 120 points < 150, though 60 % > 50 %.
        $this->add_attempt($doomed, ['kills' => 6]);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion($doomed)->get_state('completionmingrade'));
    }

    /**
     * An unknown rule is refused.
     */
    public function test_unknown_rule(): void {
        $doomed = $this->create_doomed();
        $this->expectException(\moodle_exception::class);
        $this->completion($doomed)->get_state('completionbogus');
    }

    /**
     * Defined rules, available rules, descriptions and sort order.
     */
    public function test_rules_and_descriptions(): void {
        $this->assertSame(['completionmap', 'completionmingrade'], custom_completion::get_defined_custom_rules());

        $doomed = $this->create_doomed(['startmap' => 'E2M4', 'completionmingrade' => 37.5]);
        $completion = $this->completion($doomed);
        $this->assertEqualsCanonicalizing(['completionmap', 'completionmingrade'], $completion->get_available_custom_rules());
        $this->assertSame([
            'completionmap' => 'Complete every level: E2M4',
            'completionmingrade' => 'Achieve a grade of at least 37.5',
        ], $completion->get_custom_rule_descriptions());
        $this->assertSame(['completionview', 'completionmap', 'completionmingrade'], $completion->get_sort_order());

        // A minimum grade of 0 means the rule is off.
        $doomed = $this->create_doomed(['completionmingrade' => 0]);
        $this->assertSame(['completionmap'], array_values($this->completion($doomed)->get_available_custom_rules()));
    }
}
