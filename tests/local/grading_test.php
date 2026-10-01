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

namespace mod_doomed\local;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Tests for the grade calculation.
 *
 * Every expected value is worked out by hand in the comment beside it.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(grading::class)]
final class grading_test extends advanced_testcase {
    /**
     * An activity record with percentage grading out of 100 and equal weights.
     *
     * @param array $fields overrides
     * @return stdClass
     */
    private static function doomed(array $fields = []): stdClass {
        return (object) ($fields + [
            'id' => 1,
            'startmap' => 'E1M1',
            'skill' => 3,
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 100,
            'weightkills' => 1,
            'weightitems' => 1,
            'weightsecrets' => 1,
            'timebonus' => 0,
            'grademethod' => grading::METHOD_HIGHEST,
        ]);
    }

    /**
     * An attempt that completed E1M1 at skill 3 with nothing on the level.
     *
     * @param array $fields overrides
     * @return stdClass
     */
    private static function attempt(array $fields = []): stdClass {
        return (object) ($fields + [
            'id' => 1,
            'outcome' => grading::OUTCOME_COMPLETED,
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
            'timecreated' => 1000,
        ]);
    }

    /**
     * Percentage cases.
     *
     * @return array name => [activity overrides, attempt overrides, expected percentage]
     */
    public static function percentage_provider(): array {
        $half = ['kills' => 5, 'totalkills' => 10, 'items' => 2, 'totalitems' => 4, 'secrets' => 1, 'totalsecrets' => 2];
        return [
            // Every total is 0, so every ratio is 1: (1 + 1 + 1) / 3 = 100.
            'empty level' => [[], [], 100.0],
            // Ratios 0.5, 0.5, 0.5 with weights 1: 1.5 / 3 = 0.5 -> 50.
            'half of everything' => [[], $half, 50.0],
            // Weights 2, 1, 1; ratios 10/10 = 1, 0/4 = 0, 1/2 = 0.5:
            // (2 * 1 + 1 * 0 + 1 * 0.5) / 4 = 2.5 / 4 = 0.625 -> 62.5.
            'weighted' => [
                ['weightkills' => 2],
                ['kills' => 10, 'totalkills' => 10, 'items' => 0, 'totalitems' => 4, 'secrets' => 1, 'totalsecrets' => 2],
                62.5,
            ],
            // Kills 15/10 are capped at 1, items 0/0 count as 1, secrets 0/3 = 0: 2 / 3 -> 66.666...
            'kills over the total are capped, empty total is full' => [
                [],
                ['kills' => 15, 'totalkills' => 10, 'secrets' => 0, 'totalsecrets' => 3],
                200 / 3,
            ],
            // Secrets have weight 0 and are ignored: (1/4 + 3/4) / 2 = 0.5 -> 50.
            'zero weight is left out' => [
                ['weightsecrets' => 0],
                ['kills' => 1, 'totalkills' => 4, 'items' => 3, 'totalitems' => 4, 'secrets' => 0, 'totalsecrets' => 5],
                50.0,
            ],
            // No weight above 0 at all: full marks whatever the statistics.
            'all weights zero' => [
                ['weightkills' => 0, 'weightitems' => 0, 'weightsecrets' => 0],
                ['kills' => 0, 'totalkills' => 10, 'items' => 0, 'totalitems' => 10],
                100.0,
            ],
            // A negative count is treated as 0: kills 0/10 = 0, items and secrets empty = 1: 2 / 3.
            'negative count is zero' => [[], ['kills' => -5, 'totalkills' => 10], 200 / 3],
            // 50 + 10 bonus because leveltime 120 <= partime 120 (on par counts).
            'time bonus on par' => [['timebonus' => 10], $half + ['leveltime' => 120, 'partime' => 120], 60.0],
            // 50 + 10 bonus because leveltime 90 < partime 120.
            'time bonus under par' => [['timebonus' => 10], $half + ['leveltime' => 90, 'partime' => 120], 60.0],
            // 121 > 120: no bonus, stays 50.
            'time bonus missed' => [['timebonus' => 10], $half + ['leveltime' => 121, 'partime' => 120], 50.0],
            // Partime 0 means the level has no par: no bonus, stays 50.
            'no par time' => [['timebonus' => 10], $half + ['leveltime' => 1, 'partime' => 0], 50.0],
            // Bonus switched off: stays 50.
            'time bonus off' => [['timebonus' => 0], $half + ['leveltime' => 90, 'partime' => 120], 50.0],
            // Only kills weigh: 19/20 = 95, + 10 bonus = 105, capped at 100.
            'capped at 100' => [
                ['timebonus' => 10, 'weightitems' => 0, 'weightsecrets' => 0],
                ['kills' => 19, 'totalkills' => 20, 'leveltime' => 10, 'partime' => 30],
                100.0,
            ],
            // Pass/fail mode ignores the statistics: completing the map is 100.
            'completion mode' => [
                ['grademode' => grading::MODE_COMPLETION],
                ['kills' => 0, 'totalkills' => 10, 'items' => 0, 'totalitems' => 10, 'secrets' => 0, 'totalsecrets' => 10],
                100.0,
            ],
            // Map names compare case-insensitively.
            'lower-case attempt map' => [[], ['map' => 'e1m1'], 100.0],
            'lower-case start map' => [['startmap' => 'e1m1'], [], 100.0],
            // A harder skill than the activity's counts.
            'harder skill' => [[], ['skill' => 5], 100.0],
        ];
    }

    /**
     * attempt_percentage for attempts that count.
     *
     * @param array $doomed activity overrides
     * @param array $attempt attempt overrides
     * @param float $expected expected percentage
     */
    #[DataProvider('percentage_provider')]
    public function test_attempt_percentage(array $doomed, array $attempt, float $expected): void {
        $percentage = grading::attempt_percentage(self::doomed($doomed), self::attempt($attempt));
        $this->assertIsFloat($percentage);
        $this->assertEqualsWithDelta($expected, $percentage, 1e-9);
    }

    /**
     * Attempts that do not count.
     *
     * @return array name => [activity overrides, attempt overrides]
     */
    public static function not_counting_provider(): array {
        return [
            'died' => [[], ['outcome' => grading::OUTCOME_DIED]],
            'another map' => [[], ['map' => 'E1M2']],
            'easier skill' => [[], ['skill' => 2]],
            'died in completion mode' => [['grademode' => grading::MODE_COMPLETION], ['outcome' => grading::OUTCOME_DIED]],
            'another map in completion mode' => [['grademode' => grading::MODE_COMPLETION, 'startmap' => 'MAP01'], []],
        ];
    }

    /**
     * attempt_percentage, attempt_counts and attempt_grade for attempts that do not count.
     *
     * @param array $doomed activity overrides
     * @param array $attempt attempt overrides
     */
    #[DataProvider('not_counting_provider')]
    public function test_attempt_not_counting(array $doomed, array $attempt): void {
        $doomed = self::doomed($doomed);
        $attempt = self::attempt($attempt);
        $this->assertFalse(grading::attempt_counts($doomed, $attempt));
        $this->assertNull(grading::attempt_percentage($doomed, $attempt));
        $this->assertNull(grading::attempt_grade($doomed, $attempt));
    }

    /**
     * attempt_grade scales the percentage to the maximum grade, rounded to 5 places.
     */
    public function test_attempt_grade(): void {
        // Weighted case above: 62.5 % of 50 = 31.25.
        $doomed = self::doomed(['grade' => 50, 'weightkills' => 2]);
        $attempt = self::attempt(['kills' => 10, 'totalkills' => 10, 'totalitems' => 4, 'secrets' => 1, 'totalsecrets' => 2]);
        $this->assertEqualsWithDelta(31.25, grading::attempt_grade($doomed, $attempt), 1e-9);

        // 2/3 of everything on a maximum of 7: 7 * 66.666... / 100 = 4.666666... -> 4.66667.
        $doomed = self::doomed(['grade' => 7]);
        $attempt = self::attempt(['kills' => 2, 'totalkills' => 3, 'items' => 2, 'totalitems' => 3, 'secrets' => 2,
            'totalsecrets' => 3]);
        $this->assertSame(4.66667, grading::attempt_grade($doomed, $attempt));

        // Pass/fail mode: full marks, 80 of 80.
        $doomed = self::doomed(['grade' => 80, 'grademode' => grading::MODE_COMPLETION]);
        $this->assertEqualsWithDelta(80.0, grading::attempt_grade($doomed, self::attempt()), 1e-9);
    }

    /**
     * attempt_grade is null when the activity is not graded.
     */
    public function test_attempt_grade_ungraded(): void {
        $attempt = self::attempt();
        $this->assertNull(grading::attempt_grade(self::doomed(['grademode' => grading::MODE_NONE]), $attempt));
        $this->assertNull(grading::attempt_grade(self::doomed(['grade' => 0]), $attempt));
        $this->assertNull(grading::attempt_grade(self::doomed(['grade' => -5]), $attempt));
        $this->assertFalse(grading::is_graded(self::doomed(['grademode' => grading::MODE_NONE])));
        $this->assertFalse(grading::is_graded(self::doomed(['grade' => 0])));
        $this->assertTrue(grading::is_graded(self::doomed()));
        $this->assertTrue(grading::is_graded(self::doomed(['grademode' => grading::MODE_COMPLETION])));
    }

    /**
     * Three counting attempts with only kills weighing, out of 100.
     *
     * Kills 8/10 = 80, 3/10 = 30, 5/10 = 50; in time order 80 (t=100),
     * 50 (t=200), 30 (t=300). The array is deliberately not in time order.
     *
     * @return stdClass[]
     */
    private static function three_attempts(): array {
        return [
            self::attempt(['id' => 11, 'kills' => 3, 'totalkills' => 10, 'timecreated' => 300]),
            self::attempt(['id' => 12, 'kills' => 8, 'totalkills' => 10, 'timecreated' => 100]),
            self::attempt(['id' => 13, 'kills' => 5, 'totalkills' => 10, 'timecreated' => 200]),
        ];
    }

    /**
     * Highest keeps the best attempt.
     */
    public function test_aggregate_highest(): void {
        $doomed = self::doomed(['weightitems' => 0, 'weightsecrets' => 0, 'grademethod' => grading::METHOD_HIGHEST]);
        // Max of 30, 80, 50.
        $this->assertEqualsWithDelta(80.0, grading::aggregate($doomed, self::three_attempts()), 1e-9);
    }

    /**
     * Last keeps the most recent attempt by time.
     */
    public function test_aggregate_last(): void {
        $doomed = self::doomed(['weightitems' => 0, 'weightsecrets' => 0, 'grademethod' => grading::METHOD_LAST]);
        // The t=300 attempt earned 30.
        $this->assertEqualsWithDelta(30.0, grading::aggregate($doomed, self::three_attempts()), 1e-9);
    }

    /**
     * Last breaks a time tie by id, the later insert winning.
     */
    public function test_aggregate_last_tie_broken_by_id(): void {
        $doomed = self::doomed(['weightitems' => 0, 'weightsecrets' => 0, 'grademethod' => grading::METHOD_LAST]);
        $attempts = [
            self::attempt(['id' => 9, 'kills' => 2, 'totalkills' => 10, 'timecreated' => 500]),
            self::attempt(['id' => 7, 'kills' => 9, 'totalkills' => 10, 'timecreated' => 500]),
        ];
        // Same time; id 9 > 7, so its 2/10 = 20 wins over 90.
        $this->assertEqualsWithDelta(20.0, grading::aggregate($doomed, $attempts), 1e-9);
    }

    /**
     * Last ignores later attempts that do not count.
     */
    public function test_aggregate_last_skips_non_counting(): void {
        $doomed = self::doomed(['weightitems' => 0, 'weightsecrets' => 0, 'grademethod' => grading::METHOD_LAST]);
        $attempts = self::three_attempts();
        $attempts[] = self::attempt(['id' => 20, 'outcome' => grading::OUTCOME_DIED, 'timecreated' => 900]);
        $attempts[] = self::attempt(['id' => 21, 'map' => 'E1M2', 'timecreated' => 901]);
        // The latest counting attempt is still the t=300 one: 30.
        $this->assertEqualsWithDelta(30.0, grading::aggregate($doomed, $attempts), 1e-9);
    }

    /**
     * Aggregate is null without a counting attempt or a grade.
     */
    public function test_aggregate_null(): void {
        $doomed = self::doomed();
        $this->assertNull(grading::aggregate($doomed, []));
        $this->assertNull(grading::aggregate($doomed, [
            self::attempt(['outcome' => grading::OUTCOME_DIED]),
            self::attempt(['id' => 2, 'skill' => 1]),
        ]));
        $this->assertNull(grading::aggregate(self::doomed(['grademode' => grading::MODE_NONE]), [self::attempt()]));
        $ungraded = self::doomed(['grademethod' => grading::METHOD_LAST, 'grade' => 0]);
        $this->assertNull(grading::aggregate($ungraded, [self::attempt()]));
    }

    /**
     * user_grade and gradebook_grades read the attempts from the database.
     */
    public function test_user_grade_and_gradebook_grades(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $doomed = $this->getDataGenerator()->create_module('doomed', [
            'course' => $course->id,
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 50,
            'weightitems' => 0,
            'weightsecrets' => 0,
        ]);
        $doomed = $DB->get_record('doomed', ['id' => $doomed->id]);
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        $insert = function (int $userid, array $fields) use ($DB, $doomed) {
            $record = self::attempt($fields + ['doomedid' => $doomed->id, 'userid' => $userid]);
            unset($record->id);
            return $DB->insert_record('doomed_attempts', $record);
        };
        // User 1: kills 4/10 = 40 % and 6/10 = 60 % of 50 -> 20 and 30; highest 30.
        $insert($u1->id, ['kills' => 4, 'totalkills' => 10, 'timecreated' => 100]);
        $insert($u1->id, ['kills' => 6, 'totalkills' => 10, 'timecreated' => 200]);
        // User 2: only a death -> no grade, but still listed with a null rawgrade.
        $insert($u2->id, ['outcome' => grading::OUTCOME_DIED, 'timecreated' => 300]);

        $this->assertEqualsWithDelta(30.0, grading::user_grade($doomed, $u1->id), 1e-9);
        $this->assertNull(grading::user_grade($doomed, $u2->id));
        $this->assertNull(grading::user_grade($doomed, $u3->id));

        $grades = grading::gradebook_grades($doomed);
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], array_keys($grades));
        $this->assertEqualsWithDelta(30.0, $grades[$u1->id]->rawgrade, 1e-9);
        $this->assertEquals(200, $grades[$u1->id]->datesubmitted);
        $this->assertNull($grades[$u2->id]->rawgrade);

        $one = grading::gradebook_grades($doomed, (int) $u2->id);
        $this->assertSame([(int) $u2->id], array_map('intval', array_keys($one)));
        $this->assertSame([], grading::gradebook_grades($doomed, (int) $u3->id));
    }
}
