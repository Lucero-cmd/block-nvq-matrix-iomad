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
 * Serves Quality Assurance document files (v26.7.1).
 *
 * The only way to read these files. pluginfile.php cannot serve them
 * (blocks are only served there from a block context), so this script
 * does its own access check and then hands the stored file to Moodle's
 * send_stored_file().
 *
 *   kind=master   a master document. Anyone enrolled in (or with staff
 *                 access to) a course the document applies to.
 *   kind=learner  a learner's uploaded copy. That learner, or staff
 *                 with block/nvq_matrix:viewall in that course.
 *   kind=counter  the Assessor's countersigned copy. Same rule as learner.
 *   kind=iqa      the final copy carrying the IQA signature. Same rule.
 *
 * A learner can therefore never open another learner's upload, whatever
 * ids they type. Read documents open in the browser when it can show
 * them; Sign and return masters and anything the browser cannot show
 * (Word files) are downloaded.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\qa;

require_login();

$kind     = required_param('kind', PARAM_ALPHA);
$id       = required_param('id', PARAM_INT);
$fileid   = required_param('fileid', PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

$fs = get_file_storage();
$file = $fs->get_file_by_id($fileid);
if (!$file || $file->is_directory()) {
    send_file_not_found();
}

$forcedownload = (bool) $download;

if ($kind === 'master') {
    $doc = $DB->get_record('block_nvq_matrix_qadocs', ['id' => $id]);
    if (!$doc || (!$doc->enabled && !is_siteadmin())) {
        send_file_not_found();
    }
    if ($file->get_contextid() != context_system::instance()->id
            || $file->get_component() !== 'block_nvq_matrix'
            || $file->get_filearea() !== qa::AREA_MASTER
            || (int) $file->get_itemid() !== (int) $doc->id) {
        send_file_not_found();
    }
    if (!qa::can_open_master((int) $doc->id)) {
        send_file_not_found();
    }
    if ((int) $doc->doctype === qa::TYPE_SIGN) {
        $forcedownload = true;
    }
} else if ($kind === 'learner' || $kind === 'counter' || $kind === 'iqa') {
    $sub = $DB->get_record('block_nvq_matrix_qasubs', ['id' => $id]);
    if (!$sub) {
        send_file_not_found();
    }
    $ctx = context_course::instance((int) $sub->courseid, IGNORE_MISSING);
    if (!$ctx) {
        send_file_not_found();
    }
    $area = ($kind === 'learner') ? qa::AREA_LEARNER : (($kind === 'iqa') ? qa::AREA_IQA : qa::AREA_COUNTER);
    if ($file->get_contextid() != $ctx->id
            || $file->get_component() !== 'block_nvq_matrix'
            || $file->get_filearea() !== $area
            || (int) $file->get_itemid() !== (int) $sub->id) {
        send_file_not_found();
    }
    // The same rule as the checklist itself decides who may open it.
    $access = qa::get_access($ctx, (int) $sub->userid);
    if (!$access || !$access->canview) {
        send_file_not_found();
    }
} else {
    send_file_not_found();
}

if (!qa::is_inline($file->get_filename())) {
    $forcedownload = true;
}

\core\session\manager::write_close();
// Private, never cached by a shared proxy; lifetime 0 so each open is
// checked again.
send_stored_file($file, 0, 0, $forcedownload, ['cacheability' => 'private']);
