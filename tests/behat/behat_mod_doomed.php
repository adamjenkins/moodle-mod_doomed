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
 * Behat steps for mod_doomed.
 *
 * @package    mod_doomed
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Behat steps for mod_doomed.
 *
 * @package    mod_doomed
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_doomed extends behat_base {
    /**
     * Convert page types to URLs for steps like 'I am on the "[identifier]" "mod_doomed > [page type]" page'.
     *
     * Recognised page types, each identified by the activity name:
     * | View            | The player (view.php)          |
     * | Attempts report | The attempts report (report.php) |
     *
     * @param string $type page type
     * @param string $identifier activity name
     * @return moodle_url
     * @throws Exception for an unknown page type
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $cm = $this->get_cm_by_activity_name('doomed', $identifier);
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url('/mod/doomed/view.php', ['id' => $cm->id]);
            case 'attempts report':
                return new moodle_url('/mod/doomed/report.php', ['id' => $cm->id]);
            default:
                throw new Exception('Unrecognised mod_doomed page type "' . $type . '".');
        }
    }

    /**
     * Record attempts as if the player had reported them.
     *
     * Columns: activity (name or idnumber), user (username), and optionally
     * outcome, map, skill, kills, totalkills, items, totalitems, secrets,
     * totalsecrets, leveltime and partime (seconds).
     *
     * @Given /^the following Doomed attempts exist:$/
     * @param TableNode $data attempts
     * @return void
     */
    public function the_following_doomed_attempts_exist(TableNode $data): void {
        global $DB;

        $defaults = [
            'outcome' => 'completed',
            'map' => 'E1M1',
            'skill' => 3,
            'kills' => 0,
            'totalkills' => 0,
            'items' => 0,
            'totalitems' => 0,
            'secrets' => 0,
            'totalsecrets' => 0,
            'leveltime' => 0,
            'partime' => 0,
        ];
        foreach ($data->getHash() as $row) {
            if (empty($row['activity']) || empty($row['user'])) {
                throw new Exception('Each Doomed attempt needs an activity and a user.');
            }
            $doomed = $DB->get_record('doomed', ['name' => $row['activity']]);
            if (!$doomed) {
                $cm = $DB->get_record('course_modules', ['idnumber' => $row['activity']], 'id, instance', MUST_EXIST);
                $doomed = $DB->get_record('doomed', ['id' => $cm->instance], '*', MUST_EXIST);
            }
            $user = $DB->get_record('user', ['username' => $row['user']], 'id', MUST_EXIST);

            $attempt = (object) ['doomedid' => $doomed->id, 'userid' => $user->id, 'timecreated' => time()];
            foreach ($defaults as $field => $default) {
                $attempt->$field = $row[$field] ?? $default;
            }
            $DB->insert_record('doomed_attempts', $attempt);
        }
    }
}
