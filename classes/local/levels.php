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

use stdClass;

/**
 * The ordered list of levels (maps) an activity plays and grades.
 *
 * Stored in doomed.levels as comma-separated map names, e.g. "E1M1,E1M2,E1M5".
 * doomed.startmap always holds the first of them.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class levels {
    /** @var int Most levels one activity may list (the engine's limit too). */
    public const MAX_LEVELS = 32;

    /**
     * Split teacher input into upper-case names, in order.
     *
     * Commas, spaces and new lines all separate. Names are not validated
     * here; see wad::is_map_name().
     *
     * @param string $text input such as "e1m1, E1M2 e1m5"
     * @return string[]
     */
    public static function parse(string $text): array {
        $names = preg_split('/[\s,;]+/', strtoupper(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        return array_values($names ?: []);
    }

    /**
     * The stored form of a list.
     *
     * @param string[] $levels map names
     * @return string
     */
    public static function format(array $levels): string {
        return implode(',', $levels);
    }

    /**
     * An activity's levels, in order.
     *
     * @param stdClass $doomed activity record
     * @return string[] at least one map name
     */
    public static function from_record(stdClass $doomed): array {
        $levels = array_values(array_filter(self::parse((string) ($doomed->levels ?? '')), [wad::class, 'is_map_name']));
        if (!$levels) {
            $levels = [strtoupper((string) ($doomed->startmap ?? 'E1M1'))];
        }
        return $levels;
    }

    /**
     * Whether a map is one of the activity's levels.
     *
     * @param stdClass $doomed activity record
     * @param string $map map name
     * @return bool
     */
    public static function contains(stdClass $doomed, string $map): bool {
        return in_array(strtoupper($map), self::from_record($doomed), true);
    }
}
