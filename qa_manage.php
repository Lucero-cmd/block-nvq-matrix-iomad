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
 * Company Manager page for their own company's Quality Assurance
 * documents (IOMAD fork, v27.2.0).
 *
 * The site administrator's qa_admin.php manages platform documents, which
 * every learner on the chosen courses receives. This page lets a Company
 * Manager add, edit, copy, reorder, hide and delete documents that belong
 * to their own company, and choose which of their company's courses each
 * applies to - their own courses and the shared (course bank) courses
 * they use. A company document is only ever shown to learners who belong
 * to that company, so on a shared course the other companies' learners
 * and staff never see it (see qa::get_learner_documents()).
 *
 * Who may use it, and where, is decided by qa::get_manageable_companies():
 * a manager of the company in IOMAD, holding block/nvq_matrix:qamanage on
 * a course linked to that company. Every action re-loads the document by
 * id AND company, so a crafted id for another company's document fails.
 * Course choices are limited to that same list, and links to courses
 * outside it are never added or removed from here.
 *
 * Deleting is allowed even after learners have submitted the document (a
 * wrong or mislabelled document must be removable), behind a confirmation
 * that says how many learners' signed records will be destroyed with it.
 * Hiding keeps those records.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\qa;
use block_nvq_matrix\qa_ui;
use block_nvq_matrix\form\qa_document_form;

$courseid  = optional_param('courseid', 0, PARAM_INT);
$companyid = optional_param('companyid', 0, PARAM_INT);
$action    = optional_param('action', 'list', PARAM_ALPHA);
$id        = optional_param('id', 0, PARAM_INT);
// The document being duplicated. Carried in its own URL parameter because
// the form's hidden "id" (0 for a copy) is posted on submit and Moodle
// reads POST before GET, so "id" alone loses the source on submit.
$copyfrom  = optional_param('copyfrom', 0, PARAM_INT);

if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $pagecontext = context_course::instance($course->id);
} else {
    require_login();
    $pagecontext = context_system::instance();
}

$companies = qa::get_manageable_companies();

// Pick the company: the one asked for, else the one using the course the
// manager came from, else their first.
if (!$companyid) {
    foreach ($companies as $c) {
        if ($courseid && isset($c->courses[$courseid])) {
            $companyid = $c->id;
            break;
        }
    }
    if (!$companyid && !empty($companies)) {
        $companyid = (int) array_key_first($companies);
    }
}

$baseparams = ['companyid' => $companyid];
if ($courseid) {
    $baseparams['courseid'] = $courseid;
}
$baseurl = new moodle_url('/blocks/nvq_matrix/qa_manage.php', $baseparams);
$PAGE->set_url(new moodle_url($baseurl, ['action' => $action, 'id' => $id]));
$PAGE->set_context($pagecontext);
$PAGE->set_pagelayout($courseid ? 'incourse' : 'standard');
$PAGE->set_title(get_string('qamanagetitle', 'block_nvq_matrix'));
$PAGE->set_heading($courseid ? format_string($course->fullname, true, ['context' => $pagecontext]) : get_string('qamanagetitle', 'block_nvq_matrix'));
$PAGE->add_body_class('nvq-qa-page');

$backurl = $courseid
    ? new moodle_url('/blocks/nvq_matrix/qa.php', ['courseid' => $courseid])
    : new moodle_url('/blocks/nvq_matrix/view.php');

if (empty($companies) || !isset($companies[$companyid])) {
    echo $OUTPUT->header();
    echo html_writer::start_div('nvq-qa');
    echo html_writer::link($backurl, qa_ui::icon('back') . get_string('qabacktotracker', 'block_nvq_matrix'), ['class' => 'nvq-qa-back']);
    echo $OUTPUT->notification(get_string('qamanagenoaccess', 'block_nvq_matrix'), 'notifyproblem');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die();
}

$company = $companies[$companyid];
$allowedcourseids = array_keys($company->courses);
$sysctx = context_system::instance();

/**
 * Loads one of this company's documents, or stops: a document id that is
 * not this company's - another company's, or a platform document - is
 * treated exactly as one that does not exist.
 *
 * @param int $docid
 * @param int $companyid
 * @return stdClass
 */
function nvq_qa_manage_own_doc(int $docid, int $companyid): stdClass {
    global $DB;
    $doc = $DB->get_record('block_nvq_matrix_qadocs', ['id' => $docid, 'companyid' => $companyid]);
    if (!$doc || $companyid <= 0) {
        throw new moodle_exception('qaerrornotallowed', 'block_nvq_matrix');
    }
    return $doc;
}

// ---------------------------------------------------------------------
// POST actions: move, toggle.
// ---------------------------------------------------------------------
if (in_array($action, ['up', 'down', 'toggle'], true)) {
    require_sesskey();
    $doc = nvq_qa_manage_own_doc($id, $companyid);
    if ($action === 'toggle') {
        $DB->update_record('block_nvq_matrix_qadocs', (object) [
            'id' => $doc->id, 'enabled' => $doc->enabled ? 0 : 1, 'timemodified' => time(),
        ]);
    } else {
        qa::move_document((int) $doc->id, $action === 'up' ? -1 : 1);
    }
    redirect($baseurl);
}

// ---------------------------------------------------------------------
// Delete. Allowed even after learners have submitted (client decision,
// 2026-10-09: a wrong or mislabelled document must be removable), but the
// confirmation says plainly how many learners' signed records go with it,
// since delete_document() removes every submission, signed copy and log
// entry for the document. Only this company's managers reach here
// (nvq_qa_manage_own_doc()); Assessors, IQAs and other staff cannot.
// ---------------------------------------------------------------------
if ($action === 'delete') {
    $doc = nvq_qa_manage_own_doc($id, $companyid);
    $subcount = $DB->count_records('block_nvq_matrix_qasubs', ['docid' => $doc->id]);
    if (optional_param('confirm', 0, PARAM_INT)) {
        require_sesskey();
        qa::delete_document((int) $doc->id);
        redirect($baseurl, get_string('qadocdeleted', 'block_nvq_matrix'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string($subcount ? 'qamanagedeleteconfirmsubs' : 'qadocdeleteconfirm', 'block_nvq_matrix',
            (object) ['title' => format_string($doc->title), 'count' => $subcount]),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $doc->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    die();
}

// ---------------------------------------------------------------------
// Add / edit / duplicate form.
// ---------------------------------------------------------------------
if (in_array($action, ['add', 'edit', 'copy'], true)) {
    $sourceid = ($action === 'copy') ? ($copyfrom ?: $id) : $id;
    $source = null;
    if (in_array($action, ['edit', 'copy'], true)) {
        $source = nvq_qa_manage_own_doc($sourceid, $companyid);
    }
    $fileoptions = qa_document_form::file_options();
    $formurlparams = ['action' => $action, 'id' => $id];
    if ($action === 'copy') {
        $formurlparams['copyfrom'] = $sourceid;
    }
    $form = new qa_document_form(new moodle_url($baseurl, $formurlparams), ['courseoptions' => $company->courses]);

    if ($form->is_cancelled()) {
        redirect($baseurl);
    }

    if ($data = $form->get_data()) {
        $now = time();
        $record = (object) [
            'title'        => trim($data->title),
            'description'  => trim($data->description ?? ''),
            'doctype'      => (int) $data->doctype,
            'needscounter' => ((int) $data->doctype === qa::TYPE_SIGN && (int) $data->signoff >= 1) ? 1 : 0,
            'needsiqa'     => ((int) $data->doctype === qa::TYPE_SIGN && (int) $data->signoff >= 2) ? 1 : 0,
            'enabled'      => (int) $data->enabled,
            'timemodified' => $now,
        ];
        $existingcourses = [];
        if (!empty($data->id)) {
            // The hidden id is posted by the browser - check again that it
            // is this company's document before changing anything.
            $own = nvq_qa_manage_own_doc((int) $data->id, $companyid);
            $record->id = (int) $own->id;
            $DB->update_record('block_nvq_matrix_qadocs', $record);
            $docid = (int) $own->id;
            $existingcourses = $DB->get_fieldset_select('block_nvq_matrix_qadoccourses', 'courseid', 'docid = ?', [$docid]);
        } else {
            $record->companyid   = $companyid;
            $record->sortorder   = (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), 0) FROM {block_nvq_matrix_qadocs} WHERE companyid = ?', [$companyid]) + 1;
            $record->createdby   = (int) $USER->id;
            $record->timecreated = $now;
            $docid = (int) $DB->insert_record('block_nvq_matrix_qadocs', $record);
        }

        qa::set_document_courses($docid, qa::merge_managed_courses(
            $existingcourses, (array) $data->courseids, $allowedcourseids));

        // The file's content was already checked by the form's validation
        // (qa::check_draft_master()), so nothing reaches here unchecked.
        $filedata = (object) ['id' => $docid, 'masterfile_filemanager' => $data->masterfile_filemanager];
        file_postupdate_standard_filemanager($filedata, 'masterfile', $fileoptions, $sysctx, 'block_nvq_matrix', qa::AREA_MASTER, $docid);

        redirect($baseurl, get_string('qadocsaved', 'block_nvq_matrix'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Pre-populate.
    $toset = new stdClass();
    $toset->id = 0;
    $toset->doctype = qa::TYPE_SIGN;
    $toset->signoff = 1;
    $toset->enabled = 1;
    if ($courseid && isset($company->courses[$courseid])) {
        $toset->courseids = [$courseid];
    }
    if ($source) {
        $current = $DB->get_fieldset_select('block_nvq_matrix_qadoccourses', 'courseid', 'docid = ?', [$source->id]);
        $toset->title       = $action === 'copy' ? '' : $source->title;
        $toset->description = $action === 'copy' ? '' : $source->description;
        $toset->doctype     = (int) $source->doctype;
        $toset->signoff     = !empty($source->needsiqa) ? 2 : (!empty($source->needscounter) ? 1 : 0);
        $toset->enabled     = (int) $source->enabled;
        $toset->courseids   = array_values(array_intersect(array_map('intval', $current), $allowedcourseids));
        if ($action === 'edit') {
            $toset->id = (int) $source->id;
            file_prepare_standard_filemanager($toset, 'masterfile', $fileoptions, $sysctx, 'block_nvq_matrix', qa::AREA_MASTER, (int) $source->id);
        }
    }
    $form->set_data($toset);

    echo $OUTPUT->header();
    echo html_writer::start_div('nvq-qa nvq-qa-admin');
    echo html_writer::link($baseurl, qa_ui::icon('back') . get_string('qabacktolist', 'block_nvq_matrix'), ['class' => 'nvq-qa-back']);
    $heading = ($action === 'edit') ? 'qaeditdocument' : (($action === 'copy') ? 'qacopydocument' : 'qaadddocument');
    echo html_writer::tag('h2', get_string($heading, 'block_nvq_matrix'), ['class' => 'nvq-qa-h2']);
    echo html_writer::tag('p', get_string('qamanageformintro', 'block_nvq_matrix', s($company->name)), ['class' => 'nvq-qa-muted']);
    echo html_writer::end_div();
    $form->display();
    echo $OUTPUT->footer();
    die();
}

// ---------------------------------------------------------------------
// List view - this company's documents only.
// ---------------------------------------------------------------------
$docs = qa::get_documents(false, $companyid);

$signcount = 0;
$readcount = 0;
$usedcourses = [];
foreach ($docs as $d) {
    if ((int) $d->doctype === qa::TYPE_SIGN) {
        $signcount++;
    } else {
        $readcount++;
    }
    foreach ($d->courseids as $cid) {
        if (isset($company->courses[$cid])) {
            $usedcourses[$cid] = true;
        }
    }
}
$pendingcount = 0;
if (!empty($docs)) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($docs), SQL_PARAMS_NAMED, 'md');
    $params['s1'] = qa::ST_AWAITING;
    $params['s2'] = qa::ST_AWAITING_IQA;
    $pendingcount = (int) $DB->count_records_select('block_nvq_matrix_qasubs', "docid $insql AND status IN (:s1, :s2)", $params);
}

echo $OUTPUT->header();
echo html_writer::start_div('nvq-qa nvq-qa-admin');
echo html_writer::link($backurl, qa_ui::icon('back') . get_string('qabacktotracker', 'block_nvq_matrix'), ['class' => 'nvq-qa-back']);

echo html_writer::start_div('nvq-qa-head');
echo html_writer::start_div();
echo html_writer::tag('h2', get_string('qamanageheading', 'block_nvq_matrix', s($company->name)), ['class' => 'nvq-qa-h2']);
echo html_writer::tag('p', get_string('qamanageintro', 'block_nvq_matrix'), ['class' => 'nvq-qa-muted']);
echo html_writer::end_div();
echo html_writer::link(new moodle_url($baseurl, ['action' => 'add']),
    qa_ui::icon('plus') . get_string('qaadddocument', 'block_nvq_matrix'), ['class' => 'nvq-qa-btn nvq-qa-btn--primary']);
echo html_writer::end_div();

// A manager of more than one company switches between them here.
if (count($companies) > 1) {
    echo '<div class="nvq-qa-tabs" role="tablist">';
    foreach ($companies as $c) {
        $params = ['companyid' => $c->id];
        if ($courseid) {
            $params['courseid'] = $courseid;
        }
        echo html_writer::link(new moodle_url('/blocks/nvq_matrix/qa_manage.php', $params), s($c->name), [
            'class' => 'nvq-qa-tab' . ((int) $c->id === $companyid ? ' is-active' : ''),
            'role' => 'tab', 'aria-selected' => (int) $c->id === $companyid ? 'true' : 'false',
        ]);
    }
    echo '</div>';
}

echo '<div class="nvq-qa-stats">';
foreach ([
    [count($docs), 'qastatdocs'],
    [$signcount, 'qastatsign'],
    [$readcount, 'qastatread'],
    [count($usedcourses), 'qastatcourses'],
    [$pendingcount, 'qastatpending'],
] as [$n, $label]) {
    echo '<div class="nvq-qa-stat"><span class="nvq-qa-stat-n">' . (int) $n . '</span><span class="nvq-qa-stat-l">'
        . get_string($label, 'block_nvq_matrix') . '</span></div>';
}
echo '</div>';

if (empty($docs)) {
    echo '<div class="nvq-qa-empty">'
        . '<h3>' . get_string('qaemptytitle', 'block_nvq_matrix') . '</h3>'
        . '<p>' . get_string('qamanageempty', 'block_nvq_matrix') . '</p></div>';
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die();
}

$ids = array_keys($docs);
$first = reset($ids);
$last = end($ids);
echo '<div class="nvq-qa-cardlist">';
foreach ($docs as $d) {
    $issign = ((int) $d->doctype === qa::TYPE_SIGN);
    $master = qa::get_master_file((int) $d->id);
    echo '<article class="nvq-qa-admincard' . ($d->enabled ? '' : ' is-disabled') . '">';
    echo '<div class="nvq-qa-admincard-main">';
    echo '<div class="nvq-qa-admincard-top">';
    echo '<span class="nvq-qa-typechip nvq-qa-typechip--' . ($issign ? 'sign' : 'read') . '">'
        . qa_ui::icon($issign ? 'pen' : 'eye') . get_string($issign ? 'qatypesign' : 'qatyperead', 'block_nvq_matrix') . '</span>';
    if ($issign && $d->needscounter) {
        echo '<span class="nvq-qa-typechip nvq-qa-typechip--counter">' . qa_ui::icon('shield')
            . get_string(!empty($d->needsiqa) ? 'qasignoffchip2' : 'qasignoffchip1', 'block_nvq_matrix') . '</span>';
    }
    if (!$d->enabled) {
        echo '<span class="nvq-qa-typechip nvq-qa-typechip--off">' . qa_ui::icon('hide') . get_string('qahidden', 'block_nvq_matrix') . '</span>';
    }
    echo '</div>';
    echo '<h3 class="nvq-qa-admincard-title">' . s(format_string($d->title, true, ['context' => $sysctx, 'escape' => false])) . '</h3>';
    if (trim((string) $d->description) !== '') {
        echo '<p class="nvq-qa-muted">' . s($d->description) . '</p>';
    }
    echo '<div class="nvq-qa-filerow">' . qa_ui::icon('file');
    if ($master) {
        echo html_writer::link(qa::file_url('master', (int) $d->id, $master), s($master->get_filename()), ['target' => '_blank', 'rel' => 'noopener']);
        echo '<span class="nvq-qa-muted"> · ' . display_size($master->get_filesize()) . '</span>';
    } else {
        echo '<span class="nvq-qa-warn">' . get_string('qanomaster', 'block_nvq_matrix') . '</span>';
    }
    echo '</div>';
    // Only course names this manager may manage are shown; any other link
    // (one a site administrator added) is just counted.
    echo '<div class="nvq-qa-chips">';
    $others = 0;
    foreach ($d->courseids as $cid) {
        if (isset($company->courses[$cid])) {
            echo '<span class="nvq-qa-chip">' . s($company->courses[$cid]) . '</span>';
        } else {
            $others++;
        }
    }
    if ($others > 0) {
        echo '<span class="nvq-qa-chip nvq-qa-chip--more">' . get_string('qamanageothercourses', 'block_nvq_matrix', $others) . '</span>';
    }
    if (empty($d->courseids)) {
        echo '<span class="nvq-qa-warn">' . get_string('qanocourses', 'block_nvq_matrix') . '</span>';
    }
    echo '</div>';
    echo '</div>';

    echo '<div class="nvq-qa-admincard-actions">';
    foreach ([['up', 'up', (int) $d->id !== (int) $first], ['down', 'down', (int) $d->id !== (int) $last]] as [$act, $icon, $enabled]) {
        echo '<form method="post" action="' . $baseurl->out(false) . '" class="nvq-qa-inline">'
            . '<input type="hidden" name="sesskey" value="' . sesskey() . '">'
            . '<input type="hidden" name="action" value="' . $act . '"><input type="hidden" name="id" value="' . (int) $d->id . '">'
            . '<button type="submit" class="nvq-qa-iconbtn" title="' . get_string('qamove' . $act, 'block_nvq_matrix') . '"'
            . ($enabled ? '' : ' disabled') . '>' . qa_ui::icon($icon) . '<span class="sr-only">' . get_string('qamove' . $act, 'block_nvq_matrix') . '</span></button></form>';
    }
    echo html_writer::link(new moodle_url($baseurl, ['action' => 'edit', 'id' => $d->id]),
        qa_ui::icon('edit') . '<span>' . get_string('edit') . '</span>', ['class' => 'nvq-qa-btn nvq-qa-btn--ghost']);
    echo html_writer::link(new moodle_url($baseurl, ['action' => 'copy', 'id' => $d->id]),
        qa_ui::icon('copy') . '<span>' . get_string('qaduplicate', 'block_nvq_matrix') . '</span>', ['class' => 'nvq-qa-btn nvq-qa-btn--ghost']);
    echo '<form method="post" action="' . $baseurl->out(false) . '" class="nvq-qa-inline">'
        . '<input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="action" value="toggle">'
        . '<input type="hidden" name="id" value="' . (int) $d->id . '">'
        . '<button type="submit" class="nvq-qa-btn nvq-qa-btn--ghost">' . qa_ui::icon($d->enabled ? 'hide' : 'eye')
        . '<span>' . get_string($d->enabled ? 'qahide' : 'qashow', 'block_nvq_matrix') . '</span></button></form>';
    echo html_writer::link(new moodle_url($baseurl, ['action' => 'delete', 'id' => $d->id]),
        qa_ui::icon('trash') . '<span>' . get_string('delete') . '</span>', ['class' => 'nvq-qa-btn nvq-qa-btn--danger']);
    echo '</div>';
    echo '</article>';
}
echo '</div>';
echo html_writer::end_div();
echo $OUTPUT->footer();
