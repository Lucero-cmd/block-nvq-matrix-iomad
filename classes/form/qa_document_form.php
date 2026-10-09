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
 * Add / edit form for one Quality Assurance document (v26.7.1).
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_nvq_matrix\form;

use block_nvq_matrix\qa;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * The document form.
 */
class qa_document_form extends \moodleform {

    /**
     * File manager options for the master file.
     *
     * @return array
     */
    public static function file_options(): array {
        return [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'maxbytes'       => qa::MAXBYTES,
            'accepted_types' => array_map(function ($e) {
                return '.' . $e;
            }, qa::MASTER_EXTENSIONS),
        ];
    }

    /**
     * Form definition.
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'hdrdetails', get_string('qaformdetails', 'block_nvq_matrix'));

        $mform->addElement('text', 'title', get_string('qadoctitle', 'block_nvq_matrix'), ['size' => 60, 'maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('qadocdescription', 'block_nvq_matrix'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'qadocdescription', 'block_nvq_matrix');

        $mform->addElement('select', 'doctype', get_string('qadoctype', 'block_nvq_matrix'), [
            qa::TYPE_READ => get_string('qatyperead', 'block_nvq_matrix'),
            qa::TYPE_SIGN => get_string('qatypesign', 'block_nvq_matrix'),
        ]);
        $mform->setDefault('doctype', qa::TYPE_SIGN);
        $mform->addHelpButton('doctype', 'qadoctype', 'block_nvq_matrix');

        $mform->addElement('select', 'signoff', get_string('qasignoff', 'block_nvq_matrix'), [
            0 => get_string('qasignoff0', 'block_nvq_matrix'),
            1 => get_string('qasignoff1', 'block_nvq_matrix'),
            2 => get_string('qasignoff2', 'block_nvq_matrix'),
        ]);
        $mform->setType('signoff', PARAM_INT);
        $mform->setDefault('signoff', 1);
        $mform->addHelpButton('signoff', 'qasignoff', 'block_nvq_matrix');
        $mform->hideIf('signoff', 'doctype', 'eq', qa::TYPE_READ);

        $mform->addElement('advcheckbox', 'enabled', get_string('qadocenabled', 'block_nvq_matrix'),
            get_string('qadocenabled_desc', 'block_nvq_matrix'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('header', 'hdrcourses', get_string('qaformcourses', 'block_nvq_matrix'));
        if ($this->is_company_mode()) {
            // Company Manager (IOMAD fork, v27.2.0): only the courses this
            // manager may manage for their own company - see
            // qa::get_manageable_companies(). qa_manage.php re-checks the
            // submitted ids against the same list before saving.
            $options = $this->_customdata['courseoptions'];
        } else {
            $courses = $DB->get_records_select('course', 'id <> :site', ['site' => SITEID], 'fullname ASC', 'id, fullname, shortname');
            $options = [];
            foreach ($courses as $c) {
                $label = format_string($c->fullname, true, ['context' => \context_system::instance(), 'escape' => false]);
                if ($c->shortname !== '' && $c->shortname !== $c->fullname) {
                    $label .= ' (' . format_string($c->shortname, true, ['context' => \context_system::instance(), 'escape' => false]) . ')';
                }
                $options[$c->id] = $label;
            }
        }
        $mform->addElement('autocomplete', 'courseids', get_string('qadoccourses', 'block_nvq_matrix'), $options, [
            'multiple' => true,
            'placeholder' => get_string('qadoccourses_placeholder', 'block_nvq_matrix'),
            'noselectionstring' => get_string('qadoccourses_placeholder', 'block_nvq_matrix'),
        ]);
        $mform->addHelpButton('courseids', $this->is_company_mode() ? 'qamanagecourses' : 'qadoccourses', 'block_nvq_matrix');

        $mform->addElement('header', 'hdrfile', get_string('qaformfile', 'block_nvq_matrix'));
        $mform->addElement('static', 'filelimit', '', get_string('qafilelimit', 'block_nvq_matrix', display_size(qa::max_upload_bytes())));
        $mform->addElement('filemanager', 'masterfile_filemanager', get_string('qamasterfile', 'block_nvq_matrix'), null,
            self::file_options());
        // Importing from an existing File resource by its id is for site
        // administrators only: it does not check who may see that
        // resource, so a Company Manager could otherwise copy another
        // company's file by guessing its id.
        if (!$this->is_company_mode()) {
            $mform->addElement('text', 'importcmid', get_string('qaimportcmid', 'block_nvq_matrix'), ['size' => 10]);
            $mform->setType('importcmid', PARAM_INT);
            $mform->addHelpButton('importcmid', 'qaimportcmid', 'block_nvq_matrix');
        }

        $this->add_action_buttons(true, get_string('qasavedocument', 'block_nvq_matrix'));
    }

    /**
     * Whether the form is being used by a Company Manager for their own
     * company's documents (custom data 'courseoptions' given) rather than
     * by a site administrator.
     *
     * @return bool
     */
    protected function is_company_mode(): bool {
        return is_array($this->_customdata) && array_key_exists('courseoptions', $this->_customdata);
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER, $DB;
        $errors = parent::validation($data, $files);

        if (trim($data['title']) === '') {
            $errors['title'] = get_string('required');
        }
        if (empty($data['courseids'])) {
            $errors['courseids'] = get_string('qaerrornocourses', 'block_nvq_matrix');
        }

        if ($this->is_company_mode() && !empty($data['courseids'])) {
            $allowed = array_map('intval', array_keys($this->_customdata['courseoptions']));
            if (array_diff(array_map('intval', (array) $data['courseids']), $allowed)) {
                $errors['courseids'] = get_string('qaerrornotallowed', 'block_nvq_matrix');
            }
        }

        $importcmid = $this->is_company_mode() ? 0 : (int) ($data['importcmid'] ?? 0);
        if ($importcmid > 0) {
            $found = $DB->get_record_sql("
                SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid AND m.name = 'resource' AND cm.deletioninprogress = 0
            ", ['cmid' => $importcmid]);
            if (!$found) {
                $errors['importcmid'] = get_string('qaimportnotfound', 'block_nvq_matrix');
            }
        } else {
            $fs = get_file_storage();
            $drafts = $fs->get_area_files(
                \context_user::instance($USER->id)->id, 'user', 'draft', (int) $data['masterfile_filemanager'], 'id', false
            );
            if (empty($drafts)) {
                $errors['masterfile_filemanager'] = get_string('qaerrornomaster', 'block_nvq_matrix');
            } else if ($this->is_company_mode()) {
                // A Company Manager's file is checked by content too, the
                // same way a learner's upload is, before anything is saved.
                $err = qa::check_draft_master((int) $data['masterfile_filemanager'], (int) $USER->id);
                if ($err !== '') {
                    $errors['masterfile_filemanager'] = get_string($err, 'block_nvq_matrix');
                }
            }
        }
        return $errors;
    }
}
