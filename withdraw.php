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
 * AJAX endpoint for marking a learner as withdrawn from a course, or
 * reinstating them (v26.6.28).
 *
 * Two actions, both gated on block/nvq_matrix:withdraw scoped to the
 * submitted course:
 *   - action=withdraw  : the learner is shown as Withdrawn and their
 *                        matrix for this course is locked for everyone.
 *   - action=reinstate : the lock is lifted and the learner returns to
 *                        their previous status (On-going or Completed),
 *                        since withdrawal never touched anything else.
 * Accepts an optional 'actiondate' (YYYY-MM-DD), following the plugin's
 * flat backdating rule: the entered date if given, otherwise today.
 *
 * The learner must still be enrolled on the course - withdrawal is
 * separate from Moodle enrolment, and an unenrolled learner is already
 * handled by the existing Archived view.
 *
 * Group isolation (v27.0.0 pattern, IOMAD fork): same check as every
 * other write endpoint in this plugin - see grade.php's equivalent
 * check for the full rationale. The Company Manager's course roles
 * (companycourseeditor and companycoursenoneditor) hold :withdraw (see
 * db/access.php) but are still subject to this same group check, same
 * as every other role - a Company Manager cannot withdraw a learner
 * outside their own group just because they hold this capability.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
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
    echo json_encode(['success' => false, 'error' => get_string('withdrawnopermission', 'block_nvq_matrix')]);
    die();
}

$studentid     = required_param('studentid', PARAM_INT);
$courseid      = required_param('courseid', PARAM_INT);
$action        = required_param('action', PARAM_ALPHA); // 'withdraw' | 'reinstate'
$actiondatestr = optional_param('actiondate', '', PARAM_TEXT);

$response = ['success' => false];

if ($studentid <= 0 || $courseid <= 0 || !in_array($action, ['withdraw', 'reinstate'], true)) {
    $response['error'] = get_string('withdrawerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// Capability + enrolment + group isolation check, scoped to the
// submitted course - same pattern as every other write endpoint in
// this plugin (e.g. final_status.php).
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

if (!$coursecontext
    || !has_capability('block/nvq_matrix:withdraw', $coursecontext)
    || !is_enrolled($coursecontext, $studentid)
    || has_capability('block/nvq_matrix:viewall', $coursecontext, $studentid)
    || !matrix_data::viewer_can_access_student($courseid, $studentid)
) {
    http_response_code(403);
    $response['error'] = get_string('withdrawnopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

try {
    $currentlywithdrawn = matrix_data::is_withdrawn($studentid, $courseid);
    $wantwithdrawn = ($action === 'withdraw');

    // Refuse a no-op (e.g. a second click, or two staff acting on the
    // same learner at once) rather than adding a meaningless duplicate
    // row to the log.
    if ($currentlywithdrawn === $wantwithdrawn) {
        $response['error'] = get_string('withdrawalreadystate', 'block_nvq_matrix');
        echo json_encode($response);
        die();
    }

    // A learner with a recorded Pass is Completed, not withdrawable (v26.7.4).
    // The button is hidden in the matrix; this stops a direct request too.
    if ($wantwithdrawn) {
        $passvalue = $DB->get_field('block_nvq_matrix_status', 'status', ['studentid' => $studentid, 'courseid' => $courseid]);
        if ($passvalue !== false && $passvalue !== null && (int) $passvalue === 1) {
            $response['error'] = get_string('withdrawcompleted', 'block_nvq_matrix');
            echo json_encode($response);
            die();
        }
    }

    matrix_data::record_withdrawal(
        $studentid,
        $courseid,
        $wantwithdrawn,
        matrix_data::parse_comment_date($actiondatestr)
    );
    $response['success'] = true;
    $response['message'] = get_string($wantwithdrawn ? 'withdrawsaved' : 'reinstatesaved', 'block_nvq_matrix');
} catch (\Throwable $e) {
    $response['error'] = get_string('withdrawerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
