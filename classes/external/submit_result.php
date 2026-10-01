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

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_doomed\local\grading;
use mod_doomed\local\wad;

/**
 * Record the result of a level completion or death reported by the player.
 *
 * The statistics come from the student's browser and can be forged; the
 * checks here only reject values that cannot come from a real game.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_result extends external_api {
    /** @var int Upper bound for any count; real maps stay far below it. */
    public const MAX_COUNT = 65535;

    /** @var int Upper bound for a level time in tics (24 hours). */
    public const MAX_TICS = 35 * 86400;

    /** @var int Game tics per second. */
    public const TICRATE = 35;

    /** @var int Seconds that must pass between two results from one user in one activity. */
    public const MIN_INTERVAL = 2;

    /** @var int Most results one user may store in one activity. */
    public const MAX_ATTEMPTS = 5000;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'outcome' => new external_value(PARAM_ALPHA, 'completed or died'),
            'map' => new external_value(PARAM_ALPHANUM, 'Map lump name, e.g. E1M1 or MAP01'),
            'skill' => new external_value(PARAM_INT, 'Skill level 1-5'),
            'kills' => new external_value(PARAM_INT, 'Monsters killed'),
            'totalkills' => new external_value(PARAM_INT, 'Monsters on the level', VALUE_DEFAULT, 0),
            'items' => new external_value(PARAM_INT, 'Items collected'),
            'totalitems' => new external_value(PARAM_INT, 'Items on the level', VALUE_DEFAULT, 0),
            'secrets' => new external_value(PARAM_INT, 'Secrets found'),
            'totalsecrets' => new external_value(PARAM_INT, 'Secrets on the level', VALUE_DEFAULT, 0),
            'timetics' => new external_value(PARAM_INT, 'Level time in game tics (35 per second)'),
            'partics' => new external_value(PARAM_INT, 'Par time in game tics; 0 if none', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Record a result.
     *
     * @param int $cmid course module id
     * @param string $outcome completed or died
     * @param string $map map name
     * @param int $skill skill level
     * @param int $kills monsters killed
     * @param int $totalkills monsters on the level
     * @param int $items items collected
     * @param int $totalitems items on the level
     * @param int $secrets secrets found
     * @param int $totalsecrets secrets on the level
     * @param int $timetics level time in tics
     * @param int $partics par time in tics
     * @return array
     */
    public static function execute(
        int $cmid,
        string $outcome,
        string $map,
        int $skill,
        int $kills,
        int $totalkills,
        int $items,
        int $totalitems,
        int $secrets,
        int $totalsecrets,
        int $timetics,
        int $partics
    ): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/doomed/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), compact(
            'cmid',
            'outcome',
            'map',
            'skill',
            'kills',
            'totalkills',
            'items',
            'totalitems',
            'secrets',
            'totalsecrets',
            'timetics',
            'partics'
        ));

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'doomed');
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/doomed:play', $context);
        $doomed = $DB->get_record('doomed', ['id' => $cm->instance], '*', MUST_EXIST);

        self::check_sanity($params);
        $now = \core\di::get(\core\clock::class)->time();
        self::check_volume((int) $doomed->id, (int) $USER->id, $now);

        $attempt = (object) [
            'doomedid' => $doomed->id,
            'userid' => $USER->id,
            'outcome' => $params['outcome'],
            'map' => strtoupper($params['map']),
            'skill' => $params['skill'],
            'kills' => $params['kills'],
            'totalkills' => $params['totalkills'],
            'items' => $params['items'],
            'totalitems' => $params['totalitems'],
            'secrets' => $params['secrets'],
            'totalsecrets' => $params['totalsecrets'],
            'leveltime' => intdiv($params['timetics'], self::TICRATE),
            'partime' => intdiv($params['partics'], self::TICRATE),
            'timecreated' => $now,
        ];
        $attempt->id = $DB->insert_record('doomed_attempts', $attempt);

        $event = \mod_doomed\event\attempt_submitted::create([
            'objectid' => $attempt->id,
            'context' => $context,
            'other' => ['outcome' => $attempt->outcome, 'map' => $attempt->map],
        ]);
        $event->add_record_snapshot('doomed_attempts', $attempt);
        $event->trigger();

        $counted = grading::attempt_counts($doomed, $attempt);
        if ($counted) {
            doomed_update_grades($doomed, $USER->id);
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
            }
        }

        $grade = grading::attempt_grade($doomed, $attempt);
        return [
            'attemptid' => (int) $attempt->id,
            'counted' => $counted,
            'grade' => $grade,
            'maxgrade' => grading::is_graded($doomed) ? (float) $doomed->grade : null,
        ];
    }

    /**
     * Refuse floods of results: a real game cannot report twice within
     * MIN_INTERVAL seconds (a level has to load and be played), and no
     * student needs more than MAX_ATTEMPTS stored results.
     *
     * @param int $doomedid activity id
     * @param int $userid user id
     * @param int $now current time
     * @throws \moodle_exception
     */
    protected static function check_volume(int $doomedid, int $userid, int $now): void {
        global $DB;
        $recent = $DB->record_exists_select(
            'doomed_attempts',
            'doomedid = :doomedid AND userid = :userid AND timecreated > :since',
            ['doomedid' => $doomedid, 'userid' => $userid, 'since' => $now - self::MIN_INTERVAL]
        );
        if ($recent) {
            throw new \moodle_exception('submittoosoon', 'mod_doomed');
        }
        if ($DB->count_records('doomed_attempts', ['doomedid' => $doomedid, 'userid' => $userid]) >= self::MAX_ATTEMPTS) {
            throw new \moodle_exception('submittoomany', 'mod_doomed', '', self::MAX_ATTEMPTS);
        }
    }

    /**
     * Reject values that no real game can produce.
     *
     * Kills may exceed the level total: monsters spawned or resurrected
     * during play count as kills but not towards the total, so the original
     * game shows kill percentages over 100. Grading caps the ratio at 1.
     *
     * @param array $params validated parameters
     * @throws \invalid_parameter_exception
     */
    protected static function check_sanity(array $params): void {
        $fail = fn(string $why) => throw new \invalid_parameter_exception($why);

        if (!in_array($params['outcome'], [grading::OUTCOME_COMPLETED, grading::OUTCOME_DIED], true)) {
            $fail('outcome must be completed or died');
        }
        if (!wad::is_map_name(strtoupper($params['map']))) {
            $fail('map is not a map name');
        }
        if ($params['skill'] < 1 || $params['skill'] > 5) {
            $fail('skill must be 1 to 5');
        }
        foreach (['kills', 'totalkills', 'items', 'totalitems', 'secrets', 'totalsecrets'] as $field) {
            if ($params[$field] < 0 || $params[$field] > self::MAX_COUNT) {
                $fail("$field is out of range");
            }
        }
        if ($params['items'] > $params['totalitems']) {
            $fail('items exceed the level total');
        }
        if ($params['secrets'] > $params['totalsecrets']) {
            $fail('secrets exceed the level total');
        }
        if (
            $params['timetics'] < 0 || $params['timetics'] > self::MAX_TICS
                || $params['partics'] < 0 || $params['partics'] > self::MAX_TICS
        ) {
            $fail('time is out of range');
        }
        if ($params['outcome'] === grading::OUTCOME_COMPLETED && $params['timetics'] <= 0) {
            $fail('a completed level must take some time');
        }
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid' => new external_value(PARAM_INT, 'New attempt id'),
            'counted' => new external_value(PARAM_BOOL, 'Whether the attempt counts towards grade and completion'),
            'grade' => new external_value(
                PARAM_FLOAT,
                'Grade this attempt earns; null if none',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'maxgrade' => new external_value(
                PARAM_FLOAT,
                'Maximum grade; null if ungraded',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
        ]);
    }
}
