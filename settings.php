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
 * Site administration settings for mod_doomed.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configselect(
        'mod_doomed/defaultskill',
        new lang_string('defaultskill', 'mod_doomed'),
        new lang_string('defaultskill_desc', 'mod_doomed'),
        3,
        \mod_doomed\local\options::skills()
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_doomed/allowiwadupload',
        new lang_string('allowiwadupload', 'mod_doomed'),
        new lang_string('allowiwadupload_desc', 'mod_doomed'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'mod_doomed/maxwadsize',
        new lang_string('maxwadsize', 'mod_doomed'),
        new lang_string('maxwadsize_desc', 'mod_doomed'),
        0,
        get_max_upload_sizes($CFG->maxbytes)
    ));
}
