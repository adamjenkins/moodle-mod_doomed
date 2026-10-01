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

/**
 * Reads the lump directory of a WAD file: its kind (IWAD/PWAD) and maps.
 *
 * Only the 12-byte header and the directory are read, never lump data, so
 * this is cheap even for a large IWAD.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wad {
    /** @var string Episode/map naming used by Doom and Freedoom Phase 1 (E1M1). */
    public const FORMAT_EPISODE = 'episode';

    /** @var string Map naming used by Doom II and Freedoom Phase 2 (MAP01). */
    public const FORMAT_MAPXX = 'mapxx';

    /** @var int Directory entries above this are treated as a corrupt file. */
    private const MAX_LUMPS = 65536;

    /** @var string 'IWAD' or 'PWAD'. */
    public readonly string $kind;

    /** @var string[] Map lump names in the order they appear, upper case. */
    public readonly array $maps;

    /**
     * Constructor.
     *
     * @param string $kind 'IWAD' or 'PWAD'
     * @param string[] $maps map lump names
     */
    private function __construct(string $kind, array $maps) {
        $this->kind = $kind;
        $this->maps = $maps;
    }

    /**
     * Parse a WAD from an open stream.
     *
     * @param resource $handle readable, seekable stream positioned anywhere
     * @param int $size total size of the file in bytes
     * @return self
     * @throws \moodle_exception if the stream is not a valid WAD
     */
    public static function from_stream($handle, int $size): self {
        if ($size < 12 || fseek($handle, 0) !== 0) {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        $header = fread($handle, 12);
        if ($header === false || strlen($header) !== 12) {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        $kind = substr($header, 0, 4);
        if ($kind !== 'IWAD' && $kind !== 'PWAD') {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        $fields = unpack('Vcount/Voffset', substr($header, 4, 8));
        $count = $fields['count'];
        $offset = $fields['offset'];
        if ($count > self::MAX_LUMPS || $offset < 12 || $offset + $count * 16 > $size) {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        if (fseek($handle, $offset) !== 0) {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        $directory = $count > 0 ? fread($handle, $count * 16) : '';
        if ($directory === false || strlen($directory) !== $count * 16) {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        $maps = [];
        for ($i = 0; $i < $count; $i++) {
            $name = strtoupper(rtrim(substr($directory, $i * 16 + 8, 8), "\0"));
            if (self::is_map_name($name) && !in_array($name, $maps, true)) {
                $maps[] = $name;
            }
        }
        return new self($kind, $maps);
    }

    /**
     * Parse a WAD held in the Moodle file pool.
     *
     * @param \stored_file $file the WAD
     * @return self
     * @throws \moodle_exception if the file is not a valid WAD
     */
    public static function from_stored_file(\stored_file $file): self {
        $handle = $file->get_content_file_handle();
        if (!$handle) {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        try {
            return self::from_stream($handle, (int) $file->get_filesize());
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parse a WAD on local disk.
     *
     * @param string $path absolute path
     * @return self
     * @throws \moodle_exception if the file is not a valid WAD
     */
    public static function from_path(string $path): self {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            throw new \moodle_exception('wadinvalid', 'mod_doomed');
        }
        try {
            return self::from_stream($handle, (int) filesize($path));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Whether a lump name is a map marker (ExMy or MAPxx).
     *
     * @param string $name upper-case lump name
     * @return bool
     */
    public static function is_map_name(string $name): bool {
        return (bool) preg_match('/^(E[1-9]M[1-9]|MAP[0-9][0-9])$/', $name);
    }

    /**
     * The naming scheme a map name belongs to.
     *
     * @param string $name map name
     * @return string|null one of the FORMAT_ constants, or null if not a map name
     */
    public static function map_format(string $name): ?string {
        $name = strtoupper($name);
        if (!self::is_map_name($name)) {
            return null;
        }
        return str_starts_with($name, 'MAP') ? self::FORMAT_MAPXX : self::FORMAT_EPISODE;
    }

    /**
     * The naming scheme this WAD's maps use, judged by its first map.
     *
     * @return string|null one of the FORMAT_ constants, or null if it has no maps
     */
    public function format(): ?string {
        return $this->maps ? self::map_format($this->maps[0]) : null;
    }
}
