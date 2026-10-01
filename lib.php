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
        case FEATURE_COMPLETION_TRACKS_VIEWS:
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
 * Normalise form data into a doomed record and save its WAD file areas.
 *
 * @param stdClass $data form data; changed in place
 * @return void
 */
function doomed_process_form_data(stdClass $data): void {
    $data->startmap = strtoupper(trim($data->startmap ?? 'E1M1'));
    $data->timemodified = time();
    if (empty($data->timebonusenabled)) {
        $data->timebonus = 0;
    }
    if (empty($data->completionmingradeenabled)) {
        $data->completionmingrade = 0;
    }
}

/**
 * Save the IWAD/PWAD draft areas of a submitted form.
 *
 * @param stdClass $data form data with coursemodule, iwadfile and pwadfile
 * @return void
 */
function doomed_save_wad_files(stdClass $data): void {
    $context = context_module::instance($data->coursemodule);
    $options = \mod_doomed\local\wads::filemanager_options();
    if (isset($data->iwadfile)) {
        if ($data->iwadsource === \mod_doomed\local\wads::SOURCE_UPLOAD) {
            file_save_draft_area_files(
                $data->iwadfile,
                $context->id,
                'mod_doomed',
                \mod_doomed\local\wads::AREA_IWAD,
                0,
                $options
            );
        } else {
            get_file_storage()->delete_area_files($context->id, 'mod_doomed', \mod_doomed\local\wads::AREA_IWAD);
        }
    }
    if (isset($data->pwadfile)) {
        file_save_draft_area_files(
            $data->pwadfile,
            $context->id,
            'mod_doomed',
            \mod_doomed\local\wads::AREA_PWAD,
            0,
            $options
        );
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

    if (!$DB->record_exists('doomed', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('doomed_attempts', ['doomedid' => $id]);
    $DB->delete_records('doomed', ['id' => $id]);
    return true;
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
    if (!in_array($filearea, [\mod_doomed\local\wads::AREA_IWAD, \mod_doomed\local\wads::AREA_PWAD], true)) {
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
