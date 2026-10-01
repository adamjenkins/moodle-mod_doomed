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
 * Library of interface functions and constants for mod_doomed.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_doomed\local\grading;
use mod_doomed\local\wads;

/**
 * Report which Moodle features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed true/false for a supported/unsupported feature, a value for purpose, null if unknown
 */
function doomed_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_INTERACTIVECONTENT;
        default:
            return null;
    }
}

/**
 * Normalise an activity record before it is saved.
 *
 * @param stdClass $data form or generator data; changed in place
 * @return void
 */
function doomed_process_form_data(stdClass $data): void {
    $data->startmap = strtoupper(trim($data->startmap ?? 'E1M1'));
    $data->timemodified = time();
}

/**
 * Save the IWAD/PWAD draft areas of a submitted form.
 *
 * @param stdClass $data form data with coursemodule, iwadfile and pwadfile
 * @return void
 */
function doomed_save_wad_files(stdClass $data): void {
    $context = context_module::instance($data->coursemodule);
    $options = wads::filemanager_options();
    if (isset($data->iwadfile)) {
        if ($data->iwadsource === wads::SOURCE_UPLOAD) {
            file_save_draft_area_files($data->iwadfile, $context->id, 'mod_doomed', wads::AREA_IWAD, 0, $options);
        } else {
            get_file_storage()->delete_area_files($context->id, 'mod_doomed', wads::AREA_IWAD);
        }
    }
    if (isset($data->pwadfile)) {
        file_save_draft_area_files($data->pwadfile, $context->id, 'mod_doomed', wads::AREA_PWAD, 0, $options);
    }
}

/**
 * Add a doomed instance.
 *
 * @param stdClass $data form data
 * @param mod_doomed_mod_form|null $mform the form
 * @return int new instance id
 */
function doomed_add_instance($data, $mform = null) {
    global $DB;

    doomed_process_form_data($data);
    $data->timecreated = $data->timemodified;
    $data->id = $DB->insert_record('doomed', $data);
    // Inside add_instance the course module's instance field is not set yet,
    // so everything that needs the context goes through $data->coursemodule.
    doomed_save_wad_files($data);

    $doomed = $DB->get_record('doomed', ['id' => $data->id]);
    $doomed->cmidnumber = $data->cmidnumber ?? '';
    doomed_grade_item_update($doomed);
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'doomed',
        $doomed->id,
        $data->completionexpected ?? null
    );
    return $data->id;
}

/**
 * Update a doomed instance.
 *
 * @param stdClass $data form data
 * @param mod_doomed_mod_form|null $mform the form
 * @return bool true
 */
function doomed_update_instance($data, $mform = null) {
    global $DB;

    doomed_process_form_data($data);
    $data->id = $data->instance;
    $DB->update_record('doomed', $data);
    doomed_save_wad_files($data);

    // Grades are computed from settings, so a settings change regrades everyone.
    $doomed = $DB->get_record('doomed', ['id' => $data->id]);
    $doomed->cmidnumber = $data->cmidnumber ?? '';
    doomed_update_grades($doomed);
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'doomed',
        $doomed->id,
        $data->completionexpected ?? null
    );
    return true;
}

/**
 * Delete a doomed instance and everything that belongs to it.
 *
 * @param int $id instance id
 * @return bool true on success, false if the instance does not exist
 */
function doomed_delete_instance($id) {
    global $DB;

    $doomed = $DB->get_record('doomed', ['id' => $id]);
    if (!$doomed) {
        return false;
    }
    doomed_grade_item_delete($doomed);
    $DB->delete_records('doomed_attempts', ['doomedid' => $id]);
    $DB->delete_records('doomed', ['id' => $id]);
    return true;
}

/**
 * Whether a scale is used by an activity: never, only point grades are offered.
 *
 * @param int $doomedid instance id
 * @param int $scaleid scale id
 * @return bool false
 */
function doomed_scale_used($doomedid, $scaleid) {
    return false;
}

/**
 * Whether a scale is used by any activity: never, only point grades are offered.
 *
 * @param int $scaleid scale id
 * @return bool false
 */
function doomed_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Create or update the grade item of an activity.
 *
 * @param stdClass $doomed activity record, optionally with cmidnumber
 * @param array|object|string|null $grades grades to write, 'reset' to reset, or null
 * @return int GRADE_UPDATE_OK or an error code
 */
function doomed_grade_item_update($doomed, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = ['itemname' => clean_param($doomed->name, PARAM_NOTAGS)];
    if (!empty($doomed->cmidnumber)) {
        $item['idnumber'] = $doomed->cmidnumber;
    }
    if (grading::is_graded($doomed)) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = (float) $doomed->grade;
        $item['grademin'] = 0;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/doomed', $doomed->course, 'mod', 'doomed', $doomed->id, 0, $grades, $item);
}

/**
 * Delete the grade item of an activity.
 *
 * @param stdClass $doomed activity record
 * @return int GRADE_UPDATE_OK or an error code
 */
function doomed_grade_item_delete($doomed) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/doomed', $doomed->course, 'mod', 'doomed', $doomed->id, 0, null, ['deleted' => 1]);
}

/**
 * Grades of one or all users, computed from their attempts.
 *
 * @param stdClass $doomed activity record
 * @param int $userid one user, or 0 for all
 * @return array userid => grade object
 */
function doomed_get_user_grades($doomed, $userid = 0) {
    return grading::gradebook_grades($doomed, (int) $userid);
}

/**
 * Push grades to the gradebook.
 *
 * @param stdClass $doomed activity record, optionally with cmidnumber
 * @param int $userid one user, or 0 for all
 * @param bool $nullifnone unused: users without counting attempts always get a null grade
 * @return void
 */
function doomed_update_grades($doomed, $userid = 0, $nullifnone = true) {
    if (!grading::is_graded($doomed)) {
        doomed_grade_item_update($doomed);
        return;
    }
    $grades = doomed_get_user_grades($doomed, $userid);
    doomed_grade_item_update($doomed, $grades ?: null);
}

/**
 * Data the course page and completion need without loading the instance.
 *
 * @param stdClass $coursemodule course module record
 * @return cached_cm_info|false
 */
function doomed_get_coursemodule_info($coursemodule) {
    global $DB;

    $doomed = $DB->get_record(
        'doomed',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, startmap, completionmap, completionmingrade'
    );
    if (!$doomed) {
        return false;
    }
    $result = new cached_cm_info();
    $result->name = $doomed->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('doomed', $doomed, $coursemodule->id, false);
    }
    $result->customdata['startmap'] = $doomed->startmap;
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionmap'] = (int) $doomed->completionmap;
        $result->customdata['customcompletionrules']['completionmingrade'] = (float) $doomed->completionmingrade;
    }
    return $result;
}

/**
 * Active custom completion rules, for the activity's completion information.
 *
 * @param cm_info|stdClass $cm course module with customdata
 * @return string[] rule descriptions
 */
function mod_doomed_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }
    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $rule => $value) {
        if ($rule === 'completionmap' && !empty($value)) {
            $descriptions[] = get_string('completiondetail:map', 'mod_doomed', s($cm->customdata['startmap'] ?? ''));
        } else if ($rule === 'completionmingrade' && (float) $value > 0) {
            $descriptions[] = get_string('completiondetail:mingrade', 'mod_doomed', format_float((float) $value, -1));
        }
    }
    return $descriptions;
}

/**
 * Add the attempts report to the activity's navigation.
 *
 * @param settings_navigation $settingsnav settings navigation
 * @param navigation_node $doomednode the activity's node
 * @return void
 */
function doomed_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $doomednode) {
    $cm = $settingsnav->get_page()->cm;
    if (!$cm || !has_capability('mod/doomed:viewreports', $cm->context)) {
        return;
    }
    $doomednode->add(
        get_string('attemptsreport', 'mod_doomed'),
        new moodle_url('/mod/doomed/report.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'mod_doomed_report'
    );
}

/**
 * Course reset form elements.
 *
 * @param MoodleQuickForm $mform the reset form
 * @return void
 */
function doomed_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'doomedheader', get_string('modulenameplural', 'mod_doomed'));
    $mform->addElement('advcheckbox', 'reset_doomed_attempts', get_string('resetattempts', 'mod_doomed'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course course record
 * @return array
 */
function doomed_reset_course_form_defaults($course) {
    return ['reset_doomed_attempts' => 1];
}

/**
 * Reset the user data of every Doomed activity in a course.
 *
 * @param stdClass $data reset form data
 * @return array status items
 */
function doomed_reset_userdata($data) {
    global $DB;

    $status = [];
    $componentstr = get_string('modulenameplural', 'mod_doomed');
    if (!empty($data->reset_doomed_attempts)) {
        $instances = $DB->get_records('doomed', ['course' => $data->courseid]);
        foreach ($instances as $doomed) {
            $DB->delete_records('doomed_attempts', ['doomedid' => $doomed->id]);
        }
        if (empty($data->reset_gradebook_grades)) {
            foreach ($instances as $doomed) {
                doomed_grade_item_update($doomed, 'reset');
            }
        }
        $status[] = ['component' => $componentstr, 'item' => get_string('resetattempts', 'mod_doomed'), 'error' => false];
    }
    // No dates in this module are shifted by a course reset.
    return $status;
}

/**
 * Remove all grades of the activities in a course (used by the course reset).
 *
 * @param int $courseid course id
 * @param string $type optional module type filter
 * @return void
 */
function doomed_reset_gradebook($courseid, $type = '') {
    global $DB;
    foreach ($DB->get_records('doomed', ['course' => $courseid]) as $doomed) {
        doomed_grade_item_update($doomed, 'reset');
    }
}

/**
 * Serve the activity's WAD files.
 *
 * Files are always sent as attachments: the player fetches them with
 * JavaScript, and nothing uploaded is ever rendered inline by the browser.
 *
 * @param stdClass $course course record
 * @param stdClass $cm course module record
 * @param context $context context
 * @param string $filearea file area
 * @param array $args remaining path arguments
 * @param bool $forcedownload ignored, always forced
 * @param array $options additional options
 * @return bool false if the file was not found; otherwise the file is sent and the script exits
 */
function doomed_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    if (!in_array($filearea, [wads::AREA_IWAD, wads::AREA_PWAD], true)) {
        return false;
    }
    require_course_login($course, true, $cm);
    require_capability('mod/doomed:view', $context);

    $itemid = (int) array_shift($args);
    if ($itemid !== 0) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'mod_doomed', $filearea, 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, true, $options);
}

/**
 * File areas for the file browser.
 *
 * @param stdClass $course course record
 * @param stdClass $cm course module record
 * @param context $context activity context
 * @return array area => description
 */
function doomed_get_file_areas($course, $cm, $context) {
    return [
        wads::AREA_IWAD => get_string('iwadfile', 'mod_doomed'),
        wads::AREA_PWAD => get_string('pwadfile', 'mod_doomed'),
    ];
}
