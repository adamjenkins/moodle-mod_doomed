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

/**
 * Activity settings form for mod_doomed.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_doomed\local\wad;
use mod_doomed\local\wads;

/**
 * Activity settings form for mod_doomed.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_doomed_mod_form extends moodleform_mod {
    /**
     * Whether the current user may choose an uploaded IWAD.
     *
     * @return bool
     */
    protected function can_upload_iwad(): bool {
        return get_config('mod_doomed', 'allowiwadupload')
            && has_capability('mod/doomed:uploadiwad', $this->context);
    }

    /**
     * The IWAD source stored for the activity being edited, or null when adding.
     *
     * @return string|null
     */
    protected function current_iwad_source(): ?string {
        return !empty($this->current->iwadsource) && !empty($this->current->instance)
            ? $this->current->iwadsource : null;
    }

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'gamehdr', get_string('gamesettings', 'mod_doomed'));

        $sources = [wads::SOURCE_FREEDOOM1 => get_string('iwadsource_freedoom1', 'mod_doomed')];
        if ($this->can_upload_iwad() || $this->current_iwad_source() === wads::SOURCE_UPLOAD) {
            $sources[wads::SOURCE_UPLOAD] = get_string('iwadsource_upload', 'mod_doomed');
        }
        $mform->addElement('select', 'iwadsource', get_string('iwadsource', 'mod_doomed'), $sources);
        $mform->addHelpButton('iwadsource', 'iwadsource', 'mod_doomed');
        $mform->setDefault('iwadsource', wads::SOURCE_FREEDOOM1);
        if (!$this->can_upload_iwad()) {
            // Someone without the upload right may keep an existing upload, not replace it.
            if ($this->current_iwad_source() === wads::SOURCE_UPLOAD) {
                $mform->hardFreeze('iwadsource');
            }
        } else {
            $mform->addElement(
                'filemanager',
                'iwadfile',
                get_string('iwadfile', 'mod_doomed'),
                null,
                wads::filemanager_options()
            );
            $mform->addHelpButton('iwadfile', 'iwadfile', 'mod_doomed');
            $mform->hideIf('iwadfile', 'iwadsource', 'neq', wads::SOURCE_UPLOAD);
        }

        $mform->addElement(
            'filemanager',
            'pwadfile',
            get_string('pwadfile', 'mod_doomed'),
            null,
            wads::filemanager_options()
        );
        $mform->addHelpButton('pwadfile', 'pwadfile', 'mod_doomed');

        $mform->addElement('text', 'startmap', get_string('startmap', 'mod_doomed'), ['size' => '8']);
        $mform->setType('startmap', PARAM_ALPHANUM);
        $mform->setDefault('startmap', 'E1M1');
        $mform->addRule('startmap', null, 'required', null, 'client');
        $mform->addHelpButton('startmap', 'startmap', 'mod_doomed');

        $mform->addElement('select', 'skill', get_string('skill', 'mod_doomed'), \mod_doomed\local\options::skills());
        $mform->setDefault('skill', (int) (get_config('mod_doomed', 'defaultskill') ?: 3));

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Prepare the file manager draft areas.
     *
     * @param array $defaultvalues form defaults, changed in place
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $contextid = $this->context instanceof context_module ? $this->context->id : null;
        foreach (['iwadfile' => wads::AREA_IWAD, 'pwadfile' => wads::AREA_PWAD] as $field => $area) {
            $draftitemid = file_get_submitted_draft_itemid($field);
            file_prepare_draft_area($draftitemid, $contextid, 'mod_doomed', $area, 0, wads::filemanager_options());
            $defaultvalues[$field] = $draftitemid;
        }
    }

    /**
     * Validate the WAD choice and the starting map.
     *
     * Draft files are read through the file API: calling get_new_filename()
     * or get_file_content() from validation() recurses through is_validated().
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $source = $data['iwadsource'] ?? wads::SOURCE_FREEDOOM1;
        if (
            $source === wads::SOURCE_UPLOAD && !$this->can_upload_iwad()
                && $this->current_iwad_source() !== wads::SOURCE_UPLOAD
        ) {
            $errors['iwadsource'] = get_string('nopermissiontoupload', 'mod_doomed');
            return $errors;
        }

        $maps = [];
        if ($source === wads::SOURCE_UPLOAD) {
            $iwad = null;
            if (isset($data['iwadfile'])) {
                $iwad = $this->first_draft_file((int) $data['iwadfile']);
            } else if ($this->context instanceof context_module) {
                // Frozen source: the stored upload is kept as it is.
                $iwad = wads::get_area_file($this->context, wads::AREA_IWAD);
            }
            if (!$iwad) {
                $errors['iwadfile'] = get_string('iwadrequired', 'mod_doomed');
                return $errors;
            }
            try {
                $parsed = wad::from_stored_file($iwad);
            } catch (moodle_exception $e) {
                $errors['iwadfile'] = get_string('wadinvalid', 'mod_doomed');
                return $errors;
            }
            if ($parsed->kind !== 'IWAD') {
                $errors['iwadfile'] = get_string('notaniwad', 'mod_doomed');
                return $errors;
            }
            $maps = $parsed->maps;
        } else {
            $maps = wad::from_path(wads::freedoom1_path())->maps;
        }
        $format = $maps ? wad::map_format($maps[0]) : null;

        $pwad = $this->first_draft_file((int) ($data['pwadfile'] ?? 0));
        if ($pwad) {
            try {
                $parsedpwad = wad::from_stored_file($pwad);
            } catch (moodle_exception $e) {
                $errors['pwadfile'] = get_string('wadinvalid', 'mod_doomed');
                return $errors;
            }
            if ($parsedpwad->kind !== 'PWAD') {
                $errors['pwadfile'] = get_string('notapwad', 'mod_doomed');
                return $errors;
            }
            $maps = array_merge($maps, $parsedpwad->maps);
        }

        $startmap = strtoupper(trim($data['startmap'] ?? ''));
        $mapformat = wad::map_format($startmap);
        if ($mapformat === null) {
            $errors['startmap'] = get_string('startmapinvalid', 'mod_doomed');
        } else if ($format !== null && $mapformat !== $format) {
            $errors['startmap'] = get_string(
                'startmapwrongformat',
                'mod_doomed',
                $format === wad::FORMAT_MAPXX ? 'MAP01' : 'E1M1'
            );
        } else if (!in_array($startmap, $maps, true)) {
            $errors['startmap'] = get_string('startmapnotfound', 'mod_doomed', $startmap);
        }
        return $errors;
    }

    /**
     * The first file in a user draft area.
     *
     * @param int $draftitemid draft item id
     * @return stored_file|null
     */
    protected function first_draft_file(int $draftitemid): ?stored_file {
        global $USER;
        if ($draftitemid <= 0) {
            return null;
        }
        $usercontext = context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        return $files ? reset($files) : null;
    }
}
