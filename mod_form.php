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

use mod_doomed\local\grading;
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

        $this->add_grading_elements();

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Grading section: the standard grade elements plus how Doomed computes grades.
     */
    protected function add_grading_elements(): void {
        $mform = $this->_form;

        $this->standard_grading_coursemodule_elements();

        $mode = $mform->createElement(
            'select',
            'grademode',
            get_string('grademode', 'mod_doomed'),
            grading::mode_options()
        );
        $mform->insertElementBefore($mode, 'grade');
        $mform->addHelpButton('grademode', 'grademode', 'mod_doomed');
        $mform->setDefault('grademode', (int) get_config('mod_doomed', 'defaultgrademode'));
        $mform->hideIf('grade', 'grademode', 'eq', grading::MODE_NONE);
        $mform->hideIf('gradecat', 'grademode', 'eq', grading::MODE_NONE);
        $mform->hideIf('gradepass', 'grademode', 'eq', grading::MODE_NONE);

        $weights = [];
        foreach (['weightkills', 'weightitems', 'weightsecrets'] as $field) {
            $weights[] = $mform->createElement('static', $field . 'label', '', get_string($field, 'mod_doomed'));
            $weights[] = $mform->createElement('text', $field, get_string($field, 'mod_doomed'), ['size' => 3]);
            $mform->setType($field, PARAM_INT);
            $mform->setDefault($field, 1);
        }
        $mform->addGroup($weights, 'weightsgroup', get_string('weights', 'mod_doomed'), ' ', false);
        $mform->addHelpButton('weightsgroup', 'weights', 'mod_doomed');
        $mform->hideIf('weightsgroup', 'grademode', 'neq', grading::MODE_PERCENTAGE);

        $bonus = [
            $mform->createElement('checkbox', 'timebonusenabled', '', get_string('enable')),
            $mform->createElement('text', 'timebonus', get_string('timebonus', 'mod_doomed'), ['size' => 3]),
        ];
        $mform->setType('timebonus', PARAM_INT);
        $mform->setDefault('timebonus', 10);
        $mform->addGroup($bonus, 'timebonusgroup', get_string('timebonus', 'mod_doomed'), ' ', false);
        $mform->addHelpButton('timebonusgroup', 'timebonus', 'mod_doomed');
        $mform->hideIf('timebonusgroup', 'grademode', 'neq', grading::MODE_PERCENTAGE);
        $mform->disabledIf('timebonus', 'timebonusenabled', 'notchecked');

        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mod_doomed'), grading::method_options());
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_doomed');
        $mform->setDefault('grademethod', grading::METHOD_HIGHEST);
        $mform->hideIf('grademethod', 'grademode', 'eq', grading::MODE_NONE);
    }

    /**
     * Custom completion rules.
     *
     * @return string[] names of the added elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $mapel = 'completionmap' . $suffix;
        $mform->addElement('checkbox', $mapel, '', get_string('completionmap', 'mod_doomed'));
        $mform->addHelpButton($mapel, 'completionmap', 'mod_doomed');

        $enabledel = 'completionmingradeenabled' . $suffix;
        $gradeel = 'completionmingrade' . $suffix;
        $groupel = 'completionmingradegroup' . $suffix;
        $group = [
            $mform->createElement('checkbox', $enabledel, '', get_string('completionmingrade', 'mod_doomed')),
            $mform->createElement('float', $gradeel, get_string('completionmingrade', 'mod_doomed'), ['size' => 5]),
        ];
        $mform->addGroup($group, $groupel, '', ' ', false);
        $mform->disabledIf($gradeel, $enabledel, 'notchecked');

        return [$mapel, $groupel];
    }

    /**
     * Whether any custom completion rule is enabled.
     *
     * @param array $data submitted data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionmap' . $suffix])
            || (!empty($data['completionmingradeenabled' . $suffix]) && !empty($data['completionmingrade' . $suffix]));
    }

    /**
     * Zero the values of unticked optional settings.
     *
     * @param stdClass $data submitted data, changed in place
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (empty($data->timebonusenabled) || (int) ($data->grademode ?? 0) !== grading::MODE_PERCENTAGE) {
            $data->timebonus = 0;
        }
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $completion = $data->{'completion' . $suffix} ?? COMPLETION_TRACKING_NONE;
            $automatic = $completion == COMPLETION_TRACKING_AUTOMATIC;
            if (!$automatic || empty($data->{'completionmap' . $suffix})) {
                $data->{'completionmap' . $suffix} = 0;
            }
            if (!$automatic || empty($data->{'completionmingradeenabled' . $suffix})) {
                $data->{'completionmingrade' . $suffix} = 0;
            }
        }
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

        $defaultvalues['timebonusenabled'] = !empty($defaultvalues['timebonus']) ? 1 : 0;
        if (empty($defaultvalues['timebonus'])) {
            $defaultvalues['timebonus'] = 10;
        }
        $suffix = $this->get_suffix();
        $defaultvalues['completionmingradeenabled' . $suffix] =
            !empty($defaultvalues['completionmingrade' . $suffix]) ? 1 : 0;
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
        $errors += $this->validate_grading($data);

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
     * Validate the grading settings and the minimum grade completion rule.
     *
     * @param array $data submitted data
     * @return array errors
     */
    protected function validate_grading(array $data): array {
        $errors = [];
        $mode = (int) ($data['grademode'] ?? grading::MODE_NONE);
        $maxgrade = (float) ($data['grade'] ?? 0);
        if ($mode !== grading::MODE_NONE && $maxgrade <= 0) {
            $errors['grade'] = get_string('gradepointsrequired', 'mod_doomed');
        }
        if ($mode === grading::MODE_PERCENTAGE) {
            $sum = 0;
            foreach (['weightkills', 'weightitems', 'weightsecrets'] as $field) {
                $weight = (int) ($data[$field] ?? 0);
                if ($weight < 0 || $weight > 100) {
                    $errors['weightsgroup'] = get_string('weightsrange', 'mod_doomed');
                }
                $sum += $weight;
            }
            if ($sum <= 0 && empty($errors['weightsgroup'])) {
                $errors['weightsgroup'] = get_string('weightsrange', 'mod_doomed');
            }
            if (!empty($data['timebonusenabled'])) {
                $bonus = (int) ($data['timebonus'] ?? 0);
                if ($bonus < 1 || $bonus > 100) {
                    $errors['timebonusgroup'] = get_string('timebonusrange', 'mod_doomed');
                }
            }
        }

        $suffix = $this->get_suffix();
        // Core's "receive a grade" rules can never be met by an ungraded activity.
        if ($mode === grading::MODE_NONE) {
            foreach (['completionusegrade', 'completionpassgrade'] as $rule) {
                if (!empty($data[$rule . $suffix])) {
                    $errors[$rule . $suffix] = get_string('completionmingradeneedsgrade', 'mod_doomed');
                }
            }
        }
        if (!empty($data['completionmingradeenabled' . $suffix])) {
            $mingrade = (float) ($data['completionmingrade' . $suffix] ?? 0);
            if ($mode === grading::MODE_NONE) {
                $errors['completionmingradegroup' . $suffix] = get_string('completionmingradeneedsgrade', 'mod_doomed');
            } else if ($mingrade <= 0 || ($maxgrade > 0 && $mingrade > $maxgrade)) {
                $errors['completionmingradegroup' . $suffix] = get_string('completionmingraderange', 'mod_doomed');
            }
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
        // Only a file file_save_draft_area_files() will keep: in the root
        // folder (subdirs are off) and within the size limit. Anything else
        // in the draft is discarded on save, so it must not be what passes
        // validation.
        $maxbytes = (int) wads::filemanager_options()['maxbytes'];
        $usercontext = context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        $kept = array_filter($files, fn(stored_file $file) => $file->get_filepath() === '/'
            && ($maxbytes <= 0 || $file->get_filesize() <= $maxbytes));
        return $kept ? reset($kept) : null;
    }
}
