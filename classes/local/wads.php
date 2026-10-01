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
use moodle_url;
use stdClass;

/**
 * Resolves which WAD files an activity plays and where the player fetches them.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wads {
    /** @var string IWAD source: the bundled Freedoom Phase 1. */
    public const SOURCE_FREEDOOM1 = 'freedoom1';

    /** @var string IWAD source: a file uploaded to the activity. */
    public const SOURCE_UPLOAD = 'upload';

    /** @var string File area holding an uploaded IWAD. */
    public const AREA_IWAD = 'iwad';

    /** @var string File area holding an optional PWAD. */
    public const AREA_PWAD = 'pwad';

    /** @var string Bundled IWAD path relative to the plugin directory. */
    public const FREEDOOM1_PATH = '/wads/freedoom1.wad';

    /**
     * File manager options for the WAD upload fields.
     *
     * @return array
     */
    public static function filemanager_options(): array {
        global $CFG;
        // FILE_INTERNAL lives here; outside a form page it is not loaded yet.
        require_once($CFG->dirroot . '/repository/lib.php');
        $maxbytes = (int) get_config('mod_doomed', 'maxwadsize');
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $maxbytes > 0 ? $maxbytes : 0,
            'accepted_types' => ['.wad'],
            'return_types' => FILE_INTERNAL,
        ];
    }

    /**
     * Absolute path of the bundled Freedoom Phase 1 IWAD.
     *
     * @return string
     */
    public static function freedoom1_path(): string {
        global $CFG;
        return $CFG->dirroot . '/mod/doomed' . self::FREEDOOM1_PATH;
    }

    /**
     * The stored file in one of an activity's WAD areas.
     *
     * @param context_module $context activity context
     * @param string $area AREA_IWAD or AREA_PWAD
     * @return \stored_file|null
     */
    public static function get_area_file(context_module $context, string $area): ?\stored_file {
        $files = get_file_storage()->get_area_files($context->id, 'mod_doomed', $area, 0, 'id', false);
        return $files ? reset($files) : null;
    }

    /**
     * What the player needs to load an activity's WADs.
     *
     * @param stdClass $doomed activity record
     * @param context_module $context activity context
     * @return array with iwadurl, iwadname, pwadurl (or null), pwadname (or null)
     * @throws \moodle_exception if an uploaded IWAD is selected but missing
     */
    public static function player_files(stdClass $doomed, context_module $context): array {
        if ($doomed->iwadsource === self::SOURCE_UPLOAD) {
            $iwad = self::get_area_file($context, self::AREA_IWAD);
            if (!$iwad) {
                throw new \moodle_exception('iwadmissing', 'mod_doomed');
            }
            $iwadurl = self::file_url($iwad)->out(false);
            $iwadname = self::engine_filename($iwad->get_filename());
        } else {
            $iwadurl = (new moodle_url('/mod/doomed' . self::FREEDOOM1_PATH))->out(false);
            $iwadname = 'freedoom1.wad';
        }
        $pwad = self::get_area_file($context, self::AREA_PWAD);
        return [
            'iwadurl' => $iwadurl,
            'iwadname' => $iwadname,
            'pwadurl' => $pwad ? self::file_url($pwad)->out(false) : null,
            'pwadname' => $pwad ? 'pwad-' . self::engine_filename($pwad->get_filename()) : null,
        ];
    }

    /**
     * The maps an activity can start on: the IWAD's maps plus the PWAD's.
     *
     * @param stdClass $doomed activity record
     * @param context_module $context activity context
     * @return string[] map names
     */
    public static function available_maps(stdClass $doomed, context_module $context): array {
        $maps = [];
        if ($doomed->iwadsource === self::SOURCE_UPLOAD) {
            $iwad = self::get_area_file($context, self::AREA_IWAD);
            if ($iwad) {
                $maps = wad::from_stored_file($iwad)->maps;
            }
        } else {
            $maps = wad::from_path(self::freedoom1_path())->maps;
        }
        $pwad = self::get_area_file($context, self::AREA_PWAD);
        if ($pwad) {
            $maps = array_values(array_unique(array_merge($maps, wad::from_stored_file($pwad)->maps)));
        }
        return $maps;
    }

    /**
     * Pluginfile URL for a stored WAD.
     *
     * @param \stored_file $file the file
     * @return moodle_url
     */
    private static function file_url(\stored_file $file): moodle_url {
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'mod_doomed',
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * A safe in-engine filename for an uploaded WAD.
     *
     * The engine recognises well-known IWADs by name (doom2.wad, plutonia.wad
     * and so on) and falls back to inspecting the contents otherwise, so the
     * uploaded name is kept, reduced to characters safe in a virtual path.
     *
     * @param string $filename original name
     * @return string
     */
    public static function engine_filename(string $filename): string {
        $name = strtolower(preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename));
        $name = ltrim($name, '.');
        if (!str_ends_with($name, '.wad')) {
            $name .= '.wad';
        }
        return $name === '.wad' ? 'upload.wad' : $name;
    }
}
