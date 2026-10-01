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

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the WAD directory reader and the WAD file helpers.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(wad::class)]
#[CoversClass(wads::class)]
final class wad_test extends advanced_testcase {
    /**
     * Build a WAD in memory: header, no lump data, then the directory.
     *
     * @param string $kind 'IWAD', 'PWAD' or anything else
     * @param string[] $lumps lump names
     * @return string the bytes
     */
    private static function build_wad(string $kind, array $lumps): string {
        $directory = '';
        foreach ($lumps as $name) {
            $directory .= pack('VV', 12, 0) . str_pad($name, 8, "\0");
        }
        return $kind . pack('VV', count($lumps), 12) . $directory;
    }

    /**
     * Write bytes to a temporary file.
     *
     * @param string $bytes contents
     * @return string path
     */
    private function temp_file(string $bytes): string {
        $path = make_request_directory() . '/test.wad';
        file_put_contents($path, $bytes);
        return $path;
    }

    /**
     * The E1M1 fixture is a PWAD with one episode-style map.
     */
    public function test_fixture_e1m1(): void {
        $wad = wad::from_path(__DIR__ . '/../fixtures/exitroom_e1m1.wad');
        $this->assertSame('PWAD', $wad->kind);
        $this->assertSame(['E1M1'], $wad->maps);
        $this->assertSame(wad::FORMAT_EPISODE, $wad->format());
    }

    /**
     * The MAP01 fixture is a PWAD with one MAPxx-style map.
     */
    public function test_fixture_map01(): void {
        $wad = wad::from_path(__DIR__ . '/../fixtures/exitroom_map01.wad');
        $this->assertSame('PWAD', $wad->kind);
        $this->assertSame(['MAP01'], $wad->maps);
        $this->assertSame(wad::FORMAT_MAPXX, $wad->format());
    }

    /**
     * The bundled Freedoom Phase 1 IWAD has E1M1 to E4M9.
     *
     * The count was checked independently by reading the directory with a
     * python3 struct.unpack('<II') script: 3163 lumps, 36 distinct map markers.
     */
    public function test_bundled_freedoom1(): void {
        $this->assertFileExists(wads::freedoom1_path());
        $wad = wad::from_path(wads::freedoom1_path());
        $this->assertSame('IWAD', $wad->kind);
        $expected = [];
        for ($e = 1; $e <= 4; $e++) {
            for ($m = 1; $m <= 9; $m++) {
                $expected[] = "E{$e}M{$m}";
            }
        }
        $this->assertCount(36, $wad->maps);
        $this->assertSame($expected, $wad->maps);
        $this->assertSame(wad::FORMAT_EPISODE, $wad->format());
    }

    /**
     * A WAD in the file pool parses the same as on disk.
     */
    public function test_from_stored_file(): void {
        $this->resetAfterTest();
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => \context_system::instance()->id,
            'component' => 'mod_doomed',
            'filearea' => 'pwad',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'exitroom_map01.wad',
        ], __DIR__ . '/../fixtures/exitroom_map01.wad');
        $wad = wad::from_stored_file($file);
        $this->assertSame('PWAD', $wad->kind);
        $this->assertSame(['MAP01'], $wad->maps);
    }

    /**
     * A synthetic directory: non-map lumps skipped, names upper-cased, duplicates dropped, order kept.
     */
    public function test_directory_filtering(): void {
        $bytes = self::build_wad('PWAD', ['MAP02', 'THINGS', 'map01', 'E1M10', 'MAP02', 'PLAYPAL', 'E2M3']);
        $wad = wad::from_path($this->temp_file($bytes));
        $this->assertSame('PWAD', $wad->kind);
        $this->assertSame(['MAP02', 'MAP01', 'E2M3'], $wad->maps);
        // The first map decides the format.
        $this->assertSame(wad::FORMAT_MAPXX, $wad->format());
    }

    /**
     * A valid WAD with no maps (or no lumps) has no format.
     */
    public function test_no_maps(): void {
        $wad = wad::from_path($this->temp_file(self::build_wad('PWAD', ['DEMO1', 'TEXTURE1'])));
        $this->assertSame([], $wad->maps);
        $this->assertNull($wad->format());

        $wad = wad::from_path($this->temp_file(self::build_wad('IWAD', [])));
        $this->assertSame('IWAD', $wad->kind);
        $this->assertSame([], $wad->maps);
    }

    /**
     * Data that is not a valid WAD.
     *
     * @return array name => [bytes]
     */
    public static function invalid_provider(): array {
        $valid = self::build_wad('PWAD', ['E1M1', 'THINGS']);
        return [
            'empty' => [''],
            'shorter than a header' => ['PWAD' . "\x01\x00"],
            'garbage' => [str_repeat("\xDE\xAD\xBE\xEF", 64)],
            'text file' => ["Hello, this is definitely not a WAD file.\n"],
            'wrong magic' => ['XWAD' . substr($valid, 4)],
            'lower-case magic' => ['pwad' . substr($valid, 4)],
            // Directory claims 2 entries (32 bytes) but the last 5 are missing.
            'truncated directory' => [substr($valid, 0, -5)],
            // Directory offset points past the end of the file.
            'directory past EOF' => ['PWAD' . pack('VV', 1, 4096) . str_repeat("\0", 16)],
            // Directory offset inside the header.
            'directory inside header' => ['PWAD' . pack('VV', 0, 4)],
            // More entries than the 65536 limit.
            'absurd lump count' => ['IWAD' . pack('VV', 70000, 12)],
            // Count * 16 overflows the file even though the offset is fine.
            'count past EOF' => ['PWAD' . pack('VV', 3, 12) . str_repeat("\0", 32)],
        ];
    }

    /**
     * Invalid data is rejected from a path.
     *
     * @param string $bytes file contents
     */
    #[DataProvider('invalid_provider')]
    public function test_invalid_from_path(string $bytes): void {
        $path = $this->temp_file($bytes);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('wadinvalid', 'mod_doomed'));
        wad::from_path($path);
    }

    /**
     * A missing file is rejected.
     */
    public function test_missing_file(): void {
        $this->expectException(\moodle_exception::class);
        wad::from_path(make_request_directory() . '/nope.wad');
    }

    /**
     * A stream whose declared size is larger than its data is rejected.
     */
    public function test_stream_shorter_than_declared(): void {
        $bytes = self::build_wad('PWAD', ['E1M1']);
        // Header says one entry at 12; drop the directory but declare the full size.
        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, substr($bytes, 0, 12));
        try {
            $this->expectException(\moodle_exception::class);
            wad::from_stream($handle, strlen($bytes));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Map name recognition.
     *
     * @return array name => [lump name, is map, format]
     */
    public static function map_name_provider(): array {
        return [
            ['E1M1', true, wad::FORMAT_EPISODE],
            ['E4M9', true, wad::FORMAT_EPISODE],
            ['E9M9', true, wad::FORMAT_EPISODE],
            ['MAP01', true, wad::FORMAT_MAPXX],
            ['MAP32', true, wad::FORMAT_MAPXX],
            ['MAP00', true, wad::FORMAT_MAPXX],
            ['MAP99', true, wad::FORMAT_MAPXX],
            ['E0M1', false, null],
            ['E1M0', false, null],
            ['E10M1', false, null],
            ['E1M10', false, null],
            ['MAP1', false, null],
            ['MAP100', false, null],
            ['MAPAB', false, null],
            ['E1M1X', false, null],
            [' E1M1', false, null],
            ['', false, null],
            ['THINGS', false, null],
        ];
    }

    /**
     * is_map_name and map_format on upper-case names.
     *
     * @param string $name lump name
     * @param bool $ismap expected is_map_name
     * @param string|null $format expected map_format
     */
    #[DataProvider('map_name_provider')]
    public function test_map_names(string $name, bool $ismap, ?string $format): void {
        $this->assertSame($ismap, wad::is_map_name($name));
        $this->assertSame($format, wad::map_format($name));
    }

    /**
     * is_map_name expects upper case; map_format upper-cases first.
     */
    public function test_map_name_case(): void {
        $this->assertFalse(wad::is_map_name('e1m1'));
        $this->assertFalse(wad::is_map_name('map01'));
        $this->assertSame(wad::FORMAT_EPISODE, wad::map_format('e2m3'));
        $this->assertSame(wad::FORMAT_MAPXX, wad::map_format('map01'));
        $this->assertNull(wad::map_format('map'));
    }

    /**
     * Engine filenames.
     *
     * @return array name => [uploaded name, expected engine name]
     */
    public static function engine_filename_provider(): array {
        return [
            'upper case' => ['DOOM2.WAD', 'doom2.wad'],
            'already fine' => ['freedoom2.wad', 'freedoom2.wad'],
            'spaces and brackets' => ['My File (1).wad', 'my_file__1_.wad'],
            'no extension' => ['noext', 'noext.wad'],
            'leading dots stripped' => ['.hidden', 'hidden.wad'],
            // A bare '.wad' loses its dot and becomes 'wad', which then gets the extension.
            'only an extension' => ['.wad', 'wad.wad'],
            'empty' => ['', 'upload.wad'],
            'only dots' => ['...', 'upload.wad'],
            // Each '/' becomes '_': '.._.._etc_passwd', then leading dots go.
            'path traversal' => ['../../etc/passwd', '_.._etc_passwd.wad'],
            // Each byte of the two-byte UTF-8 letters becomes '_'.
            'non-ASCII' => ["\u{00FC}n\u{00EF}code.wad", '__n__code.wad'],
            'keeps hyphen and underscore' => ['my-map_v2.WAD', 'my-map_v2.wad'],
        ];
    }

    /**
     * wads::engine_filename.
     *
     * @param string $filename uploaded name
     * @param string $expected expected result
     */
    #[DataProvider('engine_filename_provider')]
    public function test_engine_filename(string $filename, string $expected): void {
        $this->assertSame($expected, wads::engine_filename($filename));
    }
}
