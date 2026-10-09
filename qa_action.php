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
 * AJAX endpoint for Quality Assurance document actions (v26.7.1).
 *
 *   action=upload  A learner uploads or replaces their own signed copy.
 *                  The learner is always the logged-in user - the
 *                  studentid sent by the page is ignored for this action.
 *   action=accept  Staff (block/nvq_matrix:qareview) accept an upload,
 *                  with the countersigned copy where the document needs
 *                  one.
 *   action=return  Staff return an upload with a reason.
 *   action=adminupload  A site administrator uploads for a learner (optionally
 *                  marked as already accepted). Nothing records who did it
 *                  on any page.
 *   action=delete  A site administrator deletes a submission and its files.
 *
 * Every rule is re-checked here (capability, enrolment, document applies
 * to the course, withdrawn lock, file type, size and content); nothing is
 * trusted from the page.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\qa;

require_login();

header('Content-Type: application/json');

/**
 * Sends a JSON response and stops.
 *
 * @param bool   $success
 * @param string $stringid Lang string identifier (for errors).
 * @param array  $extra
 */
function nvq_qa_respond(bool $success, string $stringid = '', array $extra = []): void {
    if (!$success) {
        http_response_code(400);
    }
    $out = ['success' => $success] + $extra;
    if ($stringid !== '') {
        $out['error'] = get_string($stringid, 'block_nvq_matrix');
    }
    echo json_encode($out);
    die();
}

// A file bigger than PHP's post_max_size arrives as an empty request.
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
    nvq_qa_respond(false, 'qaerrortoolarge');
}

try {
    require_sesskey();
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => get_string('qaerrorsession', 'block_nvq_matrix')]);
    die();
}

$action   = required_param('action', PARAM_ALPHA);
$docid    = required_param('docid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

try {
    $doc = $DB->get_record('block_nvq_matrix_qadocs', ['id' => $docid]);
    $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
    if (!$doc || !$coursecontext || !$DB->record_exists('block_nvq_matrix_qadoccourses', ['docid' => $docid, 'courseid' => $courseid])) {
        nvq_qa_respond(false, 'qaerrornotallowed');
    }

    if ($action === 'upload') {
        $access = qa::get_access($coursecontext, (int) $USER->id);
        if (!$access || !$access->canupload) {
            nvq_qa_respond(false, 'qaerrornotallowed');
        }
        $uploads = qa::collect_uploads('files');
        $err = qa::validate_uploads($uploads);
        if ($err !== '') {
            nvq_qa_respond(false, $err);
        }
        $err = qa::learner_upload($doc, $courseid, (int) $USER->id, $uploads);
        if ($err !== '') {
            nvq_qa_respond(false, $err);
        }
        nvq_qa_respond(true);
    }

    if ($action === 'accept' || $action === 'return') {
        $studentid = required_param('studentid', PARAM_INT);
        $access = qa::get_access($coursecontext, $studentid);
        if (!$access || !$access->canreview) {
            nvq_qa_respond(false, 'qaerrornotallowed');
        }
        // The document must be this learner's: the platform's or their own
        // company's (IOMAD fork, v27.2.0).
        if (!qa::doc_applies_to_learner($doc, $courseid, $studentid)) {
            nvq_qa_respond(false, 'qaerrornotallowed');
        }
        $sub = qa::get_submission($docid, $courseid, $studentid);
        if (!$sub) {
            nvq_qa_respond(false, 'qaerrornotallowed');
        }

        if ($action === 'accept') {
            $uploads = qa::collect_uploads('files');
            if (!empty($uploads)) {
                $err = qa::validate_uploads($uploads);
                if ($err !== '') {
                    nvq_qa_respond(false, $err);
                }
            }
            $err = qa::accept($doc, $sub, (int) $USER->id, $uploads);
        } else {
            $err = qa::return_submission($doc, $sub, (int) $USER->id, optional_param('reason', '', PARAM_TEXT));
        }
        if ($err !== '') {
            nvq_qa_respond(false, $err);
        }
        nvq_qa_respond(true);
    }

    if ($action === 'adminupload' || $action === 'delete') {
        if (!is_siteadmin()) {
            nvq_qa_respond(false, 'qaerrornotallowed');
        }
        $studentid = required_param('studentid', PARAM_INT);
        $access = qa::get_access($coursecontext, $studentid);
        if (!$access || !$access->canadminupload) {
            nvq_qa_respond(false, 'qaerrornotallowed');
        }
        if (!qa::doc_applies_to_learner($doc, $courseid, $studentid)) {
            nvq_qa_respond(false, 'qaerrornotallowed');
        }
        if ($action === 'delete') {
            $sub = qa::get_submission($docid, $courseid, $studentid);
            if (!$sub) {
                nvq_qa_respond(false, 'qaerrornotallowed');
            }
            $err = qa::delete_submission($sub, (int) $USER->id);
        } else {
            $uploads = qa::collect_uploads('files');
            $err = qa::validate_uploads($uploads);
            if ($err === '') {
                $err = qa::admin_upload($doc, $courseid, $studentid, $uploads, (bool) optional_param('accepted', 0, PARAM_BOOL), (int) $USER->id);
            }
        }
        if ($err !== '') {
            nvq_qa_respond(false, $err);
        }
        nvq_qa_respond(true);
    }

    nvq_qa_respond(false, 'qaerrornotallowed');
} catch (\Throwable $e) {
    debugging('block_nvq_matrix QA action failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    nvq_qa_respond(false, 'qaerrorfailed');
}
