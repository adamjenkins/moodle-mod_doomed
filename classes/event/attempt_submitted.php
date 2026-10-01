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

namespace mod_doomed\event;

/**
 * A student's game reported a level completion or death.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string outcome: completed or died
 *      - string map: map name
 * }
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_submitted extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'doomed_attempts';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventattemptsubmitted', 'mod_doomed');
    }

    /**
     * Event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' submitted a Doomed result ('{$this->other['outcome']}' on map "
            . "'{$this->other['map']}') for the activity with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Link to the activity's report.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/doomed/report.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validate the event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['outcome']) || !isset($this->other['map'])) {
            throw new \coding_exception('The \'outcome\' and \'map\' values must be set in other.');
        }
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    /**
     * Mapping for restoring the object id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'doomed_attempts', 'restore' => 'doomed_attempt'];
    }

    /**
     * Mapping for restoring values in other.
     *
     * @return bool false: nothing in other needs mapping
     */
    public static function get_other_mapping() {
        return false;
    }
}
