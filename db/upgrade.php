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
 * Upgrade steps for mod_doomed.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade mod_doomed.
 *
 * @param int $oldversion the version being upgraded from
 * @return bool true
 */
function xmldb_doomed_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100102) {
        // Activities play an ordered list of levels instead of a single starting map.
        $table = new xmldb_table('doomed');
        $field = new xmldb_field('levels', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'E1M1', 'startmap');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Existing activities keep exactly their single starting map.
            $DB->execute('UPDATE {doomed} SET levels = startmap');
        }
        $field = new xmldb_field('freeplay', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'levels');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026100102, 'doomed');
    }

    return true;
}
