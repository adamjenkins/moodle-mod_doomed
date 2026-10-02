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

/**
 * Tests for activity level lists and their form validation.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(levels::class)]
#[CoversClass(\mod_doomed_mod_form::class)]
final class levels_test extends advanced_testcase {
    /**
     * Load the activity form class.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/doomed/mod_form.php');
    }

    /**
     * Commas, spaces, semicolons and new lines separate names; case and order are normalised and kept.
     */
    public function test_parse_and_format(): void {
        $this->assertSame(['E1M1', 'E1M2', 'E1M5', 'MAP07'], levels::parse(" e1m1, E1M2 ;e1m5\nmap07 "));
        $this->assertSame([], levels::parse(" ,, "));
        $this->assertSame('E1M1,E1M5', levels::format(['E1M1', 'E1M5']));
    }

    /**
     * A record's list ignores junk entries and falls back to startmap when it has no usable list.
     */
    public function test_from_record_and_contains(): void {
        $doomed = (object) ['levels' => 'E1M3,bogus,E2M1', 'startmap' => 'E1M3'];
        $this->assertSame(['E1M3', 'E2M1'], levels::from_record($doomed));
        $this->assertTrue(levels::contains($doomed, 'e2m1'));
        $this->assertFalse(levels::contains($doomed, 'E1M1'));
        $this->assertSame(['MAP02'], levels::from_record((object) ['levels' => '', 'startmap' => 'MAP02']));
        $this->assertSame(['E1M1'], levels::from_record((object) []));
    }

    /**
     * The form accepts a list of available maps in the IWAD's naming style and explains every refusal.
     */
    public function test_validate_levels(): void {
        $maps = ['E1M1', 'E1M2', 'E1M3', 'E1M9'];
        $episode = wad::FORMAT_EPISODE;
        $this->assertNull(\mod_doomed_mod_form::validate_levels(['E1M2', 'E1M9', 'E1M1'], $maps, $episode));

        $this->assertSame(get_string('required'), \mod_doomed_mod_form::validate_levels([], $maps, $episode));
        $this->assertSame(
            get_string('levelsinvalid', 'mod_doomed', 'E1X1'),
            \mod_doomed_mod_form::validate_levels(['E1M1', 'E1X1'], $maps, $episode)
        );
        $this->assertSame(
            get_string('levelswrongformat', 'mod_doomed', 'E1M1'),
            \mod_doomed_mod_form::validate_levels(['MAP01'], $maps, $episode)
        );
        $this->assertSame(
            get_string('levelsnotfound', 'mod_doomed', 'E1M4'),
            \mod_doomed_mod_form::validate_levels(['E1M1', 'E1M4'], $maps, $episode)
        );
        $this->assertSame(
            get_string('levelsduplicate', 'mod_doomed', 'E1M2'),
            \mod_doomed_mod_form::validate_levels(['E1M2', 'E1M1', 'E1M2'], $maps, $episode)
        );

        $many = array_map(fn($i) => 'MAP' . sprintf('%02d', $i), range(1, levels::MAX_LEVELS + 1));
        $this->assertSame(
            get_string('levelstoomany', 'mod_doomed', levels::MAX_LEVELS),
            \mod_doomed_mod_form::validate_levels($many, $many, wad::FORMAT_MAPXX)
        );
    }
}
