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

/**
 * Restore structure step for mod_doomed.
 *
 * @package    mod_doomed
 * @category   backup
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_doomed\external\submit_result;
use mod_doomed\local\grading;
use mod_doomed\local\levels;
use mod_doomed\local\wad;
use mod_doomed\local\wads;

/**
 * Restores doomed.xml.
 *
 * A backup file is attacker input: anyone allowed to restore can hand-edit
 * it. Every value that feeds grading or the player is therefore forced into
 * the range the activity form and the result web service accept, falling
 * back to safe defaults instead of failing the whole restore.
 *
 * No time field is shifted by the course start date offset. The activity has
 * no schedule dates; timecreated and timemodified record when the activity
 * was made and last edited, and an attempt's timecreated records when the
 * game was actually played. Shifting those would invent history, and the
 * relative order the "last attempt" grading method depends on is the same
 * either way.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_doomed_activity_structure_step extends restore_activity_structure_step {
    /** @var int Upper bound for a percentage setting (weights and time bonus). */
    protected const MAX_PERCENT = 100;

    /**
     * Paths to process.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [new restore_path_element('doomed', '/activity/doomed')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('doomed_attempt', '/activity/doomed/attempts/attempt');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity record.
     *
     * @param array|stdClass $data backed up activity
     * @return void
     */
    protected function process_doomed($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        self::clean_settings($data);

        $newid = $DB->insert_record('doomed', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore one attempt; skipped when its user is not part of the restore.
     *
     * @param array|stdClass $data backed up attempt
     * @return void
     */
    protected function process_doomed_attempt($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }
        $data->userid = $userid;
        $data->doomedid = $this->get_new_parentid('doomed');
        self::clean_attempt($data);

        $newid = $DB->insert_record('doomed_attempts', $data);
        $this->set_mapping('doomed_attempt', $oldid, $newid);
    }

    /**
     * Restore the files, then enforce the uploaded-IWAD policy.
     *
     * A backup is attacker-controllable input, so an uploaded IWAD is kept
     * only when the site allows uploaded IWADs, the user restoring holds
     * mod/doomed:uploadiwad in the new activity, and the file really is an
     * IWAD. Otherwise its files are removed and the activity falls back to
     * the bundled Freedoom Phase 1, which keeps it playable; a starting map
     * Freedoom does not have is then reset to E1M1.
     *
     * @return void
     */
    protected function after_execute() {
        global $DB;

        $this->add_related_files('mod_doomed', 'intro', null);
        $this->add_related_files('mod_doomed', wads::AREA_IWAD, null);
        $this->add_related_files('mod_doomed', wads::AREA_PWAD, null);
        if ($this->get_setting_value('userinfo')) {
            // Saved games: item id = owner's user id, remapped through the 'user' mapping; files of
            // users not restored have no mapping and are skipped. add_related_files() cannot be used:
            // it requires the mapping's parentitemid to be this activity's context, which a user
            // mapping's never is, so call the pool directly with that match skipped.
            $results = restore_dbops::send_files_to_pool(
                $this->get_basepath(),
                $this->get_restoreid(),
                'mod_doomed',
                \mod_doomed\local\saves::AREA,
                $this->task->get_old_contextid(),
                $this->task->get_userid(),
                'user',
                null,
                null,
                true
            );
            foreach ($results as $result) {
                $this->log($result->message, $result->level);
            }
        }

        $doomedid = $this->task->get_activityid();
        $context = context_module::instance($this->task->get_moduleid());
        $doomed = $DB->get_record('doomed', ['id' => $doomedid], 'id, iwadsource, startmap, levels', MUST_EXIST);
        if ($doomed->iwadsource === wads::SOURCE_UPLOAD && !self::uploaded_iwad_allowed($context, $this->task->get_userid())) {
            get_file_storage()->delete_area_files($context->id, 'mod_doomed', wads::AREA_IWAD);
            $DB->set_field('doomed', 'iwadsource', wads::SOURCE_FREEDOOM1, ['id' => $doomedid]);
            $doomed->iwadsource = wads::SOURCE_FREEDOOM1;
        }
        if ($doomed->iwadsource === wads::SOURCE_FREEDOOM1) {
            $list = self::playable_levels($doomed, $context);
            $DB->set_field('doomed', 'levels', levels::format($list), ['id' => $doomedid]);
            $DB->set_field('doomed', 'startmap', $list[0], ['id' => $doomedid]);
        }
    }

    /**
     * Whether a restored activity may keep its uploaded IWAD.
     *
     * @param context_module $context the restored activity
     * @param int $userid the user performing the restore
     * @return bool
     */
    public static function uploaded_iwad_allowed(context_module $context, int $userid): bool {
        if (
            !get_config('mod_doomed', 'allowiwadupload')
                || !has_capability('mod/doomed:uploadiwad', $context, $userid)
        ) {
            return false;
        }
        $file = wads::get_area_file($context, wads::AREA_IWAD);
        if (!$file) {
            return false;
        }
        try {
            return wad::from_stored_file($file)->kind === 'IWAD';
        } catch (\moodle_exception $e) {
            return false;
        }
    }

    /**
     * The restored levels the activity's WADs contain, in order; if none, the first map they have.
     *
     * @param stdClass $doomed activity record (iwadsource, startmap, levels)
     * @param context_module $context the restored activity
     * @return string[] at least one map name
     */
    protected static function playable_levels(stdClass $doomed, context_module $context): array {
        $list = levels::from_record($doomed);
        try {
            $maps = wads::available_maps($doomed, $context);
        } catch (\moodle_exception $e) {
            $maps = [];
        }
        if (!$maps) {
            return $list;
        }
        $playable = array_values(array_intersect($list, $maps));
        return $playable ?: [$maps[0]];
    }

    /**
     * Recompute grades once the gradebook has been restored.
     *
     * Grades are derived from attempts and settings, and a restored setting
     * may have been clamped, so the gradebook is brought in line with what
     * grading computes rather than trusting the backed up grades.
     *
     * @return void
     */
    protected function after_restore() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/doomed/lib.php');

        $doomed = $DB->get_record('doomed', ['id' => $this->task->get_activityid()]);
        if ($doomed) {
            doomed_update_grades($doomed);
        }
    }

    /**
     * The largest maximum grade the activity form offers ($CFG->gradepointmax).
     *
     * @return int
     */
    protected static function max_grade_points(): int {
        global $CFG;
        return max(1, (int) ($CFG->gradepointmax ?? 100));
    }

    /**
     * Force restored settings into the ranges the activity form accepts.
     *
     * @param stdClass $data activity record, changed in place
     * @return void
     */
    public static function clean_settings(stdClass $data): void {
        $data->name = clean_param((string) ($data->name ?? ''), PARAM_TEXT);
        $data->introformat = (int) ($data->introformat ?? FORMAT_HTML);

        $data->iwadsource = in_array($data->iwadsource ?? '', [wads::SOURCE_FREEDOOM1, wads::SOURCE_UPLOAD], true)
            ? $data->iwadsource : wads::SOURCE_FREEDOOM1;

        $startmap = strtoupper(trim((string) ($data->startmap ?? '')));
        $startmap = wad::is_map_name($startmap) ? $startmap : 'E1M1';
        // Backups made before level lists existed carry only startmap.
        $list = array_values(array_unique(array_filter(
            levels::parse((string) ($data->levels ?? $startmap)),
            [wad::class, 'is_map_name']
        )));
        $list = array_slice($list ?: [$startmap], 0, levels::MAX_LEVELS);
        $data->levels = levels::format($list);
        $data->startmap = $list[0];
        $data->freeplay = empty($data->freeplay) ? 0 : 1;

        $data->skill = self::in_range($data->skill ?? null, 1, 5) ? (int) $data->skill : 3;

        $modes = [grading::MODE_NONE, grading::MODE_COMPLETION, grading::MODE_PERCENTAGE];
        $data->grademode = in_array((int) ($data->grademode ?? 0), $modes, true) ? (int) $data->grademode : grading::MODE_NONE;

        $methods = [grading::METHOD_HIGHEST, grading::METHOD_LAST];
        $data->grademethod = in_array((int) ($data->grademethod ?? 0), $methods, true)
            ? (int) $data->grademethod : grading::METHOD_HIGHEST;

        $data->grade = min(max(0, (int) ($data->grade ?? 0)), self::max_grade_points());
        if ($data->grademode !== grading::MODE_NONE && $data->grade <= 0) {
            // A graded mode with no points cannot grade anything.
            $data->grademode = grading::MODE_NONE;
        }

        $sum = 0;
        foreach (['weightkills', 'weightitems', 'weightsecrets'] as $field) {
            $data->$field = self::clamp($data->$field ?? 1, 0, self::MAX_PERCENT);
            $sum += $data->$field;
        }
        if ($sum === 0) {
            // The form requires at least one weight; fall back to equal weights.
            $data->weightkills = $data->weightitems = $data->weightsecrets = 1;
        }
        $data->timebonus = self::clamp($data->timebonus ?? 0, 0, self::MAX_PERCENT);

        $data->completionmap = empty($data->completionmap) ? 0 : 1;
        $mingrade = (float) ($data->completionmingrade ?? 0);
        if (!is_finite($mingrade) || $mingrade < 0 || $data->grademode === grading::MODE_NONE) {
            $mingrade = 0;
        }
        $data->completionmingrade = min($mingrade, (float) $data->grade);

        $data->timecreated = max(0, (int) ($data->timecreated ?? 0));
        $data->timemodified = max(0, (int) ($data->timemodified ?? 0));
    }

    /**
     * Force a restored attempt into what the result web service accepts.
     *
     * An attempt that cannot be made valid is kept for the report but can
     * never earn a grade: its outcome becomes 'died'.
     *
     * @param stdClass $data attempt record, changed in place
     * @return void
     */
    public static function clean_attempt(stdClass $data): void {
        $countable = true;

        $outcome = (string) ($data->outcome ?? '');
        if (!in_array($outcome, [grading::OUTCOME_COMPLETED, grading::OUTCOME_DIED], true)) {
            $countable = false;
        }

        $map = strtoupper(clean_param((string) ($data->map ?? ''), PARAM_ALPHANUM));
        if (!wad::is_map_name($map)) {
            $countable = false;
            $map = substr($map, 0, 8);
            if ($map === '') {
                $map = 'E1M1';
            }
        }
        $data->map = $map;

        if (self::in_range($data->skill ?? null, 1, 5)) {
            $data->skill = (int) $data->skill;
        } else {
            $countable = false;
            $data->skill = 1;
        }

        foreach (['kills', 'totalkills', 'items', 'totalitems', 'secrets', 'totalsecrets'] as $field) {
            $data->$field = self::clamp($data->$field ?? 0, 0, submit_result::MAX_COUNT);
        }
        // Kills may legitimately exceed the total; items and secrets may not.
        $data->items = min($data->items, $data->totalitems);
        $data->secrets = min($data->secrets, $data->totalsecrets);

        $maxseconds = intdiv(submit_result::MAX_TICS, submit_result::TICRATE);
        $data->leveltime = self::clamp($data->leveltime ?? 0, 0, $maxseconds);
        $data->partime = self::clamp($data->partime ?? 0, 0, $maxseconds);

        $data->outcome = $countable ? $outcome : grading::OUTCOME_DIED;
        $data->timecreated = max(0, (int) ($data->timecreated ?? 0));
    }

    /**
     * Whether a value is an integer within a range.
     *
     * @param mixed $value raw value
     * @param int $min lowest allowed
     * @param int $max highest allowed
     * @return bool
     */
    protected static function in_range($value, int $min, int $max): bool {
        if (!is_numeric($value) || (string) (int) $value !== trim((string) $value)) {
            return false;
        }
        return (int) $value >= $min && (int) $value <= $max;
    }

    /**
     * An integer forced into a range; non-numeric input becomes the minimum.
     *
     * @param mixed $value raw value
     * @param int $min lowest allowed
     * @param int $max highest allowed
     * @return int
     */
    protected static function clamp($value, int $min, int $max): int {
        $value = is_numeric($value) ? (int) $value : $min;
        return max($min, min($max, $value));
    }
}
