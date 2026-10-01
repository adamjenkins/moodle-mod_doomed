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

namespace mod_doomed\completion;

use core_completion\activity_custom_completion;
use mod_doomed\local\grading;

/**
 * Custom completion rules for mod_doomed.
 *
 * - completionmap: the student completed the starting map (at the activity's
 *   skill level or harder).
 * - completionmingrade: the student's grade is at least the given value.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetch the completion state of a rule.
     *
     * @param string $rule rule name
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);
        $doomed = $DB->get_record('doomed', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ($rule === 'completionmap') {
            $attempts = $DB->get_records('doomed_attempts', [
                'doomedid' => $doomed->id,
                'userid' => $this->userid,
                'outcome' => grading::OUTCOME_COMPLETED,
            ]);
            foreach ($attempts as $attempt) {
                if (grading::attempt_counts($doomed, $attempt)) {
                    return COMPLETION_COMPLETE;
                }
            }
            return COMPLETION_INCOMPLETE;
        }

        // Rule completionmingrade.
        $grade = grading::user_grade($doomed, $this->userid);
        return $grade !== null && $grade >= (float) $doomed->completionmingrade
            ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Rules this module defines.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completionmap', 'completionmingrade'];
    }

    /**
     * Human-readable descriptions of the active rules.
     *
     * @return array rule => description
     */
    public function get_custom_rule_descriptions(): array {
        $startmap = $this->cm->customdata['startmap'] ?? '';
        $mingrade = $this->cm->customdata['customcompletionrules']['completionmingrade'] ?? 0;
        return [
            'completionmap' => get_string('completiondetail:map', 'mod_doomed', s($startmap)),
            'completionmingrade' => get_string('completiondetail:mingrade', 'mod_doomed', format_float((float) $mingrade, -1)),
        ];
    }

    /**
     * Order in which the rules are shown.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionmap', 'completionmingrade'];
    }
}
