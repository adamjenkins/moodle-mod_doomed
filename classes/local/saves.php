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

namespace mod_doomed\local;

use context_module;

/**
 * Server copies of students' saved games, so saves follow them across devices.
 *
 * Saves live in the activity's 'saves' file area with the owner's user id as
 * the item id. They are only ever returned to their owner through the
 * mod_doomed_get_saves web service; pluginfile does not serve this area.
 * The content is opaque engine data and is never interpreted by Moodle.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class saves {
    /** @var string File area holding saved games (itemid = user id). */
    public const AREA = 'saves';

    /** @var int Largest saved game accepted, in bytes. The engine's default limit is 180224. */
    public const MAX_BYTES = 1048576;

    /**
     * Whether saved games are kept on the server on this site.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool) get_config('mod_doomed', 'syncsaves');
    }

    /**
     * Whether a file name is one of the game menu's six save slots (doomsav0.dsg to doomsav5.dsg).
     *
     * @param string $filename file name
     * @return bool
     */
    public static function is_save_name(string $filename): bool {
        return (bool) preg_match('/^doomsav[0-5]\.dsg$/', $filename);
    }

    /**
     * A user's saved games in an activity.
     *
     * @param context_module $context activity context
     * @param int $userid owner
     * @return \stored_file[] keyed by file name
     */
    public static function get_user_saves(context_module $context, int $userid): array {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_doomed',
            self::AREA,
            $userid,
            'filename',
            false
        );
        $saves = [];
        foreach ($files as $file) {
            if ($file->get_filepath() === '/' && self::is_save_name($file->get_filename())) {
                $saves[$file->get_filename()] = $file;
            }
        }
        return $saves;
    }

    /**
     * Store (or replace) one of a user's saved games.
     *
     * @param context_module $context activity context
     * @param int $userid owner
     * @param string $filename save slot file name, checked with is_save_name()
     * @param string $content raw bytes
     * @return \stored_file the stored file
     */
    public static function store(context_module $context, int $userid, string $filename, string $content): \stored_file {
        if (!self::is_save_name($filename)) {
            throw new \invalid_parameter_exception('Not a saved game file name');
        }
        if ($content === '' || strlen($content) > self::MAX_BYTES) {
            throw new \invalid_parameter_exception('Saved game is empty or too large');
        }
        $fs = get_file_storage();
        $existing = $fs->get_file($context->id, 'mod_doomed', self::AREA, $userid, '/', $filename);
        if ($existing) {
            $existing->delete();
        }
        return $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_doomed',
            'filearea' => self::AREA,
            'itemid' => $userid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'timecreated' => \core\di::get(\core\clock::class)->time(),
            'timemodified' => \core\di::get(\core\clock::class)->time(),
        ], $content);
    }

    /**
     * Delete saved games in an activity: one user's, or everyone's.
     *
     * @param context_module $context activity context
     * @param int|null $userid owner, or null for all users
     * @return void
     */
    public static function delete(context_module $context, ?int $userid = null): void {
        get_file_storage()->delete_area_files($context->id, 'mod_doomed', self::AREA, $userid ?? false);
    }
}
