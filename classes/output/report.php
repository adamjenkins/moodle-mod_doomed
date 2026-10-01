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

namespace mod_doomed\output;

use cm_info;
use context_module;
use core_user\fields;
use mod_doomed\local\grading;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Teacher report: every student's attempts in one activity.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report implements renderable, templatable {
    /** @var int Attempts listed per student; the grade still uses all of them. */
    public const MAX_ROWS = 25;

    /**
     * Constructor.
     *
     * @param stdClass $doomed activity record
     * @param cm_info $cm course module
     * @param context_module $context activity context
     * @param int $groupid group to show, 0 for all
     */
    public function __construct(
        /** @var stdClass activity record */
        protected stdClass $doomed,
        /** @var cm_info course module */
        protected cm_info $cm,
        /** @var context_module activity context */
        protected context_module $context,
        /** @var int group filter, 0 for all */
        protected int $groupid = 0,
    ) {
    }

    /**
     * Attempts of the users visible in the current group, grouped by user.
     *
     * @return array [users keyed by id, attempts keyed by user id]
     */
    protected function load(): array {
        global $DB;

        $userfields = fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $params = ['doomedid' => $this->doomed->id];
        $groupjoin = '';
        if ($this->groupid) {
            $groupjoin = 'JOIN {groups_members} gm ON gm.userid = a.userid AND gm.groupid = :groupid';
            $params['groupid'] = $this->groupid;
        }
        $sql = "SELECT a.*, u.id AS uid, $userfields
                  FROM {doomed_attempts} a
                  JOIN {user} u ON u.id = a.userid AND u.deleted = 0
                  $groupjoin
                 WHERE a.doomedid = :doomedid
              ORDER BY a.timecreated DESC, a.id DESC";
        $users = [];
        $attempts = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            if (!isset($users[$row->userid])) {
                $user = new stdClass();
                $user->id = $row->uid;
                foreach (fields::get_name_fields() as $field) {
                    $user->$field = $row->$field ?? '';
                }
                $users[$row->userid] = $user;
            }
            $attempts[$row->userid][] = $row;
        }
        return [$users, $attempts];
    }

    /**
     * Export data for the report template.
     *
     * @param renderer_base $output renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        [$users, $attempts] = $this->load();
        $graded = grading::is_graded($this->doomed);
        $students = [];
        foreach ($users as $userid => $user) {
            $grade = grading::aggregate($this->doomed, $attempts[$userid]);
            $rows = [];
            foreach (array_slice($attempts[$userid], 0, self::MAX_ROWS) as $attempt) {
                $attemptgrade = grading::attempt_grade($this->doomed, $attempt);
                $rows[] = [
                    'date' => userdate($attempt->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
                    'outcome' => get_string('outcome_' . $attempt->outcome, 'mod_doomed'),
                    'completed' => $attempt->outcome === grading::OUTCOME_COMPLETED,
                    'map' => $attempt->map,
                    'skill' => (int) $attempt->skill,
                    'kills' => self::ratio($attempt->kills, $attempt->totalkills, $attempt->outcome),
                    'items' => self::ratio($attempt->items, $attempt->totalitems, $attempt->outcome),
                    'secrets' => self::ratio($attempt->secrets, $attempt->totalsecrets, $attempt->outcome),
                    'time' => self::duration((int) $attempt->leveltime),
                    'par' => (int) $attempt->partime > 0 ? self::duration((int) $attempt->partime) : '',
                    'counted' => grading::attempt_counts($this->doomed, $attempt),
                    'grade' => $attemptgrade === null ? '' : format_float($attemptgrade, 2, true, true),
                ];
            }
            $total = count($attempts[$userid]);
            $gradetext = $grade === null ? get_string('nograde', 'mod_doomed') : format_float($grade, 2, true, true);
            $students[] = [
                'fullname' => fullname($user, has_capability('moodle/site:viewfullnames', $this->context)),
                'profileurl' => (new \moodle_url('/user/view.php', ['id' => $userid, 'course' => $this->cm->course]))->out(false),
                'summary' => $graded
                    ? get_string('studentsummary', 'mod_doomed', (object) ['attempts' => $total, 'grade' => $gradetext])
                    : get_string('studentsummaryungraded', 'mod_doomed', $total),
                'truncated' => $total > self::MAX_ROWS
                    ? get_string('showinglatest', 'mod_doomed', (object) ['shown' => self::MAX_ROWS, 'total' => $total])
                    : '',
                'attempts' => $rows,
            ];
        }
        usort($students, fn($a, $b) => \core_text::strtolower($a['fullname']) <=> \core_text::strtolower($b['fullname']));
        return [
            'graded' => $graded,
            'maxgrade' => $graded ? format_float((float) $this->doomed->grade, 2, true, true) : '',
            'startmap' => $this->doomed->startmap,
            'hasstudents' => !empty($students),
            'students' => $students,
        ];
    }

    /**
     * "got / total" for display; totals are unknown for deaths before the level loaded.
     *
     * @param int|string $got count achieved
     * @param int|string $total level total
     * @param string $outcome attempt outcome
     * @return string
     */
    protected static function ratio($got, $total, string $outcome): string {
        return (int) $total > 0 || $outcome === grading::OUTCOME_COMPLETED
            ? (int) $got . ' / ' . (int) $total : (string) (int) $got;
    }

    /**
     * Minutes and seconds, as the game shows them.
     *
     * @param int $seconds duration
     * @return string m:ss
     */
    protected static function duration(int $seconds): string {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
