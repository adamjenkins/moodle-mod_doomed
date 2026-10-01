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
 * Backup structure step for mod_doomed.
 *
 * @package    mod_doomed
 * @category   backup
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the structure of doomed.xml: the settings, the WAD files and, with
 * user data, the attempts.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_doomed_activity_structure_step extends backup_activity_structure_step {
    /**
     * Build the structure.
     *
     * @return backup_nested_element the root element wrapped in the activity structure
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $doomed = new backup_nested_element('doomed', ['id'], [
            'name', 'intro', 'introformat', 'iwadsource', 'startmap', 'skill',
            'grademode', 'grade', 'weightkills', 'weightitems', 'weightsecrets',
            'timebonus', 'grademethod', 'completionmap', 'completionmingrade',
            'timecreated', 'timemodified',
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'outcome', 'map', 'skill', 'kills', 'totalkills',
            'items', 'totalitems', 'secrets', 'totalsecrets',
            'leveltime', 'partime', 'timecreated',
        ]);

        $doomed->add_child($attempts);
        $attempts->add_child($attempt);

        $doomed->set_source_table('doomed', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $attempt->set_source_table('doomed_attempts', ['doomedid' => backup::VAR_PARENTID], 'id ASC');
        }

        $attempt->annotate_ids('user', 'userid');

        $doomed->annotate_files('mod_doomed', 'intro', null);
        $doomed->annotate_files('mod_doomed', 'iwad', null);
        $doomed->annotate_files('mod_doomed', 'pwad', null);

        return $this->prepare_activity_structure($doomed);
    }
}
