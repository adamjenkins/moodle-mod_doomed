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

namespace mod_doomed;

use advanced_testcase;
use mod_doomed\local\grading;
use PHPUnit\Framework\Attributes\CoversFunction;
use stdClass;

/**
 * Tests for the module's lib.php callbacks.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('doomed_supports')]
#[CoversFunction('doomed_add_instance')]
#[CoversFunction('doomed_update_instance')]
#[CoversFunction('doomed_delete_instance')]
#[CoversFunction('doomed_grade_item_update')]
#[CoversFunction('doomed_update_grades')]
#[CoversFunction('doomed_reset_userdata')]
#[CoversFunction('doomed_get_coursemodule_info')]
#[CoversFunction('mod_doomed_get_completion_active_rule_descriptions')]
#[CoversFunction('doomed_pluginfile')]
final class lib_test extends advanced_testcase {
    /** @var stdClass course */
    private stdClass $course;

    /**
     * Load the libraries and make a course with completion.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/doomed/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
    }

    /**
     * The grade item of an activity, or false.
     *
     * @param stdClass $doomed activity
     * @return stdClass|false
     */
    private function grade_item(stdClass $doomed) {
        global $DB;
        return $DB->get_record('grade_items', [
            'courseid' => $this->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'doomed',
            'iteminstance' => $doomed->id,
            'itemnumber' => 0,
        ]);
    }

    /**
     * Store an attempt.
     *
     * @param stdClass $doomed activity
     * @param int $userid user
     * @param array $fields overrides
     * @return int attempt id
     */
    private function add_attempt(stdClass $doomed, int $userid, array $fields = []): int {
        global $DB;
        return $DB->insert_record('doomed_attempts', (object) ($fields + [
            'doomedid' => $doomed->id,
            'userid' => $userid,
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
     * The gradebook grade of a user, or null.
     *
     * @param stdClass $doomed activity
     * @param int $userid user
     * @return float|null
     */
    private function gradebook_grade(stdClass $doomed, int $userid): ?float {
        $grades = grade_get_grades($this->course->id, 'mod', 'doomed', $doomed->id, $userid);
        $grade = $grades->items[0]->grades[$userid]->grade ?? null;
        return $grade === null ? null : (float) $grade;
    }

    /**
     * Supported features.
     */
    public function test_supports(): void {
        $this->assertTrue(doomed_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(doomed_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(doomed_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(doomed_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertTrue(doomed_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertTrue(doomed_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(doomed_supports(FEATURE_GROUPS));
        $this->assertTrue(doomed_supports(FEATURE_GROUPINGS));
        $this->assertFalse(doomed_supports(FEATURE_GRADE_OUTCOMES));
        $this->assertSame(MOD_PURPOSE_INTERACTIVECONTENT, doomed_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(doomed_supports('not_a_real_feature'));
    }

    /**
     * Adding a graded instance normalises the start map and creates a value grade item.
     */
    public function test_add_instance_graded(): void {
        global $DB;
        $doomed = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'name' => 'Knee-deep',
            'startmap' => ' e1m3 ',
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 40,
        ]);
        $record = $DB->get_record('doomed', ['id' => $doomed->id], '*', MUST_EXIST);
        $this->assertSame('E1M3', $record->startmap);
        $this->assertGreaterThan(0, (int) $record->timecreated);
        $this->assertEquals($record->timecreated, $record->timemodified);

        $item = $this->grade_item($doomed);
        $this->assertNotFalse($item);
        $this->assertEquals(GRADE_TYPE_VALUE, $item->gradetype);
        $this->assertEqualsWithDelta(40.0, (float) $item->grademax, 1e-9);
        $this->assertEqualsWithDelta(0.0, (float) $item->grademin, 1e-9);
        $this->assertSame('Knee-deep', $item->itemname);
    }

    /**
     * An ungraded instance has no grade item.
     */
    public function test_add_instance_ungraded(): void {
        $doomed = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'grademode' => grading::MODE_NONE,
        ]);
        $this->assertFalse($this->grade_item($doomed));
    }

    /**
     * Updating an instance changes the grade item; switching to ungraded makes it type none.
     */
    public function test_update_instance_grade_item(): void {
        global $DB;
        $doomed = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 40,
        ]);
        $data = $DB->get_record('doomed', ['id' => $doomed->id]);
        $data->instance = $doomed->id;
        $data->coursemodule = $doomed->cmid;
        $data->grade = 80;
        $data->startmap = 'e1m2';
        $this->assertTrue(doomed_update_instance(clone $data));
        $this->assertSame('E1M2', $DB->get_field('doomed', 'startmap', ['id' => $doomed->id]));
        $this->assertEqualsWithDelta(80.0, (float) $this->grade_item($doomed)->grademax, 1e-9);

        $data->grademode = grading::MODE_NONE;
        doomed_update_instance(clone $data);
        $this->assertEquals(GRADE_TYPE_NONE, $this->grade_item($doomed)->gradetype);
    }

    /**
     * A settings change regrades every user (highest -> last).
     */
    public function test_update_grades_after_settings_change(): void {
        global $DB;
        $doomed = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 50,
            'weightitems' => 0,
            'weightsecrets' => 0,
            'grademethod' => grading::METHOD_HIGHEST,
        ]);
        $u1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $u2 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        // User 1: 8/10 = 40 points first, 4/10 = 20 points later.
        $this->add_attempt($doomed, $u1->id, ['kills' => 8, 'timecreated' => 100]);
        $this->add_attempt($doomed, $u1->id, ['kills' => 4, 'timecreated' => 200]);
        // User 2: one attempt, 10/10 = 50 points.
        $this->add_attempt($doomed, $u2->id, ['kills' => 10, 'timecreated' => 150]);

        $record = $DB->get_record('doomed', ['id' => $doomed->id]);
        doomed_update_grades($record);
        // Highest: user 1 keeps 40.
        $this->assertEqualsWithDelta(40.0, $this->gradebook_grade($doomed, $u1->id), 1e-9);
        $this->assertEqualsWithDelta(50.0, $this->gradebook_grade($doomed, $u2->id), 1e-9);

        // Switch to last: user 1 drops to 20; user 2 unchanged. Update regrades by itself.
        $record->instance = $record->id;
        $record->coursemodule = $doomed->cmid;
        $record->grademethod = grading::METHOD_LAST;
        doomed_update_instance(clone $record);
        $this->assertEqualsWithDelta(20.0, $this->gradebook_grade($doomed, $u1->id), 1e-9);
        $this->assertEqualsWithDelta(50.0, $this->gradebook_grade($doomed, $u2->id), 1e-9);

        // Raise the maximum to 100: 4/10 = 40, 10/10 = 100.
        $record->grade = 100;
        doomed_update_instance(clone $record);
        $this->assertEqualsWithDelta(40.0, $this->gradebook_grade($doomed, $u1->id), 1e-9);
        $this->assertEqualsWithDelta(100.0, $this->gradebook_grade($doomed, $u2->id), 1e-9);

        // Move the start map: no attempt counts any more and the old grades are removed.
        $record->startmap = 'E1M2';
        doomed_update_instance(clone $record);
        $this->assertNull($this->gradebook_grade($doomed, $u1->id));
        $this->assertNull($this->gradebook_grade($doomed, $u2->id));
    }

    /**
     * Deleting an instance removes its attempts and grade item, and leaves others alone.
     */
    public function test_delete_instance(): void {
        global $DB;
        $params = ['course' => $this->course->id, 'grademode' => grading::MODE_PERCENTAGE];
        $doomed = $this->getDataGenerator()->create_module('doomed', $params);
        $keep = $this->getDataGenerator()->create_module('doomed', $params);
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->add_attempt($doomed, $user->id);
        $this->add_attempt($doomed, $user->id);
        $this->add_attempt($keep, $user->id);
        $this->assertNotFalse($this->grade_item($doomed));

        $this->assertTrue(doomed_delete_instance($doomed->id));
        $this->assertFalse($DB->record_exists('doomed', ['id' => $doomed->id]));
        $this->assertSame(0, $DB->count_records('doomed_attempts', ['doomedid' => $doomed->id]));
        $this->assertFalse($this->grade_item($doomed));

        $this->assertSame(1, $DB->count_records('doomed_attempts', ['doomedid' => $keep->id]));
        $this->assertNotFalse($this->grade_item($keep));

        $this->assertFalse(doomed_delete_instance($doomed->id));
    }

    /**
     * Course reset deletes the attempts of every activity in the course and reports it.
     */
    public function test_reset_userdata(): void {
        global $DB;
        $params = ['course' => $this->course->id, 'grademode' => grading::MODE_COMPLETION];
        $doomed1 = $this->getDataGenerator()->create_module('doomed', $params);
        $doomed2 = $this->getDataGenerator()->create_module('doomed', $params);
        $othercourse = $this->getDataGenerator()->create_course();
        $elsewhere = $this->getDataGenerator()->create_module('doomed', ['course' => $othercourse->id] + $params);
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->add_attempt($doomed1, $user->id);
        $this->add_attempt($doomed2, $user->id);
        $this->add_attempt($elsewhere, $user->id);
        doomed_update_grades($DB->get_record('doomed', ['id' => $doomed1->id]));
        $this->assertEqualsWithDelta(100.0, $this->gradebook_grade($doomed1, $user->id), 1e-9);

        // Nothing ticked: nothing happens.
        $status = doomed_reset_userdata((object) ['courseid' => $this->course->id]);
        $this->assertSame([], $status);
        $this->assertSame(3, $DB->count_records('doomed_attempts'));

        $status = doomed_reset_userdata((object) ['courseid' => $this->course->id, 'reset_doomed_attempts' => 1]);
        $this->assertSame([[
            'component' => get_string('modulenameplural', 'mod_doomed'),
            'item' => get_string('resetattempts', 'mod_doomed'),
            'error' => false,
        ]], $status);
        $this->assertSame(0, $DB->count_records('doomed_attempts', ['doomedid' => $doomed1->id]));
        $this->assertSame(0, $DB->count_records('doomed_attempts', ['doomedid' => $doomed2->id]));
        $this->assertSame(1, $DB->count_records('doomed_attempts', ['doomedid' => $elsewhere->id]));
        // The gradebook grades go with the attempts.
        $this->assertNull($this->gradebook_grade($doomed1, $user->id));
    }

    /**
     * Course module info carries the start map, and the completion rules only under automatic completion.
     */
    public function test_get_coursemodule_info(): void {
        global $DB;
        $auto = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'name' => 'Auto',
            'startmap' => 'MAP07',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmap' => 1,
            'completionmingrade' => 12.5,
        ]);
        $manual = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'startmap' => 'E1M1',
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionmap' => 1,
        ]);

        $info = doomed_get_coursemodule_info($DB->get_record('course_modules', ['id' => $auto->cmid]));
        $this->assertSame('Auto', $info->name);
        $this->assertSame('MAP07', $info->customdata['startmap']);
        $this->assertSame(['completionmap' => 1, 'completionmingrade' => 12.5], $info->customdata['customcompletionrules']);

        $info = doomed_get_coursemodule_info($DB->get_record('course_modules', ['id' => $manual->cmid]));
        $this->assertSame('E1M1', $info->customdata['startmap']);
        $this->assertArrayNotHasKey('customcompletionrules', $info->customdata);

        $cm = $DB->get_record('course_modules', ['id' => $auto->cmid]);
        $cm->instance = -1;
        $this->assertFalse(doomed_get_coursemodule_info($cm));
    }

    /**
     * Active rule descriptions list only the rules that are switched on.
     */
    public function test_active_rule_descriptions(): void {
        $both = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'startmap' => 'E3M2',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmap' => 1,
            'completionmingrade' => 30,
        ]);
        $maponly = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmap' => 1,
            'completionmingrade' => 0,
        ]);
        $gradeonly = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionmap' => 0,
            'completionmingrade' => 7.25,
        ]);
        $manual = $this->getDataGenerator()->create_module('doomed', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionmap' => 1,
        ]);
        $modinfo = get_fast_modinfo($this->course->id);

        $this->assertSame(
            ['Complete map E3M2', 'Achieve a grade of at least 30'],
            mod_doomed_get_completion_active_rule_descriptions($modinfo->get_cm($both->cmid))
        );
        $this->assertSame(
            ['Complete map E1M1'],
            mod_doomed_get_completion_active_rule_descriptions($modinfo->get_cm($maponly->cmid))
        );
        $this->assertSame(
            ['Achieve a grade of at least 7.25'],
            mod_doomed_get_completion_active_rule_descriptions($modinfo->get_cm($gradeonly->cmid))
        );
        $this->assertSame([], mod_doomed_get_completion_active_rule_descriptions($modinfo->get_cm($manual->cmid)));
    }

    /**
     * pluginfile refuses anything but the activity's own WAD areas, item 0, existing files and viewers.
     *
     * The success path ends in send_stored_file(), which stops the script, so
     * only the refusals are exercised here.
     */
    public function test_pluginfile_refusals(): void {
        global $DB;
        $this->setAdminUser();
        $module = $this->getDataGenerator()->create_module('doomed', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_id('doomed', $module->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_doomed',
            'filearea' => 'pwad', 'itemid' => 0, 'filepath' => '/', 'filename' => 'x.wad'], 'PWAD');

        $coursecontext = \context_course::instance($this->course->id);
        $this->assertFalse(doomed_pluginfile($this->course, $cm, $coursecontext, 'pwad', [0, 'x.wad'], true));
        $this->assertFalse(doomed_pluginfile($this->course, $cm, $context, 'intro', [0, 'x.wad'], true));
        $this->assertFalse(doomed_pluginfile($this->course, $cm, $context, 'pwad', [1, 'x.wad'], true));
        $this->assertFalse(doomed_pluginfile($this->course, $cm, $context, 'pwad', [0, 'missing.wad'], true));

        // An enrolled student without mod/doomed:view is refused before any file is looked up. With the
        // capability prohibited the module is not visible to them, so require_course_login() refuses first
        // (by redirecting, which PHPUnit reports as a moodle_exception).
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/doomed:view', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($student);
        $this->assertFalse(has_capability('mod/doomed:view', $context));
        $this->expectException(\moodle_exception::class);
        doomed_pluginfile($this->course, $cm, $context, 'pwad', [0, 'x.wad'], true);
    }
}
