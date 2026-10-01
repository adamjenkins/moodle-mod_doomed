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

namespace mod_doomed\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_doomed\local\saves;

/**
 * Store one of the calling user's saved games for an activity.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store_save extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'filename' => new external_value(PARAM_FILE, 'Save slot file name, doomsav0.dsg to doomsav5.dsg'),
            'content' => new external_value(PARAM_RAW, 'Saved game, base64-encoded'),
        ]);
    }

    /**
     * Store the save under the caller's own user id.
     *
     * @param int $cmid course module id
     * @param string $filename save slot file name
     * @param string $content base64 content
     * @return array
     */
    public static function execute(int $cmid, string $filename, string $content): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'filename' => $filename, 'content' => $content]
        );
        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'doomed');
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/doomed:play', $context);

        if (!saves::enabled()) {
            throw new \moodle_exception('savesdisabled', 'mod_doomed');
        }
        if (!saves::is_save_name($params['filename'])) {
            throw new \invalid_parameter_exception('Not a saved game file name');
        }
        // Base64 inflates by 4/3: refuse oversized input before decoding it.
        if (strlen($params['content']) > 4 * intdiv(saves::MAX_BYTES + 2, 3)) {
            throw new \invalid_parameter_exception('Saved game is too large');
        }
        $bytes = base64_decode($params['content'], true);
        if ($bytes === false) {
            throw new \invalid_parameter_exception('Saved game is not valid base64');
        }
        $file = saves::store($context, (int) $USER->id, $params['filename'], $bytes);
        return ['timemodified' => (int) $file->get_timemodified()];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'timemodified' => new external_value(PARAM_INT, 'When the server copy was stored'),
        ]);
    }
}
