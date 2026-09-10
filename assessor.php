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
 * AJAX endpoint for setting the designated Assessor for a course.
 *
 * Accepts a POST with courseid, assessorid. Gated on
 * block/nvq_matrix:manageassessor - deliberately withheld from the
 * 'teacher' archetype (IQA reviewers on this site), same reasoning as
 * grade.php/sample.php/final_status.php. The chosen assessorid must
 * itself currently hold block/nvq_matrix:grade in the course - checked
 * both here (so the dropdown-population list and the accepted value
 * agree) and again inside matrix_data::save_assessor() (defence in
 * depth against a stale submission).
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\matrix_data;

require_login();

header('Content-Type: application/json');

try {
    require_sesskey();
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => get_string('assessornopermission', 'block_nvq_matrix')]);
    die();
}

$courseid   = required_param('courseid', PARAM_INT);
$assessorid = required_param('assessorid', PARAM_INT);

$response = ['success' => false];

if ($courseid <= 0 || $assessorid < 0) {
    $response['error'] = get_string('assessorerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

if (!$coursecontext || !has_capability('block/nvq_matrix:manageassessor', $coursecontext)) {
    http_response_code(403);
    $response['error'] = get_string('assessornopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// Group isolation (v27.0.1, IOMAD fork): block_nvq_matrix_assessor
// holds ONE assessor slot per COURSE, not per company. Now that
// assessor_manage.php no longer reveals who holds this slot when
// they're outside the caller's group (see its own matching fix), a
// restricted viewer sees an out-of-group designation as "Not set" -
// without this check they could then clear or overwrite it, silently
// wiping out a different company's real assessor the moment its
// identity became invisible to them. Only blocks the action when
// there's an existing designee this caller can't see; a genuinely
// empty slot, or one already within the caller's own group, is
// completely unaffected - an unrestricted (accessallgroups) caller is
// never blocked either way.
$existingassessorrow = matrix_data::get_assessor($courseid);
if ($existingassessorrow
    && !matrix_data::viewer_can_access_student($courseid, (int) $existingassessorrow->userid)
) {
    http_response_code(409);
    $response['error'] = get_string('assessorerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// assessorid=0 is the dropdown's "Not set" option — clears the
// designation rather than being rejected as an invalid candidate. Was
// previously treated as a plain validation error, silently blocking the
// only way to unset an already-designated assessor.
if ($assessorid === 0) {
    try {
        matrix_data::clear_assessor($courseid);
        $response['success'] = true;
        $response['message'] = get_string('assessorcleared', 'block_nvq_matrix');
    } catch (\Throwable $e) {
        $response['error'] = get_string('assessorerror', 'block_nvq_matrix');
    }
    echo json_encode($response);
    die();
}

// The candidate must currently hold block/nvq_matrix:grade in this
// course - matches the dropdown's own population list
// (matrix_data::get_candidate_assessors()) so the accepted value can
// never diverge from what was actually offered.
//
// Group isolation (v27.0.0, IOMAD fork): get_candidate_assessors() now
// group-scopes the dropdown itself (a restricted manager only sees
// their own company's teachers), so this check must match it exactly -
// otherwise a stale/crafted POST could still designate a candidate
// from a different company that the dropdown itself would never have
// offered. Same rationale as grade.php's equivalent check.
if (!has_capability('block/nvq_matrix:grade', $coursecontext, $assessorid)
    || !matrix_data::viewer_can_access_student($courseid, $assessorid)
) {
    $response['error'] = get_string('assessorerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

try {
    matrix_data::save_assessor($courseid, $assessorid);
    $response['success'] = true;
    $response['message'] = get_string('assessorsaved', 'block_nvq_matrix');
} catch (\Throwable $e) {
    $response['error'] = get_string('assessorerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
