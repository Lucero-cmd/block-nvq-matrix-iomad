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
 * Quality Assurance documents page (v26.7.1).
 *
 * Two views of one course:
 *   - checklist: one learner's documents. A learner sees only their own
 *     (studentid is forced to the logged-in user for anyone without
 *     staff access); staff open it for a chosen learner and can accept
 *     or return uploads.
 *   - tracker: staff only, no studentid - every learner against every
 *     Sign and return document.
 *
 * No upload, acceptance or return date or time is shown anywhere.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\matrix_data;
use block_nvq_matrix\qa;
use block_nvq_matrix\qa_ui;

$courseid  = required_param('courseid', PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$ctx = context_course::instance($course->id);

$viewall = has_capability('block/nvq_matrix:viewall', $ctx);
$trackermode = ($viewall && $studentid <= 0);
if (!$viewall || $studentid <= 0) {
    // Anyone without staff access only ever sees their own checklist.
    if (!$viewall) {
        $studentid = (int) $USER->id;
    }
}

$pageparams = ['courseid' => $course->id];
if ($studentid > 0 && !$trackermode) {
    $pageparams['studentid'] = $studentid;
}
$PAGE->set_url(new moodle_url('/blocks/nvq_matrix/qa.php', $pageparams));
$PAGE->set_context($ctx);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('qapagetitle', 'block_nvq_matrix'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $ctx]));
$PAGE->add_body_class('nvq-qa-page');

$coursename = format_string($course->fullname, true, ['context' => $ctx, 'escape' => false]);
$docs = qa::get_course_documents($course->id);
$signdocs = array_filter($docs, function ($d) {
    return (int) $d->doctype === qa::TYPE_SIGN;
});
$readdocs = array_filter($docs, function ($d) {
    return (int) $d->doctype === qa::TYPE_READ;
});

// Where "back" goes: the matrix, on this learner and course when known.
$backparams = [];
if ($studentid > 0 && $studentid !== (int) $USER->id) {
    $backparams = ['nvq_matrix_student' => $studentid, 'nvq_matrix_course' => $course->id];
}
$backurl = new moodle_url('/blocks/nvq_matrix/view.php', $backparams);

$namefields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';

echo $OUTPUT->header();
echo '<div class="nvq-qa">';

echo html_writer::link($backurl, qa_ui::icon('back') . get_string('backtomatrix', 'block_nvq_matrix'), ['class' => 'nvq-qa-back']);

// =====================================================================
// TRACKER (staff, whole course)
// =====================================================================
if ($trackermode) {
    $learners = qa::get_tracker_learners($ctx);
    $subs = qa::get_course_submissions($course->id);
    $withdrawn = matrix_data::get_withdrawn_map(array_keys($learners), [$course->id]);

    $totals = ['learners' => count($learners), 'awaiting' => 0, 'returned' => 0, 'complete' => 0];
    $rows = [];
    foreach ($learners as $uid => $u) {
        $mine = $subs[$uid] ?? [];
        $sum = qa::summarise($signdocs, $mine);
        $totals['awaiting'] += $sum->awaiting;
        $totals['returned'] += $sum->returned;
        if ($sum->complete) {
            $totals['complete']++;
        }
        $rows[] = ['user' => $u, 'subs' => $mine, 'sum' => $sum, 'withdrawn' => isset($withdrawn[$uid][$course->id])];
    }

    echo '<header class="nvq-qa-head"><div>';
    echo '<h2 class="nvq-qa-h2">' . get_string('qatrackertitle', 'block_nvq_matrix') . '</h2>';
    echo '<p class="nvq-qa-muted">' . s($coursename) . '</p>';
    echo '</div></header>';

    if (empty($signdocs)) {
        echo '<div class="nvq-qa-empty">'
            . '<h3>' . get_string('qaemptytitle', 'block_nvq_matrix') . '</h3>'
            . '<p>' . get_string('qatrackernosign', 'block_nvq_matrix') . '</p></div>';
        if (!empty($readdocs)) {
            echo html_writer::link(new moodle_url('/blocks/nvq_matrix/qa.php', ['courseid' => $course->id, 'studentid' => $USER->id]),
                get_string('qaviewreaddocs', 'block_nvq_matrix'), ['class' => 'nvq-qa-btn nvq-qa-btn--ghost']);
        }
        echo '</div>';
        echo $OUTPUT->footer();
        die();
    }

    echo '<div class="nvq-qa-stats">';
    foreach ([
        [$totals['learners'], 'qastatlearners'],
        [$totals['awaiting'], 'qastatawaiting'],
        [$totals['returned'], 'qastatreturned'],
        [$totals['complete'], 'qastatcomplete'],
    ] as [$n, $label]) {
        echo '<div class="nvq-qa-stat"><span class="nvq-qa-stat-n">' . (int) $n . '</span><span class="nvq-qa-stat-l">'
            . get_string($label, 'block_nvq_matrix') . '</span></div>';
    }
    echo '</div>';

    echo '<div class="nvq-qa-toolbar">';
    echo '<input type="search" class="nvq-qa-search" id="nvq-qa-search" placeholder="'
        . s(get_string('qasearchlearners', 'block_nvq_matrix')) . '" aria-label="' . s(get_string('qasearchlearners', 'block_nvq_matrix')) . '">';
    echo '<div class="nvq-qa-filters" role="group" aria-label="' . s(get_string('qafilterlabel', 'block_nvq_matrix')) . '">';
    foreach (['all' => 'qafilterall', 'needs' => 'qafilterneeds', 'returned' => 'qafilterreturned',
              'incomplete' => 'qafilterincomplete', 'complete' => 'qafiltercomplete'] as $key => $str) {
        echo '<button type="button" class="nvq-qa-filter' . ($key === 'all' ? ' is-active' : '') . '" data-filter="' . $key . '">'
            . get_string($str, 'block_nvq_matrix') . '</button>';
    }
    echo '</div></div>';

    if (empty($rows)) {
        echo '<div class="nvq-qa-empty"><h3>' . get_string('qatrackernolearners', 'block_nvq_matrix') . '</h3></div>';
    } else {
        echo '<div class="nvq-qa-tablewrap"><table class="nvq-qa-table" id="nvq-qa-table"><thead><tr>';
        echo '<th class="nvq-qa-th-learner" scope="col">' . get_string('qalearner', 'block_nvq_matrix') . '</th>';
        echo '<th scope="col" class="nvq-qa-th-prog">' . get_string('qaprogress', 'block_nvq_matrix') . '</th>';
        foreach ($signdocs as $d) {
            echo '<th scope="col" class="nvq-qa-th-doc" title="' . s(format_string($d->title, true, ['context' => $ctx, 'escape' => false])) . '"><span>'
                . s(format_string($d->title, true, ['context' => $ctx, 'escape' => false])) . '</span></th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $u = $r['user'];
            $sum = $r['sum'];
            $state = [];
            if ($sum->awaiting > 0) {
                $state[] = 'needs';
            }
            if ($sum->returned > 0) {
                $state[] = 'returned';
            }
            $state[] = $sum->complete ? 'complete' : 'incomplete';
            $pct = $sum->total ? (int) round(100 * $sum->accepted / $sum->total) : 0;
            $learnerurl = new moodle_url('/blocks/nvq_matrix/qa.php', ['courseid' => $course->id, 'studentid' => $u->id]);
            echo '<tr data-name="' . s(core_text::strtolower(fullname($u))) . '" data-state="' . implode(' ', $state) . '">';
            echo '<th scope="row" class="nvq-qa-td-learner">' . html_writer::link($learnerurl, s(fullname($u)));
            if ($r['withdrawn']) {
                echo ' <span class="nvq-qa-tag">' . get_string('withdrawnstate', 'block_nvq_matrix') . '</span>';
            }
            echo '</th>';
            echo '<td class="nvq-qa-td-prog"><div class="nvq-qa-bar" role="img" aria-label="' . s($sum->accepted . '/' . $sum->total)
                . '"><span style="width:' . $pct . '%"></span></div><span class="nvq-qa-bar-n">' . $sum->accepted . '/' . $sum->total . '</span></td>';
            foreach ($signdocs as $d) {
                $st = qa::status_of($r['subs'][(int) $d->id] ?? null);
                echo '<td class="nvq-qa-td-doc">' . html_writer::link($learnerurl, qa_ui::status_pill($st),
                    ['class' => 'nvq-qa-cell', 'title' => s(fullname($u))]) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="nvq-qa-noresults" id="nvq-qa-noresults" hidden>' . get_string('qanoresults', 'block_nvq_matrix') . '</p>';
    }
    ?>
<script>
(function() {
    var search = document.getElementById('nvq-qa-search');
    var table = document.getElementById('nvq-qa-table');
    if (!table) { return; }
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
    var empty = document.getElementById('nvq-qa-noresults');
    var filter = 'all';
    function apply() {
        var q = (search.value || '').toLowerCase().trim();
        var shown = 0;
        rows.forEach(function(r) {
            var okName = !q || (r.getAttribute('data-name') || '').indexOf(q) !== -1;
            var okState = filter === 'all' || (' ' + r.getAttribute('data-state') + ' ').indexOf(' ' + filter + ' ') !== -1;
            var show = okName && okState;
            r.hidden = !show;
            if (show) { shown++; }
        });
        if (empty) { empty.hidden = shown !== 0; }
    }
    search.addEventListener('input', apply);
    document.querySelectorAll('.nvq-qa-filter').forEach(function(b) {
        b.addEventListener('click', function() {
            document.querySelectorAll('.nvq-qa-filter').forEach(function(x) { x.classList.remove('is-active'); });
            b.classList.add('is-active');
            filter = b.getAttribute('data-filter');
            apply();
        });
    });
})();
</script>
    <?php
    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

// =====================================================================
// CHECKLIST (one learner)
// =====================================================================
$access = qa::get_access($ctx, $studentid);
if (!$access) {
    echo $OUTPUT->notification(get_string('qaerrornotallowed', 'block_nvq_matrix'), 'notifyproblem');
    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], $namefields, MUST_EXIST);
$iswithdrawn = matrix_data::is_withdrawn($studentid, (int) $course->id);
$canupload = $access->canupload && !$iswithdrawn;
$canreview = $access->canreview && !$iswithdrawn;
$canadmin = $access->canadminupload && !$iswithdrawn;
$candelete = $access->candelete;
$caniqasign = qa::can_iqa_sign($ctx);
$subs = qa::get_learner_submissions((int) $course->id, $studentid);
$sum = qa::summarise($docs, $subs);
$maxbytes = qa::max_upload_bytes();

// Names of the staff who checked, shown instead of any date. An upload an
// administrator made on the learner's behalf has no reviewer on record, so
// no name is shown for it.
$reviewerids = [];
foreach ($subs as $s) {
    if (!empty($s->reviewedby)) {
        $reviewerids[(int) $s->reviewedby] = (int) $s->reviewedby;
    }
}
$reviewers = $reviewerids ? $DB->get_records_list('user', 'id', array_values($reviewerids), '', $namefields) : [];

// Header.
echo '<header class="nvq-qa-head"><div>';
echo '<h2 class="nvq-qa-h2">' . s($coursename) . '</h2>';
if ($access->isself) {
    echo '<p class="nvq-qa-muted">' . get_string('qalearnerintro', 'block_nvq_matrix') . '</p>';
} else {
    echo '<p class="nvq-qa-muted">' . get_string('qalearnerlabel', 'block_nvq_matrix', s(fullname($student))) . '</p>';
}
if ($sum->total > 0) {
    $pct = (int) round(100 * $sum->accepted / $sum->total);
    echo '<div class="nvq-qa-progressline"><div class="nvq-qa-bar" role="img" aria-label="'
        . s(get_string('qaringlabel', 'block_nvq_matrix', ['done' => $sum->accepted, 'total' => $sum->total]))
        . '"><span style="width:' . $pct . '%"></span></div><strong>' . $sum->accepted . '/' . $sum->total . '</strong> '
        . '<span class="nvq-qa-muted">' . get_string('qaringcaption', 'block_nvq_matrix') . '</span></div>';
}
echo '</div>';
if ($viewall) {
    echo html_writer::link(new moodle_url('/blocks/nvq_matrix/qa.php', ['courseid' => $course->id]),
        get_string('qatrackerlink', 'block_nvq_matrix'), ['class' => 'nvq-qa-btn nvq-qa-btn--ghost']);
}
echo '</header>';

if ($iswithdrawn) {
    echo '<div class="nvq-qa-banner">' . get_string('qawithdrawnnote', 'block_nvq_matrix') . '</div>';
}

if (empty($docs)) {
    echo '<div class="nvq-qa-empty">'
        . '<h3>' . get_string('qaemptytitle', 'block_nvq_matrix') . '</h3>'
        . '<p>' . get_string('qaemptylearner', 'block_nvq_matrix') . '</p></div>';
    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

// ---- Read documents -------------------------------------------------
if (!empty($readdocs)) {
    echo '<section class="nvq-qa-section"><div class="nvq-qa-section-head"><h3>' . get_string('qasectionread', 'block_nvq_matrix') . '</h3>'
        . '<p class="nvq-qa-muted">' . get_string('qasectionreadhint', 'block_nvq_matrix') . '</p></div><ul class="nvq-qa-readlist">';
    foreach ($readdocs as $d) {
        $master = qa::get_master_file((int) $d->id);
        $title = format_string($d->title, true, ['context' => $ctx, 'escape' => false]);
        echo '<li class="nvq-qa-readitem"><div class="nvq-qa-readtext"><h4>' . s($title) . '</h4>';
        if (trim((string) $d->description) !== '') {
            echo '<p class="nvq-qa-muted">' . s($d->description) . '</p>';
        }
        echo '</div>';
        if ($master) {
            $inline = qa::is_inline($master->get_filename());
            echo html_writer::link(qa::file_url('master', (int) $d->id, $master),
                qa_ui::icon($inline ? 'eye' : 'download') . '<span>' . get_string($inline ? 'qaopen' : 'qadownload', 'block_nvq_matrix') . '</span>',
                ['class' => 'nvq-qa-btn nvq-qa-btn--ghost', 'target' => '_blank', 'rel' => 'noopener']);
        } else {
            echo '<span class="nvq-qa-muted">' . get_string('qanotavailable', 'block_nvq_matrix') . '</span>';
        }
        echo '</li>';
    }
    echo '</ul></section>';
}

/**
 * Renders one list of stored files.
 *
 * @param string $kind learner|counter|iqa
 * @param stdClass $sub
 * @param stored_file[] $files
 * @return string
 */
function nvq_qa_filelist(string $kind, stdClass $sub, array $files): string {
    $out = '<ul class="nvq-qa-files">';
    foreach ($files as $f) {
        $out .= '<li>' . qa_ui::icon('file') . html_writer::link(qa::file_url($kind, (int) $sub->id, $f), s($f->get_filename()),
            ['target' => '_blank', 'rel' => 'noopener']) . '<span class="nvq-qa-muted">' . display_size($f->get_filesize()) . '</span></li>';
    }
    return $out . '</ul>';
}

/**
 * Renders the upload form (dropzone, picked files, progress, submit).
 *
 * @param stdClass $d Document.
 * @param bool $admin Administrator uploading for the learner.
 * @param bool $replace Whether files already exist.
 * @param int $maxbytes
 * @return string
 */
function nvq_qa_uploadform(stdClass $d, bool $admin, bool $replace, int $maxbytes): string {
    $label = $replace ? 'qareplace' : 'qadropzone';
    $out = '<form class="nvq-qa-upload" data-docid="' . (int) $d->id . '"' . ($admin ? ' data-admin="1"' : '') . ' enctype="multipart/form-data" novalidate>'
        . '<label class="nvq-qa-drop"><input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.heic,image/*" class="nvq-qa-fileinput">'
        . qa_ui::icon('upload') . '<span class="nvq-qa-drop-title">' . get_string($label, 'block_nvq_matrix') . '</span>'
        . '<span class="nvq-qa-muted nvq-qa-drop-hint">' . get_string('qauploadhint', 'block_nvq_matrix',
            ['size' => display_size($maxbytes), 'count' => qa::MAXFILES]) . '</span></label>'
        . '<ul class="nvq-qa-picked" hidden></ul>';
    if ($admin) {
        $out .= '<label class="nvq-qa-check"><input type="checkbox" class="nvq-qa-accepted"> <span>'
            . get_string('qaadminuploadaccepted', 'block_nvq_matrix') . '</span></label>';
    }
    $out .= '<div class="nvq-qa-progress" hidden><span></span></div>'
        . '<p class="nvq-qa-error" role="alert" hidden></p>'
        . '<button type="submit" class="nvq-qa-btn nvq-qa-btn--primary nvq-qa-submit" hidden>' . qa_ui::icon('upload')
        . '<span>' . get_string($admin ? 'qaadminuploadsubmit' : 'qasubmitsigned', 'block_nvq_matrix') . '</span></button></form>';
    return $out;
}

// ---- Sign and return documents --------------------------------------
if (!empty($signdocs)) {
    echo '<section class="nvq-qa-section"><div class="nvq-qa-section-head"><h3>' . get_string('qasectionsign', 'block_nvq_matrix') . '</h3>'
        . '<p class="nvq-qa-muted">' . get_string($access->isself ? 'qasectionsignhint' : 'qasectionsignstaffhint', 'block_nvq_matrix') . '</p></div>';
    $n = 0;
    foreach ($signdocs as $d) {
        $n++;
        $sub = $subs[(int) $d->id] ?? null;
        $status = qa::status_of($sub);
        $master = qa::get_master_file((int) $d->id);
        $title = format_string($d->title, true, ['context' => $ctx, 'escape' => false]);
        $learnerfiles = $sub ? qa::get_submission_files($sub, qa::AREA_LEARNER) : [];
        $counterfiles = $sub ? qa::get_submission_files($sub, qa::AREA_COUNTER) : [];
        $iqafiles = $sub ? qa::get_submission_files($sub, qa::AREA_IQA) : [];
        $needsiqa = qa::needs_iqa($d);
        $stage = 'none';
        if ($status === qa::ST_AWAITING_IQA) {
            $stage = 'iqa';
        } else if ((int) $d->needscounter) {
            $stage = $needsiqa ? 'assessoriqa' : 'counter';
        }

        echo '<article class="nvq-qa-task" data-docid="' . (int) $d->id . '" data-title="' . s($title) . '" data-stage="' . $stage . '">';
        echo '<header class="nvq-qa-task-head"><span class="nvq-qa-task-num">' . $n . '</span><div class="nvq-qa-task-title"><h4>' . s($title) . '</h4>';
        if (trim((string) $d->description) !== '') {
            echo '<p class="nvq-qa-muted">' . s($d->description) . '</p>';
        }
        echo '</div>' . qa_ui::status_pill($status) . '</header>';

        echo '<div class="nvq-qa-task-body">';

        // Who has signed so far, for documents that need more than the learner.
        if ((int) $d->needscounter) {
            $accepted = ($status === qa::ST_ACCEPTED);
            $ldone = !empty($learnerfiles) && $status !== qa::ST_RETURNED;
            $adone = $accepted || $status === qa::ST_AWAITING_IQA;
            $steps = [['qasiglearner', $ldone], ['qasigassessor', $adone]];
            if ($needsiqa) {
                $steps[] = ['qasigiqa', $accepted];
            }
            echo '<ol class="nvq-qa-sigs" aria-label="' . s(get_string('qasigtrack', 'block_nvq_matrix')) . '">';
            foreach ($steps as [$str, $done]) {
                echo '<li class="' . ($done ? 'is-done' : '') . '">' . ($done ? qa_ui::icon('check') : '<span class="nvq-qa-sigdot"></span>')
                    . '<span>' . get_string($str, 'block_nvq_matrix') . '</span> <span class="nvq-qa-muted">'
                    . get_string($done ? 'qasigdone' : 'qasigpending', 'block_nvq_matrix') . '</span></li>';
            }
            echo '</ol>';
        }

        // Returned: show why, prominently.
        if ($status === qa::ST_RETURNED && $sub) {
            $by = isset($reviewers[(int) $sub->reviewedby]) ? fullname($reviewers[(int) $sub->reviewedby]) : '';
            echo '<div class="nvq-qa-callout"><strong>' . get_string('qareturnedtitle', 'block_nvq_matrix') . '</strong>'
                . '<p>' . nl2br(s((string) $sub->returnreason)) . '</p>'
                . ($by !== '' ? '<span class="nvq-qa-muted">' . get_string('qareturnedby', 'block_nvq_matrix', s($by)) . '</span>' : '') . '</div>';
        }
        if ($status === qa::ST_ACCEPTED && $sub) {
            $by = isset($reviewers[(int) $sub->reviewedby]) ? fullname($reviewers[(int) $sub->reviewedby]) : '';
            echo '<div class="nvq-qa-callout"><strong>' . get_string('qaacceptedtitle', 'block_nvq_matrix') . '</strong>'
                . ($by !== '' ? '<span class="nvq-qa-muted"> ' . get_string('qaacceptedby', 'block_nvq_matrix', s($by)) . '</span>' : '') . '</div>';
        }
        if ($status === qa::ST_AWAITING_IQA && $access->isself) {
            echo '<div class="nvq-qa-callout"><span>' . get_string('qawaitingiqa', 'block_nvq_matrix') . '</span></div>';
        }

        // The form to download.
        echo '<div class="nvq-qa-step"><span class="nvq-qa-step-n' . ($master ? '' : ' is-off') . '">1</span><div class="nvq-qa-step-body">'
            . '<strong>' . get_string($access->isself ? 'qastepdownload' : 'qablankform', 'block_nvq_matrix') . '</strong>';
        if ($master) {
            echo html_writer::link(qa::file_url('master', (int) $d->id, $master, true),
                qa_ui::icon('download') . '<span>' . get_string('qadownload', 'block_nvq_matrix') . '</span>',
                ['class' => 'nvq-qa-btn nvq-qa-btn--ghost']);
        } else {
            echo '<span class="nvq-qa-muted">' . get_string('qanotavailable', 'block_nvq_matrix') . '</span>';
        }
        echo '</div></div>';

        if ($access->isself) {
            echo '<div class="nvq-qa-step"><span class="nvq-qa-step-n">2</span><div class="nvq-qa-step-body"><strong>'
                . get_string('qastepsign', 'block_nvq_matrix') . '</strong></div></div>';
        }

        // Upload / files.
        $locked = in_array($status, [qa::ST_ACCEPTED, qa::ST_AWAITING_IQA], true);
        echo '<div class="nvq-qa-step"><span class="nvq-qa-step-n' . (!empty($learnerfiles) && $status !== qa::ST_RETURNED ? ' is-done' : '') . '">' . ($access->isself ? '3' : '2') . '</span>'
            . '<div class="nvq-qa-step-body nvq-qa-step-body--wide"><strong>'
            . get_string($access->isself ? 'qastepupload' : 'qalearnerfiles', 'block_nvq_matrix') . '</strong>';

        if (!empty($learnerfiles)) {
            echo nvq_qa_filelist('learner', $sub, $learnerfiles);
        } else if (!$access->isself) {
            echo '<span class="nvq-qa-muted">' . get_string('qanotuploaded', 'block_nvq_matrix') . '</span>';
        }

        if ($canupload && !$locked) {
            echo nvq_qa_uploadform($d, false, !empty($learnerfiles), $maxbytes);
        }
        echo '</div></div>';

        // The signed copies from staff.
        if (!empty($counterfiles)) {
            echo '<div class="nvq-qa-step"><span class="nvq-qa-step-n is-done">' . qa_ui::icon('check') . '</span><div class="nvq-qa-step-body nvq-qa-step-body--wide"><strong>'
                . get_string($needsiqa ? 'qacounterfinaliqa' : 'qacounterfinal', 'block_nvq_matrix') . '</strong>'
                . nvq_qa_filelist('counter', $sub, $counterfiles) . '</div></div>';
        }
        if (!empty($iqafiles)) {
            echo '<div class="nvq-qa-step"><span class="nvq-qa-step-n is-done">' . qa_ui::icon('check') . '</span><div class="nvq-qa-step-body nvq-qa-step-body--wide"><strong>'
                . get_string('qaiqafinal', 'block_nvq_matrix') . '</strong>'
                . nvq_qa_filelist('iqa', $sub, $iqafiles) . '</div></div>';
        }

        // Staff review controls.
        if ($canreview && $sub && in_array($status, [qa::ST_AWAITING, qa::ST_AWAITING_IQA, qa::ST_ACCEPTED, qa::ST_RETURNED], true)) {
            echo '<div class="nvq-qa-review">';
            if ($status === qa::ST_AWAITING) {
                echo '<button type="button" class="nvq-qa-btn nvq-qa-btn--primary" data-qa-accept="' . (int) $d->id . '">'
                    . qa_ui::icon('check') . '<span>' . get_string('qaaccept', 'block_nvq_matrix') . '</span></button>';
            } else if ($status === qa::ST_AWAITING_IQA) {
                if ($caniqasign) {
                    echo '<button type="button" class="nvq-qa-btn nvq-qa-btn--primary" data-qa-accept="' . (int) $d->id . '">'
                        . qa_ui::icon('pen') . '<span>' . get_string('qaiqaaccept', 'block_nvq_matrix') . '</span></button>';
                } else {
                    echo '<span class="nvq-qa-muted">' . get_string('qawaitingiqa', 'block_nvq_matrix') . '</span>';
                }
            } else if ($status === qa::ST_RETURNED) {
                echo '<span class="nvq-qa-muted">' . get_string('qawaitinglearner', 'block_nvq_matrix') . '</span>';
            }
            if ($status !== qa::ST_RETURNED) {
                echo '<button type="button" class="nvq-qa-btn nvq-qa-btn--ghost" data-qa-return="' . (int) $d->id . '">'
                    . qa_ui::icon('return') . '<span>' . get_string('qareturn', 'block_nvq_matrix') . '</span></button>';
            }
            echo '</div>';
        }

        // Administrator tools: upload for the learner, delete an upload.
        if ($canadmin || ($candelete && $sub)) {
            echo '<div class="nvq-qa-admintools">';
            if ($canadmin && $d->doctype == qa::TYPE_SIGN) {
                echo '<details class="nvq-qa-adminupload"><summary>' . get_string('qaadminuploadtitle', 'block_nvq_matrix') . '</summary>'
                    . '<p class="nvq-qa-muted">' . get_string('qaadminuploadhint', 'block_nvq_matrix') . '</p>'
                    . nvq_qa_uploadform($d, true, !empty($learnerfiles), $maxbytes) . '</details>';
            }
            if ($candelete && $sub) {
                echo '<button type="button" class="nvq-qa-btn nvq-qa-btn--danger" data-qa-delete="' . (int) $d->id . '">'
                    . qa_ui::icon('trash') . '<span>' . get_string('qadeleteupload', 'block_nvq_matrix') . '</span></button>';
            }
            echo '</div>';
        }
        echo '</div></article>';
    }
    echo '</section>';
}

// ---- Dialogs (staff) -------------------------------------------------
if ($canreview) {
    ?>
<dialog class="nvq-qa-dialog" id="nvq-qa-accept-dialog" aria-labelledby="nvq-qa-accept-title">
    <form method="dialog" class="nvq-qa-dialog-form" id="nvq-qa-accept-form" enctype="multipart/form-data">
        <h3 id="nvq-qa-accept-title"><?php echo get_string('qaaccepttitle', 'block_nvq_matrix'); ?></h3>
        <p class="nvq-qa-muted" id="nvq-qa-accept-doc"></p>
        <div id="nvq-qa-accept-counter">
            <p id="nvq-qa-accept-help"></p>
            <label class="nvq-qa-drop nvq-qa-drop--compact">
                <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.heic,image/*" id="nvq-qa-counter-input">
                <?php echo qa_ui::icon('upload'); ?><span class="nvq-qa-drop-title" id="nvq-qa-accept-pick"></span>
            </label>
            <ul class="nvq-qa-picked" id="nvq-qa-counter-picked" hidden></ul>
        </div>
        <p class="nvq-qa-muted" id="nvq-qa-accept-nocounter" hidden><?php echo get_string('qanocounternote', 'block_nvq_matrix'); ?></p>
        <div class="nvq-qa-progress" id="nvq-qa-accept-progress" hidden><span></span></div>
        <p class="nvq-qa-error" id="nvq-qa-accept-error" role="alert" hidden></p>
        <div class="nvq-qa-dialog-actions">
            <button type="button" class="nvq-qa-btn nvq-qa-btn--ghost" data-qa-close><?php echo get_string('cancel'); ?></button>
            <button type="submit" class="nvq-qa-btn nvq-qa-btn--primary" id="nvq-qa-accept-submit"><?php echo qa_ui::icon('check'); ?><span id="nvq-qa-accept-submit-label"><?php echo get_string('qaaccept', 'block_nvq_matrix'); ?></span></button>
        </div>
    </form>
</dialog>
<dialog class="nvq-qa-dialog" id="nvq-qa-return-dialog" aria-labelledby="nvq-qa-return-title">
    <form method="dialog" class="nvq-qa-dialog-form" id="nvq-qa-return-form">
        <h3 id="nvq-qa-return-title"><?php echo get_string('qareturntitle', 'block_nvq_matrix'); ?></h3>
        <p class="nvq-qa-muted" id="nvq-qa-return-doc"></p>
        <label for="nvq-qa-return-reason"><?php echo get_string('qareturnreason', 'block_nvq_matrix'); ?></label>
        <textarea id="nvq-qa-return-reason" rows="4" maxlength="1000" class="nvq-qa-textarea"></textarea>
        <p class="nvq-qa-error" id="nvq-qa-return-error" role="alert" hidden></p>
        <div class="nvq-qa-dialog-actions">
            <button type="button" class="nvq-qa-btn nvq-qa-btn--ghost" data-qa-close><?php echo get_string('cancel'); ?></button>
            <button type="submit" class="nvq-qa-btn nvq-qa-btn--danger" id="nvq-qa-return-submit"><?php echo qa_ui::icon('return'); ?><span><?php echo get_string('qareturn', 'block_nvq_matrix'); ?></span></button>
        </div>
    </form>
</dialog>
    <?php
}
if ($candelete) {
    ?>
<dialog class="nvq-qa-dialog" id="nvq-qa-delete-dialog" aria-labelledby="nvq-qa-delete-title">
    <form method="dialog" class="nvq-qa-dialog-form" id="nvq-qa-delete-form">
        <h3 id="nvq-qa-delete-title"><?php echo get_string('qadeleteuploadtitle', 'block_nvq_matrix'); ?></h3>
        <p class="nvq-qa-muted" id="nvq-qa-delete-doc"></p>
        <p><?php echo get_string('qadeleteuploadtext', 'block_nvq_matrix'); ?></p>
        <p class="nvq-qa-error" id="nvq-qa-delete-error" role="alert" hidden></p>
        <div class="nvq-qa-dialog-actions">
            <button type="button" class="nvq-qa-btn nvq-qa-btn--ghost" data-qa-close><?php echo get_string('cancel'); ?></button>
            <button type="submit" class="nvq-qa-btn nvq-qa-btn--danger" id="nvq-qa-delete-submit"><?php echo qa_ui::icon('trash'); ?><span><?php echo get_string('qadeleteupload', 'block_nvq_matrix'); ?></span></button>
        </div>
    </form>
</dialog>
    <?php
}

// ---- Script ---------------------------------------------------------
$jsconfig = [
    'url'        => (new moodle_url('/blocks/nvq_matrix/qa_action.php'))->out(false),
    'sesskey'    => sesskey(),
    'courseid'   => (int) $course->id,
    'studentid'  => (int) $studentid,
    'maxbytes'   => (int) $maxbytes,
    'maxfiles'   => qa::MAXFILES,
    'extensions' => qa::UPLOAD_EXTENSIONS,
    'str' => [
        'toolarge' => get_string('qaerrortoolarge', 'block_nvq_matrix'),
        'type'     => get_string('qaerrortype', 'block_nvq_matrix'),
        'toomany'  => get_string('qaerrortoomany', 'block_nvq_matrix'),
        'failed'   => get_string('qaerrorfailed', 'block_nvq_matrix'),
        'nofile'   => get_string('qaerrornofile', 'block_nvq_matrix'),
        'noreason' => get_string('qaerrornoreason', 'block_nvq_matrix'),
        'counter'  => get_string('qaerrorcounterrequired', 'block_nvq_matrix'),
        'iqa'      => get_string('qaerroriqarequired', 'block_nvq_matrix'),
        'uploading' => get_string('qauploading', 'block_nvq_matrix'),
        'submit'   => get_string('qasubmitsigned', 'block_nvq_matrix'),
        'adminsubmit' => get_string('qaadminuploadsubmit', 'block_nvq_matrix'),
    ],
    // Text of the accept dialog for each stage.
    'stages' => [
        'counter'     => ['title' => get_string('qaaccepttitle', 'block_nvq_matrix'), 'help' => get_string('qacounterhelp', 'block_nvq_matrix'),
                          'pick' => get_string('qacounterpick', 'block_nvq_matrix'), 'button' => get_string('qaaccept', 'block_nvq_matrix')],
        'assessoriqa' => ['title' => get_string('qaaccepttitle', 'block_nvq_matrix'), 'help' => get_string('qacounterhelpiqa', 'block_nvq_matrix'),
                          'pick' => get_string('qacounterpick', 'block_nvq_matrix'), 'button' => get_string('qaaccept', 'block_nvq_matrix')],
        'iqa'         => ['title' => get_string('qaiqaaccepttitle', 'block_nvq_matrix'), 'help' => get_string('qaiqahelp', 'block_nvq_matrix'),
                          'pick' => get_string('qaiqapick', 'block_nvq_matrix'), 'button' => get_string('qaiqaaccept', 'block_nvq_matrix')],
        'none'        => ['title' => get_string('qaaccepttitle', 'block_nvq_matrix'), 'help' => '',
                          'pick' => '', 'button' => get_string('qaaccept', 'block_nvq_matrix')],
    ],
];
?>
<script>
(function() {
    var cfg = <?php echo json_encode($jsconfig); ?>;

    function ext(name) { var i = name.lastIndexOf('.'); return i < 0 ? '' : name.slice(i + 1).toLowerCase(); }
    function size(n) { return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
    function check(files) {
        if (!files.length) { return cfg.str.nofile; }
        if (files.length > cfg.maxfiles) { return cfg.str.toomany; }
        for (var i = 0; i < files.length; i++) {
            if (cfg.extensions.indexOf(ext(files[i].name)) === -1) { return cfg.str.type; }
            if (files[i].size > cfg.maxbytes) { return cfg.str.toolarge; }
        }
        return '';
    }
    function listFiles(ul, files) {
        ul.innerHTML = '';
        for (var i = 0; i < files.length; i++) {
            var li = document.createElement('li');
            var a = document.createElement('span'); a.textContent = files[i].name;
            var b = document.createElement('span'); b.className = 'nvq-qa-muted'; b.textContent = size(files[i].size);
            li.appendChild(a); li.appendChild(b); ul.appendChild(li);
        }
        ul.hidden = !files.length;
    }
    function send(fd, progressEl, done) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.url);
        xhr.setRequestHeader('Accept', 'application/json');
        if (progressEl) {
            progressEl.hidden = false;
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) { progressEl.firstElementChild.style.width = Math.round(100 * e.loaded / e.total) + '%'; }
            };
        }
        xhr.onload = function() {
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            if (res && res.success) { done(true, ''); return; }
            done(false, (res && res.error) ? res.error : cfg.str.failed);
        };
        xhr.onerror = function() { done(false, cfg.str.failed); };
        xhr.send(fd);
    }
    function showError(el, msg) { el.textContent = msg; el.hidden = !msg; }

    // ---- Upload forms (learner, or administrator for a learner) ---------
    document.querySelectorAll('.nvq-qa-upload').forEach(function(form) {
        var isAdmin = form.getAttribute('data-admin') === '1';
        var input = form.querySelector('.nvq-qa-fileinput');
        var picked = form.querySelector('.nvq-qa-picked');
        var err = form.querySelector('.nvq-qa-error');
        var prog = form.querySelector('.nvq-qa-progress');
        var submit = form.querySelector('.nvq-qa-submit');
        var drop = form.querySelector('.nvq-qa-drop');
        var accepted = form.querySelector('.nvq-qa-accepted');
        var files = [];

        function setFiles(list) {
            files = Array.prototype.slice.call(list);
            var problem = files.length ? check(files) : '';
            listFiles(picked, files);
            showError(err, problem);
            submit.hidden = !files.length || !!problem;
        }
        input.addEventListener('change', function() { setFiles(input.files); });
        ['dragenter', 'dragover'].forEach(function(ev) {
            drop.addEventListener(ev, function(e) { e.preventDefault(); drop.classList.add('is-over'); });
        });
        ['dragleave', 'drop'].forEach(function(ev) {
            drop.addEventListener(ev, function(e) { e.preventDefault(); drop.classList.remove('is-over'); });
        });
        drop.addEventListener('drop', function(e) {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                try { input.files = e.dataTransfer.files; } catch (x) { /* older browsers */ }
                setFiles(e.dataTransfer.files);
            }
        });
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var problem = check(files);
            if (problem) { showError(err, problem); return; }
            var fd = new FormData();
            fd.append('sesskey', cfg.sesskey);
            fd.append('action', isAdmin ? 'adminupload' : 'upload');
            fd.append('docid', form.getAttribute('data-docid'));
            fd.append('courseid', cfg.courseid);
            fd.append('studentid', cfg.studentid);
            if (isAdmin && accepted && accepted.checked) { fd.append('accepted', '1'); }
            files.forEach(function(f) { fd.append('files[]', f, f.name); });
            submit.disabled = true;
            submit.querySelector('span').textContent = cfg.str.uploading;
            showError(err, '');
            send(fd, prog, function(ok, msg) {
                if (ok) { window.location.reload(); return; }
                submit.disabled = false;
                prog.hidden = true;
                showError(err, msg);
                submit.querySelector('span').textContent = isAdmin ? cfg.str.adminsubmit : cfg.str.submit;
            });
        });
    });

    var current = null;
    function open(dlg, task) {
        current = task;
        if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    }
    document.querySelectorAll('[data-qa-close]').forEach(function(b) {
        b.addEventListener('click', function() { b.closest('dialog').close(); });
    });

    // ---- Staff: accept / sign ---------------------------------------------
    var acceptDlg = document.getElementById('nvq-qa-accept-dialog');
    var returnDlg = document.getElementById('nvq-qa-return-dialog');
    if (acceptDlg && returnDlg) {
        var counterInput = document.getElementById('nvq-qa-counter-input');
        var counterPicked = document.getElementById('nvq-qa-counter-picked');
        var acceptErr = document.getElementById('nvq-qa-accept-error');
        var acceptProg = document.getElementById('nvq-qa-accept-progress');
        var acceptSubmit = document.getElementById('nvq-qa-accept-submit');
        var stage = 'none';
        counterInput.addEventListener('change', function() {
            var list = Array.prototype.slice.call(counterInput.files);
            listFiles(counterPicked, list);
            showError(acceptErr, list.length ? check(list) : '');
        });
        document.querySelectorAll('[data-qa-accept]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var task = btn.closest('.nvq-qa-task');
                stage = task.getAttribute('data-stage') || 'none';
                var t = cfg.stages[stage] || cfg.stages.none;
                document.getElementById('nvq-qa-accept-doc').textContent = task.getAttribute('data-title');
                document.getElementById('nvq-qa-accept-title').textContent = t.title;
                document.getElementById('nvq-qa-accept-help').textContent = t.help;
                document.getElementById('nvq-qa-accept-pick').textContent = t.pick;
                document.getElementById('nvq-qa-accept-submit-label').textContent = t.button;
                var needs = stage !== 'none';
                document.getElementById('nvq-qa-accept-counter').hidden = !needs;
                document.getElementById('nvq-qa-accept-nocounter').hidden = needs;
                counterInput.value = '';
                listFiles(counterPicked, []);
                showError(acceptErr, '');
                acceptProg.hidden = true;
                acceptSubmit.disabled = false;
                open(acceptDlg, task);
            });
        });
        document.getElementById('nvq-qa-accept-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var needs = stage !== 'none';
            var files = Array.prototype.slice.call(counterInput.files);
            if (needs && !files.length) { showError(acceptErr, stage === 'iqa' ? cfg.str.iqa : cfg.str.counter); return; }
            var problem = files.length ? check(files) : '';
            if (problem) { showError(acceptErr, problem); return; }
            var fd = new FormData();
            fd.append('sesskey', cfg.sesskey);
            fd.append('action', 'accept');
            fd.append('docid', current.getAttribute('data-docid'));
            fd.append('courseid', cfg.courseid);
            fd.append('studentid', cfg.studentid);
            files.forEach(function(f) { fd.append('files[]', f, f.name); });
            acceptSubmit.disabled = true;
            send(fd, files.length ? acceptProg : null, function(ok, msg) {
                if (ok) { window.location.reload(); return; }
                acceptSubmit.disabled = false;
                showError(acceptErr, msg);
            });
        });

        // ---- Staff: return ------------------------------------------------
        var reason = document.getElementById('nvq-qa-return-reason');
        var returnErr = document.getElementById('nvq-qa-return-error');
        var returnSubmit = document.getElementById('nvq-qa-return-submit');
        document.querySelectorAll('[data-qa-return]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var task = btn.closest('.nvq-qa-task');
                document.getElementById('nvq-qa-return-doc').textContent = task.getAttribute('data-title');
                reason.value = '';
                showError(returnErr, '');
                returnSubmit.disabled = false;
                open(returnDlg, task);
            });
        });
        document.getElementById('nvq-qa-return-form').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!reason.value.trim()) { showError(returnErr, cfg.str.noreason); return; }
            var fd = new FormData();
            fd.append('sesskey', cfg.sesskey);
            fd.append('action', 'return');
            fd.append('docid', current.getAttribute('data-docid'));
            fd.append('courseid', cfg.courseid);
            fd.append('studentid', cfg.studentid);
            fd.append('reason', reason.value.trim());
            returnSubmit.disabled = true;
            send(fd, null, function(ok, msg) {
                if (ok) { window.location.reload(); return; }
                returnSubmit.disabled = false;
                showError(returnErr, msg);
            });
        });
    }

    // ---- Administrator: delete an upload --------------------------------
    var deleteDlg = document.getElementById('nvq-qa-delete-dialog');
    if (deleteDlg) {
        var deleteErr = document.getElementById('nvq-qa-delete-error');
        var deleteSubmit = document.getElementById('nvq-qa-delete-submit');
        document.querySelectorAll('[data-qa-delete]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var task = btn.closest('.nvq-qa-task');
                document.getElementById('nvq-qa-delete-doc').textContent = task.getAttribute('data-title');
                showError(deleteErr, '');
                deleteSubmit.disabled = false;
                open(deleteDlg, task);
            });
        });
        document.getElementById('nvq-qa-delete-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData();
            fd.append('sesskey', cfg.sesskey);
            fd.append('action', 'delete');
            fd.append('docid', current.getAttribute('data-docid'));
            fd.append('courseid', cfg.courseid);
            fd.append('studentid', cfg.studentid);
            deleteSubmit.disabled = true;
            send(fd, null, function(ok, msg) {
                if (ok) { window.location.reload(); return; }
                deleteSubmit.disabled = false;
                showError(deleteErr, msg);
            });
        });
    }
})();
</script>
<?php
echo '</div>';
echo $OUTPUT->footer();
