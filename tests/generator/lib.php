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
 * Data generator for mod_doomed.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_doomed_generator extends testing_module_generator {
    /**
     * Create a Doomed activity.
     *
     * @param array|stdClass|null $record instance fields
     * @param array|null $options course module options
     * @return stdClass the instance record with cmid
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) ($record ?? []);
        $record += [
            'iwadsource' => 'freedoom1',
            'startmap' => 'E1M1',
            'skill' => 3,
            'grademode' => 0,
            'grade' => 100,
            'weightkills' => 1,
            'weightitems' => 1,
            'weightsecrets' => 1,
            'timebonus' => 0,
            'grademethod' => 1,
            'completionmap' => 0,
            'completionmingrade' => 0,
        ];
        return parent::create_instance($record, (array) $options);
    }
}
