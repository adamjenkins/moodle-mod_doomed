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
 * Restore task for mod_doomed.
 *
 * @package    mod_doomed
 * @category   backup
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Restore step classes are global and not autoloaded.
require_once($CFG->dirroot . '/mod/doomed/backup/moodle2/restore_doomed_stepslib.php');

/**
 * Provides the steps to restore one Doomed activity.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_doomed_activity_task extends restore_activity_task {
    /**
     * No settings of its own.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * The single structure step.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_doomed_activity_structure_step('doomed_structure', 'doomed.xml'));
    }

    /**
     * Content fields that may hold encoded links.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('doomed', ['intro'], 'doomed'),
        ];
    }

    /**
     * Rules turning encoded links back into links to the restored course and activity.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('DOOMEDVIEWBYID', '/mod/doomed/view.php?id=$1', 'course_module'),
            new restore_decode_rule('DOOMEDINDEX', '/mod/doomed/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Legacy log rules for the activity's own logs.
     *
     * Only view.php ever logged ('view', from course_module_viewed's legacy
     * name); result submissions are logged by attempt_submitted, whose object
     * id is mapped through get_objectid_mapping() instead.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('doomed', 'view', 'view.php?id={course_module}', '{doomed}'),
        ];
    }

    /**
     * Legacy log rules for course-level logs: the index page.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('doomed', 'view all', 'index.php?id={course}', null),
        ];
    }
}
