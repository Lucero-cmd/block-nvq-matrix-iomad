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
 * Administrator page for the Quality Assurance documents list (v26.7.1).
 *
 * Site administrators only (moodle/site:config, through the admin
 * external page). Lets an administrator add a document by uploading a
 * file or by importing the file of an existing Moodle File resource,
 * choose its type, choose the courses it applies to, reorder, hide and
 * delete it, and see which documents each course receives.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use block_nvq_matrix\qa;
use block_nvq_matrix\qa_ui;
use block_nvq_matrix\form\qa_document_form;

$action = optional_param('action', 'list', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);
$view   = optional_param('view', 'documents', PARAM_ALPHA);

admin_externalpage_setup('block_nvq_matrix_qadocs', '', ['action' => $action, 'id' => $id, 'view' => $view]);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$baseurl = new moodle_url('/blocks/nvq_matrix/qa_admin.php');
$PAGE->set_title(get_string('qaadmintitle', 'block_nvq_matrix'));
$PAGE->set_heading(get_string('qaadmintitle', 'block_nvq_matrix'));

// ---------------------------------------------------------------------
// POST actions: move, toggle, delete.
// ---------------------------------------------------------------------
if (in_array($action, ['up', 'down', 'toggle'], true)) {
    require_sesskey();
    $doc = $DB->get_record('block_nvq_matrix_qadocs', ['id' => $id], '*', MUST_EXIST);
    if ($action === 'toggle') {
        $DB->update_record('block_nvq_matrix_qadocs', (object) [
            'id' => $doc->id, 'enabled' => $doc->enabled ? 0 : 1, 'timemodified' => time(),
        ]);
    } else {
        qa::move_document((int) $doc->id, $action === 'up' ? -1 : 1);
    }
    redirect($baseurl);
}

if ($action === 'delete') {
    $doc = $DB->get_record('block_nvq_matrix_qadocs', ['id' => $id], '*', MUST_EXIST);
    if (optional_param('confirm', 0, PARAM_INT)) {
        require_sesskey();
        qa::delete_document((int) $doc->id);
        redirect($baseurl, get_string('qadocdeleted', 'block_nvq_matrix'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $subcount = $DB->count_records('block_nvq_matrix_qasubs', ['docid' => $doc->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string($subcount ? 'qadocdeleteconfirmsubs' : 'qadocdeleteconfirm', 'block_nvq_matrix', (object) [
            'title' => format_string($doc->title), 'count' => $subcount,
        ]),
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
    $fileoptions = qa_document_form::file_options();
    $form = new qa_document_form(new moodle_url($baseurl, ['action' => $action, 'id' => $id]));

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
        if (!empty($data->id)) {
            $record->id = (int) $data->id;
            $DB->update_record('block_nvq_matrix_qadocs', $record);
            $docid = (int) $data->id;
        } else {
            $record->sortorder   = (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {block_nvq_matrix_qadocs}') + 1;
            $record->createdby   = (int) $USER->id;
            $record->timecreated = $now;
            $docid = (int) $DB->insert_record('block_nvq_matrix_qadocs', $record);
        }

        qa::set_document_courses($docid, (array) $data->courseids);

        // The uploaded file (draft area) first; an import, when given,
        // then replaces it.
        $filedata = (object) ['id' => $docid, 'masterfile_filemanager' => $data->masterfile_filemanager];
        file_postupdate_standard_filemanager($filedata, 'masterfile', $fileoptions, $context, 'block_nvq_matrix', qa::AREA_MASTER, $docid);

        $importcmid = (int) ($data->importcmid ?? 0);
        $message = get_string('qadocsaved', 'block_nvq_matrix');
        $type = \core\output\notification::NOTIFY_SUCCESS;
        if ($importcmid > 0) {
            $err = qa::import_master_from_resource($docid, $importcmid);
            if ($err !== '') {
                $message = get_string($err, 'block_nvq_matrix');
                $type = \core\output\notification::NOTIFY_ERROR;
            }
        }
        redirect($baseurl, $message, null, $type);
    }

    // Pre-populate.
    $toset = new stdClass();
    $toset->id = 0;
    $toset->doctype = qa::TYPE_SIGN;
    $toset->signoff = 1;
    $toset->enabled = 1;
    if (in_array($action, ['edit', 'copy'], true) && $id) {
        $doc = $DB->get_record('block_nvq_matrix_qadocs', ['id' => $id], '*', MUST_EXIST);
        $docs = qa::get_documents();
        $toset->title        = $action === 'copy' ? '' : $doc->title;
        $toset->description  = $action === 'copy' ? '' : $doc->description;
        $toset->doctype      = (int) $doc->doctype;
        $toset->signoff      = !empty($doc->needsiqa) ? 2 : (!empty($doc->needscounter) ? 1 : 0);
        $toset->enabled      = (int) $doc->enabled;
        $toset->courseids    = $docs[$doc->id]->courseids ?? [];
        if ($action === 'edit') {
            $toset->id = (int) $doc->id;
            file_prepare_standard_filemanager($toset, 'masterfile', $fileoptions, $context, 'block_nvq_matrix', qa::AREA_MASTER, (int) $doc->id);
        }
    }
    $form->set_data($toset);

    echo $OUTPUT->header();
    echo html_writer::start_div('nvq-qa nvq-qa-admin');
    echo html_writer::link($baseurl, qa_ui::icon('back') . get_string('qabacktolist', 'block_nvq_matrix'), ['class' => 'nvq-qa-back']);
    $heading = ($action === 'edit') ? 'qaeditdocument' : (($action === 'copy') ? 'qacopydocument' : 'qaadddocument');
    echo html_writer::tag('h2', get_string($heading, 'block_nvq_matrix'), ['class' => 'nvq-qa-h2']);
    if ($action === 'copy') {
        echo html_writer::tag('p', get_string('qacopyhint', 'block_nvq_matrix'), ['class' => 'nvq-qa-muted']);
    }
    echo html_writer::end_div();
    $form->display();
    echo $OUTPUT->footer();
    die();
}

// ---------------------------------------------------------------------
// List view.
// ---------------------------------------------------------------------
$docs = qa::get_documents();
$sysctx = context_system::instance();

$coursenames = [];
$allcourseids = [];
foreach ($docs as $d) {
    foreach ($d->courseids as $cid) {
        $allcourseids[$cid] = $cid;
    }
}
if (!empty($allcourseids)) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_values($allcourseids), SQL_PARAMS_NAMED, 'c');
    foreach ($DB->get_records_select('course', "id $insql", $inparams, '', 'id, fullname, shortname') as $c) {
        $coursenames[$c->id] = format_string($c->fullname, true, ['context' => $sysctx, 'escape' => false]);
    }
}

$signcount = 0;
$readcount = 0;
foreach ($docs as $d) {
    if ((int) $d->doctype === qa::TYPE_SIGN) {
        $signcount++;
    } else {
        $readcount++;
    }
}
$pendingcount = (int) $DB->count_records_select('block_nvq_matrix_qasubs', 'status IN (?, ?)', [qa::ST_AWAITING, qa::ST_AWAITING_IQA]);

echo $OUTPUT->header();
echo html_writer::start_div('nvq-qa nvq-qa-admin');

echo html_writer::start_div('nvq-qa-head');
echo html_writer::start_div();
echo html_writer::tag('h2', get_string('qaadmintitle', 'block_nvq_matrix'), ['class' => 'nvq-qa-h2']);
echo html_writer::tag('p', get_string('qaadminintro', 'block_nvq_matrix'), ['class' => 'nvq-qa-muted']);
echo html_writer::end_div();
echo html_writer::link(new moodle_url($baseurl, ['action' => 'add']),
    qa_ui::icon('plus') . get_string('qaadddocument', 'block_nvq_matrix'), ['class' => 'nvq-qa-btn nvq-qa-btn--primary']);
echo html_writer::end_div();

// Stat strip.
echo '<div class="nvq-qa-stats">';
foreach ([
    [count($docs), 'qastatdocs'],
    [$signcount, 'qastatsign'],
    [$readcount, 'qastatread'],
    [count($allcourseids), 'qastatcourses'],
    [$pendingcount, 'qastatpending'],
] as [$n, $label]) {
    echo '<div class="nvq-qa-stat"><span class="nvq-qa-stat-n">' . (int) $n . '</span><span class="nvq-qa-stat-l">'
        . get_string($label, 'block_nvq_matrix') . '</span></div>';
}
echo '</div>';

// Tabs.
echo '<div class="nvq-qa-tabs" role="tablist">';
foreach (['documents' => 'qatabdocuments', 'courses' => 'qatabcourses'] as $key => $str) {
    $cls = 'nvq-qa-tab' . ($view === $key ? ' is-active' : '');
    echo html_writer::link(new moodle_url($baseurl, ['view' => $key]), get_string($str, 'block_nvq_matrix'),
        ['class' => $cls, 'role' => 'tab', 'aria-selected' => $view === $key ? 'true' : 'false']);
}
echo '</div>';

if (empty($docs)) {
    echo '<div class="nvq-qa-empty">'
        . '<h3>' . get_string('qaemptytitle', 'block_nvq_matrix') . '</h3>'
        . '<p>' . get_string('qaemptyadmin', 'block_nvq_matrix') . '</p></div>';
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die();
}

if ($view === 'courses') {
    // Which documents each course receives.
    $bycourse = [];
    foreach ($docs as $d) {
        foreach ($d->courseids as $cid) {
            $bycourse[$cid][] = $d;
        }
    }
    uksort($bycourse, function ($a, $b) use ($coursenames) {
        return strcasecmp($coursenames[$a] ?? '', $coursenames[$b] ?? '');
    });
    echo '<p class="nvq-qa-muted">' . get_string('qacoursesintro', 'block_nvq_matrix') . '</p>';
    echo '<div class="nvq-qa-coveragelist">';
    foreach ($bycourse as $cid => $list) {
        echo '<div class="nvq-qa-coverage"><div class="nvq-qa-coverage-name">' . s($coursenames[$cid] ?? ('#' . $cid)) . '</div><div class="nvq-qa-chips">';
        foreach ($list as $d) {
            $cls = 'nvq-qa-chip' . ((int) $d->doctype === qa::TYPE_SIGN ? ' nvq-qa-chip--sign' : '') . ($d->enabled ? '' : ' is-off');
            echo '<span class="' . $cls . '">' . s(format_string($d->title, true, ['context' => $sysctx, 'escape' => false])) . '</span>';
        }
        echo '</div></div>';
    }
    echo '</div>';
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    die();
}

// Document cards.
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
    echo '<div class="nvq-qa-chips">';
    $shown = 0;
    foreach ($d->courseids as $cid) {
        if ($shown >= 6) {
            break;
        }
        echo '<span class="nvq-qa-chip">' . s($coursenames[$cid] ?? ('#' . $cid)) . '</span>';
        $shown++;
    }
    if (count($d->courseids) > $shown) {
        echo '<span class="nvq-qa-chip nvq-qa-chip--more">+' . (count($d->courseids) - $shown) . '</span>';
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
