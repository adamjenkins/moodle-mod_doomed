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
 * Backup task for mod_doomed.
 *
 * @package    mod_doomed
 * @category   backup
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Backup step classes are global and not autoloaded.
require_once($CFG->dirroot . '/mod/doomed/backup/moodle2/backup_doomed_stepslib.php');

/**
 * Provides the steps to back up one Doomed activity.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_doomed_activity_task extends backup_activity_task {
    /**
     * No settings of its own: the inherited userinfo setting decides about attempts.
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
        $this->add_step(new backup_doomed_activity_structure_step('doomed_structure', 'doomed.xml'));
    }

    /**
     * Encode links to the activity's index and view pages for the restore decoder.
     *
     * @param string $content HTML that may contain links
     * @return string the content with links encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/doomed\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@DOOMEDINDEX*$2@$', $content);

        $search = '/(' . $base . '\/mod\/doomed\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@DOOMEDVIEWBYID*$2@$', $content);

        return $content;
    }
}
