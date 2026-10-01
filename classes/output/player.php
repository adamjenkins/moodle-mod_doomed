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

namespace mod_doomed\output;

use cm_info;
use context_module;
use mod_doomed\local\wads;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The in-browser player for one activity.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param stdClass $doomed activity record
     * @param cm_info $cm course module
     * @param context_module $context activity context
     * @param stdClass $user the viewing user
     */
    public function __construct(
        /** @var stdClass activity record */
        protected stdClass $doomed,
        /** @var cm_info course module */
        protected cm_info $cm,
        /** @var context_module activity context */
        protected context_module $context,
        /** @var stdClass viewing user */
        protected stdClass $user,
    ) {
    }

    /**
     * The configuration handed to the player JavaScript.
     *
     * @return array
     */
    public function get_js_config(): array {
        $files = wads::player_files($this->doomed, $this->context);
        return [
            'cmid' => (int) $this->cm->id,
            'engineurl' => (new moodle_url('/mod/doomed/engine/doomed-engine.js'))->out(false),
            'wasmurl' => (new moodle_url('/mod/doomed/engine/doomed-engine.wasm'))->out(false),
            'iwadurl' => $files['iwadurl'],
            'iwadname' => $files['iwadname'],
            'pwadurl' => $files['pwadurl'],
            'pwadname' => $files['pwadname'],
            'startmap' => $this->doomed->startmap,
            'skill' => (int) $this->doomed->skill,
            // Browser save storage is keyed per user and activity so saves on a
            // shared computer do not leak between accounts or activities.
            'saveroot' => '/doomed/u' . (int) $this->user->id . '/cm' . (int) $this->cm->id,
            'canrecord' => has_capability('mod/doomed:play', $this->context),
            // Server copies of saves need the same right as recording results.
            'syncsaves' => \mod_doomed\local\saves::enabled() && has_capability('mod/doomed:play', $this->context),
        ];
    }

    /**
     * Export data for the player template.
     *
     * @param renderer_base $output renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'uniqid' => $this->get_dom_id(),
            'startmap' => $this->doomed->startmap,
            'canrecord' => has_capability('mod/doomed:play', $this->context),
        ];
    }

    /**
     * The id of the player's root element.
     *
     * @return string
     */
    public function get_dom_id(): string {
        return 'mod-doomed-player-' . $this->cm->id;
    }
}
