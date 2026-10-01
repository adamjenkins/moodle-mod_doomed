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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_doomed\local\saves;

/**
 * Return the calling user's saved games for an activity.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_saves extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Return the saves. Only ever the caller's own: the user id is never a parameter.
     *
     * @param int $cmid course module id
     * @return array
     */
    public static function execute(int $cmid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'doomed');
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/doomed:play', $context);

        $result = [];
        if (saves::enabled()) {
            foreach (saves::get_user_saves($context, (int) $USER->id) as $file) {
                $result[] = [
                    'filename' => $file->get_filename(),
                    'timemodified' => (int) $file->get_timemodified(),
                    'content' => base64_encode($file->get_content()),
                ];
            }
        }
        return ['enabled' => saves::enabled(), 'saves' => $result];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Whether saved games are kept on the server'),
            'saves' => new external_multiple_structure(new external_single_structure([
                'filename' => new external_value(PARAM_FILE, 'Save slot file name'),
                'timemodified' => new external_value(PARAM_INT, 'When the server copy was stored'),
                'content' => new external_value(PARAM_RAW, 'Saved game, base64-encoded'),
            ])),
        ]);
    }
}
