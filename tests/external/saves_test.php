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

use advanced_testcase;
use core_external\external_api;
use mod_doomed\local\saves;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Tests for the saved-game web services.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_saves::class)]
#[CoversClass(store_save::class)]
#[CoversClass(saves::class)]
final class saves_test extends advanced_testcase {
    /** @var stdClass course */
    private stdClass $course;

    /** @var stdClass activity record with cmid */
    private stdClass $doomed;

    /** @var stdClass first student */
    private stdClass $student;

    /** @var stdClass second student */
    private stdClass $other;

    /**
     * A course with one activity and two students; server saves on.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1790000000);
        set_config('syncsaves', 1, 'mod_doomed');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->doomed = $generator->create_module('doomed', ['course' => $this->course->id]);
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->other = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * Store a save as the current user, the way the web service layer does.
     *
     * @param string $filename slot file name
     * @param string $bytes raw content
     * @return array cleaned return value
     */
    private function store(string $filename, string $bytes): array {
        return external_api::clean_returnvalue(
            store_save::execute_returns(),
            store_save::execute((int) $this->doomed->cmid, $filename, base64_encode($bytes))
        );
    }

    /**
     * Fetch the current user's saves.
     *
     * @return array cleaned return value
     */
    private function fetch(): array {
        return external_api::clean_returnvalue(
            get_saves::execute_returns(),
            get_saves::execute((int) $this->doomed->cmid)
        );
    }

    /**
     * A stored save comes back byte for byte, and storing a slot again replaces it.
     */
    public function test_store_and_fetch(): void {
        $this->setUser($this->student);
        $first = random_bytes(1000);
        $result = $this->store('doomsav0.dsg', $first);
        $this->assertSame(1790000000, $result['timemodified']);

        $fetched = $this->fetch();
        $this->assertTrue($fetched['enabled']);
        $this->assertCount(1, $fetched['saves']);
        $this->assertSame('doomsav0.dsg', $fetched['saves'][0]['filename']);
        $this->assertSame($first, base64_decode($fetched['saves'][0]['content']));

        $second = random_bytes(2000);
        $this->store('doomsav0.dsg', $second);
        $fetched = $this->fetch();
        $this->assertCount(1, $fetched['saves']);
        $this->assertSame($second, base64_decode($fetched['saves'][0]['content']));
    }

    /**
     * Each student only ever sees their own saves.
     */
    public function test_saves_are_private(): void {
        $this->setUser($this->student);
        $this->store('doomsav1.dsg', 'student save');

        $this->setUser($this->other);
        $this->assertSame([], $this->fetch()['saves']);
        $this->store('doomsav1.dsg', 'other save');

        $this->setUser($this->student);
        $saves = $this->fetch()['saves'];
        $this->assertCount(1, $saves);
        $this->assertSame('student save', base64_decode($saves[0]['content']));
    }

    /**
     * Names other than the six menu slots, oversized and malformed content are refused.
     */
    public function test_invalid_saves_refused(): void {
        $this->setUser($this->student);
        foreach (['doomsav6.dsg', 'doomed.cfg', 'DOOMSAV0.DSG'] as $name) {
            try {
                $this->store($name, 'x');
                $this->fail("$name must be refused");
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString('Not a saved game file name', $e->debuginfo ?? $e->getMessage());
            }
        }
        // A path is already refused by the PARAM_FILE parameter validation.
        try {
            $this->store('../doomsav0.dsg', 'x');
            $this->fail('A path must be refused');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('expecting "file" type', $e->debuginfo ?? $e->getMessage());
        }
        try {
            $this->store('doomsav0.dsg', str_repeat('x', saves::MAX_BYTES + 1));
            $this->fail('An oversized save must be refused');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('too large', $e->debuginfo ?? $e->getMessage());
        }
        try {
            store_save::execute((int) $this->doomed->cmid, 'doomsav0.dsg', '***not base64***');
            $this->fail('Malformed base64 must be refused');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('base64', $e->debuginfo ?? $e->getMessage());
        }
        $this->assertSame([], $this->fetch()['saves']);
    }

    /**
     * Without mod/doomed:play neither service is available.
     */
    public function test_requires_play_capability(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        try {
            $this->store('doomsav0.dsg', 'x');
            $this->fail('A user without mod/doomed:play must be refused');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
            $this->assertStringContainsString(get_string('doomed:play', 'mod_doomed'), $e->getMessage());
        }
        $this->expectException(\required_capability_exception::class);
        $this->fetch();
    }

    /**
     * With server saves turned off, storing is refused and fetching returns nothing.
     */
    public function test_disabled_on_site(): void {
        $this->setUser($this->student);
        $this->store('doomsav0.dsg', 'kept from before');
        set_config('syncsaves', 0, 'mod_doomed');

        $fetched = $this->fetch();
        $this->assertFalse($fetched['enabled']);
        $this->assertSame([], $fetched['saves']);
        try {
            $this->store('doomsav1.dsg', 'x');
            $this->fail('Storing must be refused while server saves are off');
        } catch (\moodle_exception $e) {
            $this->assertSame('savesdisabled', $e->errorcode);
        }
    }
}
