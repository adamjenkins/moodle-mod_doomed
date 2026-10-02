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

use stdClass;

/**
 * Grade calculation for Doomed activities.
 *
 * Attempts store only the raw statistics the player reported. Grades are
 * always computed from those and the activity's current settings, so a
 * teacher who changes the weights, the maximum grade or the grading method
 * regrades every attempt consistently.
 *
 * Grading is per level: each of the activity's levels gets the grade of its
 * best (or last) counting attempt, and the activity grade is the average
 * over all its levels, an unplayed level counting 0. Only completions of a
 * listed level count; deaths and other maps are recorded for the report but
 * earn nothing.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading {
    /** @var int No grade. */
    public const MODE_NONE = 0;

    /** @var int Full marks for completing a level, nothing otherwise. */
    public const MODE_COMPLETION = 1;

    /** @var int Weighted percentage of kills, items and secrets. */
    public const MODE_PERCENTAGE = 2;

    /** @var int Keep the highest attempt. */
    public const METHOD_HIGHEST = 1;

    /** @var int Keep the most recent attempt. */
    public const METHOD_LAST = 2;

    /** @var string Outcome of an attempt that finished the level. */
    public const OUTCOME_COMPLETED = 'completed';

    /** @var string Outcome of an attempt that ended in the player's death. */
    public const OUTCOME_DIED = 'died';

    /**
     * Grading mode choices.
     *
     * @return array mode => label
     */
    public static function mode_options(): array {
        return [
            self::MODE_NONE => get_string('grademode_none', 'mod_doomed'),
            self::MODE_COMPLETION => get_string('grademode_completion', 'mod_doomed'),
            self::MODE_PERCENTAGE => get_string('grademode_percentage', 'mod_doomed'),
        ];
    }

    /**
     * Grading method choices.
     *
     * @return array method => label
     */
    public static function method_options(): array {
        return [
            self::METHOD_HIGHEST => get_string('grademethod_highest', 'mod_doomed'),
            self::METHOD_LAST => get_string('grademethod_last', 'mod_doomed'),
        ];
    }

    /**
     * Whether an activity is graded at all.
     *
     * @param stdClass $doomed activity record
     * @return bool
     */
    public static function is_graded(stdClass $doomed): bool {
        return (int) $doomed->grademode !== self::MODE_NONE && (float) $doomed->grade > 0;
    }

    /**
     * Whether an attempt counts towards the grade and completion.
     *
     * It must have completed one of the activity's levels at the activity's
     * skill level or harder: a student can start a new game at another skill
     * from the game menu, and an easier game must not earn full marks.
     *
     * @param stdClass $doomed activity record
     * @param stdClass $attempt attempt record
     * @return bool
     */
    public static function attempt_counts(stdClass $doomed, stdClass $attempt): bool {
        return $attempt->outcome === self::OUTCOME_COMPLETED
            && levels::contains($doomed, (string) $attempt->map)
            && (int) $attempt->skill >= (int) $doomed->skill;
    }

    /**
     * The percentage (0-100) an attempt earns, or null if it does not count.
     *
     * @param stdClass $doomed activity record
     * @param stdClass $attempt attempt record
     * @return float|null
     */
    public static function attempt_percentage(stdClass $doomed, stdClass $attempt): ?float {
        if (!self::attempt_counts($doomed, $attempt)) {
            return null;
        }
        if ((int) $doomed->grademode !== self::MODE_PERCENTAGE) {
            return 100.0;
        }

        $parts = [
            [(int) $doomed->weightkills, (int) $attempt->kills, (int) $attempt->totalkills],
            [(int) $doomed->weightitems, (int) $attempt->items, (int) $attempt->totalitems],
            [(int) $doomed->weightsecrets, (int) $attempt->secrets, (int) $attempt->totalsecrets],
        ];
        $weighted = 0.0;
        $weights = 0;
        foreach ($parts as [$weight, $got, $total]) {
            if ($weight <= 0) {
                continue;
            }
            // A level with nothing of a kind to find gives full marks for it.
            $ratio = $total > 0 ? min(1.0, max(0, $got) / $total) : 1.0;
            $weighted += $weight * $ratio;
            $weights += $weight;
        }
        $percentage = $weights > 0 ? 100.0 * $weighted / $weights : 100.0;

        $bonus = (int) $doomed->timebonus;
        if ($bonus > 0 && (int) $attempt->partime > 0 && (int) $attempt->leveltime <= (int) $attempt->partime) {
            $percentage += $bonus;
        }
        return min(100.0, $percentage);
    }

    /**
     * The grade (on the activity's maximum grade) an attempt earns.
     *
     * @param stdClass $doomed activity record
     * @param stdClass $attempt attempt record
     * @return float|null null if ungraded or the attempt does not count
     */
    public static function attempt_grade(stdClass $doomed, stdClass $attempt): ?float {
        if (!self::is_graded($doomed)) {
            return null;
        }
        $percentage = self::attempt_percentage($doomed, $attempt);
        return $percentage === null ? null : round((float) $doomed->grade * $percentage / 100, 5);
    }

    /**
     * A user's grade for each of the activity's levels, by the grading method.
     *
     * @param stdClass $doomed activity record
     * @param stdClass[] $attempts the user's attempts, any order
     * @return array map name => grade, or null for a level with no counting attempt; in level order
     */
    public static function level_grades(stdClass $doomed, array $attempts): array {
        $bymap = [];
        foreach ($attempts as $attempt) {
            $grade = self::attempt_grade($doomed, $attempt);
            if ($grade !== null) {
                $bymap[strtoupper($attempt->map)][] = [$attempt, $grade];
            }
        }
        $result = [];
        foreach (levels::from_record($doomed) as $map) {
            $graded = $bymap[$map] ?? [];
            if (!$graded) {
                $result[$map] = null;
            } else if ((int) $doomed->grademethod === self::METHOD_LAST) {
                usort($graded, fn($a, $b) => [$b[0]->timecreated, $b[0]->id] <=> [$a[0]->timecreated, $a[0]->id]);
                $result[$map] = $graded[0][1];
            } else {
                $result[$map] = max(array_column($graded, 1));
            }
        }
        return $result;
    }

    /**
     * A user's activity grade: the average of their level grades, unplayed levels counting 0.
     *
     * @param stdClass $doomed activity record
     * @param stdClass[] $attempts the user's attempts, any order
     * @return float|null null if no attempt counts at all or the activity is ungraded
     */
    public static function aggregate(stdClass $doomed, array $attempts): ?float {
        $grades = self::level_grades($doomed, $attempts);
        $played = array_filter($grades, fn($grade) => $grade !== null);
        if (!$played) {
            return null;
        }
        return round(array_sum($played) / count($grades), 5);
    }

    /**
     * The activity's levels a user has completed (with a counting attempt), in level order.
     *
     * @param stdClass $doomed activity record
     * @param stdClass[] $attempts the user's attempts, any order
     * @return string[] map names
     */
    public static function completed_levels(stdClass $doomed, array $attempts): array {
        $done = [];
        foreach ($attempts as $attempt) {
            if (self::attempt_counts($doomed, $attempt)) {
                $done[strtoupper($attempt->map)] = true;
            }
        }
        return array_values(array_filter(levels::from_record($doomed), fn($map) => isset($done[$map])));
    }

    /**
     * A user's current grade in an activity.
     *
     * @param stdClass $doomed activity record
     * @param int $userid user id
     * @return float|null
     */
    public static function user_grade(stdClass $doomed, int $userid): ?float {
        global $DB;
        $attempts = $DB->get_records('doomed_attempts', ['doomedid' => $doomed->id, 'userid' => $userid]);
        return self::aggregate($doomed, $attempts);
    }

    /**
     * Gradebook grade records for one user or every user with attempts.
     *
     * Users whose attempts no longer earn a grade (for example after the
     * starting map changed) get a null rawgrade so an old grade is removed.
     *
     * @param stdClass $doomed activity record
     * @param int $userid one user, or 0 for all
     * @return array userid => grade object for grade_update()
     */
    public static function gradebook_grades(stdClass $doomed, int $userid = 0): array {
        global $DB;
        $params = ['doomedid' => $doomed->id];
        if ($userid) {
            $params['userid'] = $userid;
        }
        $byuser = [];
        foreach ($DB->get_records('doomed_attempts', $params, 'id') as $attempt) {
            $byuser[$attempt->userid][] = $attempt;
        }
        $grades = [];
        foreach ($byuser as $uid => $attempts) {
            $grade = self::aggregate($doomed, $attempts);
            $latest = max(array_column($attempts, 'timecreated'));
            $grades[$uid] = (object) [
                'userid' => $uid,
                'rawgrade' => $grade,
                'datesubmitted' => $latest,
                'dategraded' => $latest,
            ];
        }
        return $grades;
    }
}
