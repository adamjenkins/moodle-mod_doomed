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

use mod_doomed\local\grading;
use mod_doomed\local\wads;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_plan_builder.class.php');
require_once($CFG->dirroot . '/mod/doomed/backup/moodle2/backup_doomed_activity_task.class.php');
require_once($CFG->dirroot . '/mod/doomed/backup/moodle2/restore_doomed_activity_task.class.php');

/**
 * Backup and restore of Doomed activities through the real controllers.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_doomed_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_doomed_activity_structure_step::class)]
final class backup_restore_test extends \advanced_testcase {
    /** @var \stdClass The source course. */
    protected \stdClass $course;

    /** @var \stdClass The activity record. */
    protected \stdClass $doomed;

    /** @var \stdClass The student with two attempts. */
    protected \stdClass $student;

    /** @var string[] Settings fields that must come across unchanged. */
    protected const SETTINGS = [
        'name', 'intro', 'introformat', 'iwadsource', 'startmap', 'skill', 'grademode', 'grade',
        'weightkills', 'weightitems', 'weightsecrets', 'timebonus', 'grademethod', 'completionmap',
        'completionmingrade', 'timecreated', 'timemodified',
    ];

    /**
     * A graded activity with a PWAD and two attempts by one student.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['startdate' => mktime(0, 0, 0, 9, 1, 2026)]);
        $module = $generator->create_module('doomed', [
            'course' => $this->course->id,
            'name' => 'Exit room',
            'intro' => '<p>See <a href="' . $CFG->wwwroot . '/mod/doomed/index.php?id=' . $this->course->id
                . '">all games</a></p>',
            'introformat' => FORMAT_HTML,
            'startmap' => 'E1M1',
            'skill' => 3,
            'grademode' => grading::MODE_PERCENTAGE,
            'grade' => 50,
            'weightkills' => 2,
            'weightitems' => 1,
            'weightsecrets' => 0,
            'timebonus' => 10,
            'grademethod' => grading::METHOD_LAST,
            'completionmap' => 1,
            'completionmingrade' => 20,
        ]);
        $this->doomed = $DB->get_record('doomed', ['id' => $module->id], '*', MUST_EXIST);

        $context = \context_module::instance($module->cmid);
        get_file_storage()->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_doomed',
            'filearea' => wads::AREA_PWAD,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'exitroom_e1m1.wad',
        ], __DIR__ . '/fixtures/exitroom_e1m1.wad');

        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $base = [
            'doomedid' => $this->doomed->id,
            'userid' => $this->student->id,
            'map' => 'E1M1',
            'skill' => 3,
            'totalkills' => 10,
            'totalitems' => 4,
            'totalsecrets' => 1,
            'partime' => 30,
        ];
        $DB->insert_record('doomed_attempts', (object) ($base + [
            'outcome' => grading::OUTCOME_COMPLETED,
            'kills' => 10, 'items' => 4, 'secrets' => 1, 'leveltime' => 20,
            'timecreated' => 1700000000,
        ]));
        $DB->insert_record('doomed_attempts', (object) ($base + [
            'outcome' => grading::OUTCOME_COMPLETED,
            'kills' => 5, 'items' => 1, 'secrets' => 0, 'leveltime' => 90,
            'timecreated' => 1700000500,
        ]));
    }

    /**
     * Back the course up and restore it into a new course.
     *
     * @param bool $users include user data in backup and restore
     * @param int $shift seconds to move the new course's start date by
     * @param callable|null $edit function(string $xml): string rewriting doomed.xml before the restore
     * @return \stdClass the restored activity record
     */
    protected function backup_and_restore(bool $users, int $shift = 0, ?callable $edit = null): \stdClass {
        global $DB, $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($users);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $dirname = 'doomed_restore_' . (int) $users . '_' . $shift . ($edit ? '_edited' : '');
        $path = make_backup_temp_directory($dirname);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);
        if ($edit) {
            $xmlfiles = glob($path . '/activities/doomed_*/doomed.xml');
            $this->assertCount(1, $xmlfiles);
            file_put_contents($xmlfiles[0], $edit(file_get_contents($xmlfiles[0])));
        }

        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored_' . $dirname, $this->course->category);
        $rc = new \restore_controller(
            $dirname,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value($users);
        if ($shift) {
            $rc->get_plan()->get_setting('course_startdate')->set_value((int) $this->course->startdate + $shift);
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $DB->get_record('doomed', ['course' => $newcourseid], '*', MUST_EXIST);
    }

    /**
     * Attempts of an activity, oldest first, without the ids that differ between copies.
     *
     * @param int $doomedid activity id
     * @return array[]
     */
    protected function attempt_rows(int $doomedid): array {
        global $DB;
        $rows = [];
        foreach ($DB->get_records('doomed_attempts', ['doomedid' => $doomedid], 'timecreated, id') as $attempt) {
            $row = (array) $attempt;
            unset($row['id'], $row['doomedid']);
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * With user data: settings, the PWAD, both attempts and the computed grade come back.
     */
    public function test_restore_with_user_data(): void {
        global $CFG;

        $restored = $this->backup_and_restore(true);

        $this->assertNotEquals($this->doomed->id, $restored->id);
        foreach (self::SETTINGS as $field) {
            if ($field === 'intro') {
                continue;
            }
            $this->assertEquals($this->doomed->$field, $restored->$field, "setting {$field}");
        }
        $this->assertStringContainsString(
            $CFG->wwwroot . '/mod/doomed/index.php?id=' . $restored->course,
            $restored->intro,
            'the index link must point at the restored course'
        );

        $cm = get_coursemodule_from_instance('doomed', $restored->id, $restored->course, false, MUST_EXIST);
        $pwad = wads::get_area_file(\context_module::instance($cm->id), wads::AREA_PWAD);
        $this->assertNotNull($pwad, 'the PWAD must be restored');
        $this->assertSame('exitroom_e1m1.wad', $pwad->get_filename());
        $this->assertSame(sha1_file(__DIR__ . '/fixtures/exitroom_e1m1.wad'), $pwad->get_contenthash());

        $rows = $this->attempt_rows($restored->id);
        $this->assertCount(2, $rows);
        $this->assertEquals($this->attempt_rows($this->doomed->id), $rows);

        $original = grading::user_grade($this->doomed, $this->student->id);
        // Last attempt: kills 5/10 weight 2, items 1/4 weight 1, over par: (2 * 0.5 + 0.25) / 3 of 50.
        $this->assertEqualsWithDelta(50 * 1.25 / 3, $original, 0.0001);
        $this->assertEqualsWithDelta($original, grading::user_grade($restored, $this->student->id), 0.0001);

        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'doomed', 'iteminstance' => $restored->id,
            'courseid' => $restored->course]);
        $this->assertNotEmpty($item);
        $this->assertEqualsWithDelta(50, (float) $item->grademax, 0.0001);
        $grade = $item->get_grade($this->student->id, false);
        $this->assertEqualsWithDelta($original, (float) $grade->rawgrade, 0.0001);
    }

    /**
     * Without user data: settings and the PWAD come back, attempts and grades do not.
     */
    public function test_restore_without_user_data(): void {
        global $DB;

        $restored = $this->backup_and_restore(false);

        foreach (self::SETTINGS as $field) {
            if ($field === 'intro') {
                continue;
            }
            $this->assertEquals($this->doomed->$field, $restored->$field, "setting {$field}");
        }
        $cm = get_coursemodule_from_instance('doomed', $restored->id, $restored->course, false, MUST_EXIST);
        $this->assertNotNull(wads::get_area_file(\context_module::instance($cm->id), wads::AREA_PWAD));

        $this->assertSame(0, $DB->count_records('doomed_attempts', ['doomedid' => $restored->id]));
        $this->assertSame(2, $DB->count_records('doomed_attempts', ['doomedid' => $this->doomed->id]));
        $this->assertNull(grading::user_grade($restored, $this->student->id));
    }

    /**
     * Moving the course start date shifts nothing: there are no schedule dates and attempts are history.
     */
    public function test_restore_with_date_shift_keeps_timestamps(): void {
        $restored = $this->backup_and_restore(true, 30 * DAYSECS);

        $this->assertEquals($this->doomed->timecreated, $restored->timecreated);
        $this->assertEquals($this->doomed->timemodified, $restored->timemodified);
        $this->assertEquals($this->attempt_rows($this->doomed->id), $this->attempt_rows($restored->id));
    }

    /**
     * A hand-edited backup: impossible values are forced back to safe ones instead of failing the restore.
     */
    public function test_restore_crafted_backup(): void {
        global $DB;

        $restored = $this->backup_and_restore(true, 0, function (string $xml): string {
            $replacements = [
                '~<iwadsource>[^<]*</iwadsource>~' => '<iwadsource>upload</iwadsource>',
                '~<startmap>[^<]*</startmap>~' => '<startmap>../../x</startmap>',
                '~<grademode>[^<]*</grademode>~' => '<grademode>7</grademode>',
                '~<grademethod>[^<]*</grademethod>~' => '<grademethod>-1</grademethod>',
                '~<weightkills>[^<]*</weightkills>~' => '<weightkills>500</weightkills>',
                '~<weightitems>[^<]*</weightitems>~' => '<weightitems>-5</weightitems>',
                '~<timebonus>[^<]*</timebonus>~' => '<timebonus>1000</timebonus>',
                // The activity's skill comes before the attempts' skills, so limit 1 hits it.
                '~<skill>[^<]*</skill>~' => '<skill>9</skill>',
                '~<outcome>completed</outcome>~' => '<outcome>won</outcome>',
            ];
            foreach ($replacements as $pattern => $replacement) {
                $xml = preg_replace($pattern, $replacement, $xml, 1, $count);
                $this->assertSame(1, $count, "edit {$pattern} applied");
            }
            return $xml;
        });

        $this->assertSame(wads::SOURCE_FREEDOOM1, $restored->iwadsource, 'no uploaded IWAD came across');
        $this->assertSame('E1M1', $restored->startmap);
        $this->assertSame(3, (int) $restored->skill);
        $this->assertSame(grading::MODE_NONE, (int) $restored->grademode);
        $this->assertSame(grading::METHOD_HIGHEST, (int) $restored->grademethod);
        $this->assertSame(100, (int) $restored->weightkills);
        $this->assertSame(0, (int) $restored->weightitems);
        $this->assertSame(100, (int) $restored->timebonus);
        $this->assertEquals(0, (float) $restored->completionmingrade, 'no minimum grade on an ungraded activity');

        $outcomes = $DB->get_fieldset_select(
            'doomed_attempts',
            'outcome',
            'doomedid = ? ORDER BY timecreated',
            [$restored->id]
        );
        $this->assertSame([grading::OUTCOME_DIED, grading::OUTCOME_COMPLETED], $outcomes);
    }

    /**
     * Switch the fixture activity to an uploaded IWAD (MAP01 naming) and start on MAP01.
     *
     * The IWAD is the exit-room test map with an IWAD header, which is all
     * the plugin inspects.
     */
    protected function use_uploaded_iwad(): void {
        global $DB;
        $cm = get_coursemodule_from_instance('doomed', $this->doomed->id);
        $context = \context_module::instance($cm->id);
        $bytes = 'IWAD' . substr(file_get_contents(__DIR__ . '/fixtures/exitroom_map01.wad'), 4);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_doomed',
            'filearea' => wads::AREA_IWAD,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'doom2.wad',
        ], $bytes);
        $DB->update_record('doomed', (object) ['id' => $this->doomed->id, 'iwadsource' => wads::SOURCE_UPLOAD,
            'startmap' => 'MAP01']);
    }

    /**
     * An uploaded IWAD survives a restore by someone allowed to upload one.
     */
    public function test_restore_keeps_permitted_uploaded_iwad(): void {
        set_config('allowiwadupload', 1, 'mod_doomed');
        $this->use_uploaded_iwad();

        $restored = $this->backup_and_restore(false);

        $this->assertSame(wads::SOURCE_UPLOAD, $restored->iwadsource);
        $this->assertSame('MAP01', $restored->startmap);
        $cm = get_coursemodule_from_instance('doomed', $restored->id);
        $iwad = wads::get_area_file(\context_module::instance($cm->id), wads::AREA_IWAD);
        $this->assertNotNull($iwad);
        $this->assertSame('doom2.wad', $iwad->get_filename());
    }

    /**
     * With uploaded IWADs disabled on the site, a restore cannot bring one in:
     * the file is dropped, the activity uses Freedoom, and a starting map
     * Freedoom lacks is reset to its first map.
     */
    public function test_restore_drops_iwad_when_uploads_disabled(): void {
        $this->use_uploaded_iwad();
        set_config('allowiwadupload', 0, 'mod_doomed');

        $restored = $this->backup_and_restore(false);

        $this->assertSame(wads::SOURCE_FREEDOOM1, $restored->iwadsource);
        $this->assertSame('E1M1', $restored->startmap);
        $cm = get_coursemodule_from_instance('doomed', $restored->id);
        $this->assertNull(wads::get_area_file(\context_module::instance($cm->id), wads::AREA_IWAD));
    }

    /**
     * The upload policy check: capability of the restoring user, and the file must be an IWAD.
     */
    public function test_uploaded_iwad_allowed(): void {
        global $DB;
        set_config('allowiwadupload', 1, 'mod_doomed');
        $this->use_uploaded_iwad();
        $cm = get_coursemodule_from_instance('doomed', $this->doomed->id);
        $context = \context_module::instance($cm->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        $this->assertTrue(\restore_doomed_activity_structure_step::uploaded_iwad_allowed($context, $teacher->id));
        $this->assertFalse(\restore_doomed_activity_structure_step::uploaded_iwad_allowed($context, $this->student->id));

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('mod/doomed:uploadiwad', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('mod/doomed:uploadiwad', $context, $teacher->id));
        $this->assertFalse(\restore_doomed_activity_structure_step::uploaded_iwad_allowed($context, $teacher->id));

        // A PWAD in the IWAD area is refused even for an admin.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_doomed', wads::AREA_IWAD);
        $fs->create_file_from_pathname(
            ['contextid' => $context->id, 'component' => 'mod_doomed',
            'filearea' => wads::AREA_IWAD, 'itemid' => 0, 'filepath' => '/', 'filename' => 'pwad.wad'],
            __DIR__ . '/fixtures/exitroom_e1m1.wad'
        );
        $this->assertFalse(\restore_doomed_activity_structure_step::uploaded_iwad_allowed($context, get_admin()->id));
    }

    /**
     * Saved games are user data: restored with it (re-keyed to the user), left out without it.
     */
    public function test_saved_games_follow_user_data(): void {
        $cm = get_coursemodule_from_instance('doomed', $this->doomed->id);
        \mod_doomed\local\saves::store(
            \context_module::instance($cm->id),
            (int) $this->student->id,
            'doomsav4.dsg',
            'student save bytes'
        );

        $with = $this->backup_and_restore(true);
        $withcm = get_coursemodule_from_instance('doomed', $with->id);
        $restored = \mod_doomed\local\saves::get_user_saves(\context_module::instance($withcm->id), (int) $this->student->id);
        $this->assertArrayHasKey('doomsav4.dsg', $restored);
        $this->assertSame('student save bytes', $restored['doomsav4.dsg']->get_content());

        $without = $this->backup_and_restore(false);
        $withoutcm = get_coursemodule_from_instance('doomed', $without->id);
        $files = get_file_storage()->get_area_files(
            \context_module::instance($withoutcm->id)->id,
            'mod_doomed',
            \mod_doomed\local\saves::AREA,
            false,
            'id',
            false
        );
        $this->assertSame([], $files);
    }
}
