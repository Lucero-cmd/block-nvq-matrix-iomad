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
 * Language strings for the block_nvq_matrix plugin.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'NVQ Competence Matrix';
$string['messageprovider:coursecomplete'] = 'Course completion notifications';
$string['messageprovider:coursecomplete'] = 'Course completion notifications';
$string['messageprovider:assessorsubmission'] = 'Assessor evidence submission notifications';
$string['nvq_matrix:addinstance']  = 'Add an NVQ Competence Matrix block';
$string['nvq_matrix:myaddinstance']= 'Add an NVQ Competence Matrix block to My Dashboard';
$string['nvq_matrix:viewall']      = 'View all students in the NVQ Competence Matrix';
$string['nvq_matrix:grade']        = 'Set the unit grade in the NVQ Competence Matrix';
$string['nvq_matrix:sample']       = 'Set the unit sampling status in the NVQ Competence Matrix';
$string['nvq_matrix:iqacomment']   = 'Set the unit-level IQA comment in the NVQ Competence Matrix';
$string['nvq_matrix:finalstatus']  = 'Set the final Pass/Fail status in the NVQ Competence Matrix';
$string['nvq_matrix:manageassessor'] = 'Set the designated assessor in the NVQ Competence Matrix';
$string['nvq_matrix:exportportfolio'] = 'Export a student\'s full portfolio from the NVQ Competence Matrix';
$string['nvq_matrix:deletearchived'] = 'Permanently delete an archived student\'s NVQ matrix data for a course';
$string['nvq_matrix:withdraw']     = 'Mark a learner as withdrawn from a course, or reinstate them, in the NVQ Competence Matrix';

$string['exportportfolio']         = 'Export portfolio';
$string['exportportfolioconfirm']  = 'Export {$a}\'s full portfolio (matrix overview, evidence files, assessment plan and sampling records) as a zip?';

$string['selectstudent']           = 'Select student';
$string['selectstudentprompt']     = '— Select a student —';
$string['searchstudentplaceholder'] = 'Search by name…';
$string['filterneedsgrading']      = 'On-going';
$string['filtercompleted']         = 'Completed';
$string['filterwithdrawn']         = 'Withdrawn';
$string['filterall']               = 'All';
$string['statuscompleted']         = 'Completed';
$string['statusneedsgrading']      = 'On-going';
$string['statusarchived']          = 'Archived';
$string['statuswithdrawn']         = 'Withdrawn';

// Withdrawn status (v26.6.28).
$string['withdrawalheading']       = 'Enrolment status';
$string['withdrawnstate']          = 'Withdrawn';
$string['activestate']             = 'Active';
$string['completedstate']          = 'Completed';
$string['withdrawcompleted']       = 'This learner has completed the qualification (final status Pass), so they cannot be marked as withdrawn.';
$string['withdrawbutton']          = 'Mark as withdrawn';
$string['reinstatebutton']         = 'Reinstate';
$string['withdrawaldate']          = 'Date';
$string['withdrawaldatehelp']      = 'Defaults to today. Change this to record the date the learner actually withdrew or returned.';
$string['withdrawconfirm']         = 'Mark {$a->name} as withdrawn from {$a->course}? Their matrix will be locked (no grading, sampling or comments) until they are reinstated. Nothing already recorded is changed.';
$string['reinstateconfirm']        = 'Reinstate {$a->name} on {$a->course}? Their matrix will be unlocked and they will return to their previous status.';
$string['withdrawnbanner']         = 'This learner has withdrawn from this course. The matrix is locked - reinstate them to make changes.';
$string['withdrawnbannerown']      = 'You are recorded as withdrawn from this course. Please contact your assessor if this is not correct.';
$string['withdrawnlogwithdrawn']   = 'Withdrawn {$a->date} by {$a->name}';
$string['withdrawnlogreinstated']  = 'Reinstated {$a->date} by {$a->name}';
$string['withdrawnlocknote']       = 'The matrix is locked while this learner is withdrawn.';
$string['withdrawnlocknoteedit']   = 'The matrix is locked while this learner is withdrawn. Use Edit matrix to reinstate them.';
$string['withdrawdeletetoactive']    = 'This entry sets the learner\'s current enrolment status. Deleting it will change their status to Active and unlock the matrix. Delete it permanently?';
$string['withdrawdeletetowithdrawn'] = 'This entry sets the learner\'s current enrolment status. Deleting it will change their status back to Withdrawn and lock the matrix. Delete it permanently?';
$string['historyevidencecommentlabel'] = 'Evidence comments';
$string['privacy:metadata:block_nvq_matrix_evcomments_history'] = 'Earlier versions of assessor comments on a single piece of evidence, kept when a comment is changed, cleared or deleted.';
$string['withdrawnlocked']         = 'This learner is marked as withdrawn from this course, so their matrix cannot be changed. Reinstate them first.';
$string['withdrawsaved']           = 'Learner marked as withdrawn.';
$string['reinstatesaved']          = 'Learner reinstated.';
$string['withdrawerror']           = 'Could not update the learner\'s enrolment status. Nothing was changed.';
$string['withdrawnopermission']    = 'You do not have permission to change this learner\'s enrolment status.';
$string['withdrawalreadystate']    = 'This learner\'s status has already been changed - please reload the page.';
$string['deletearchived']          = 'Delete';
$string['deletearchivedconfirm']   = 'Permanently delete {$a->name}\'s NVQ matrix data (grades, sampling, comments, final status, and evidence comments) for {$a->coursename}? This cannot be undone.';
$string['deletearchivedsuccess']   = 'Archived student data deleted.';
$string['deletearchivederror']     = 'Something went wrong deleting this data. Nothing was changed.';
$string['deletearchivednopermission'] = 'You do not have permission to delete this data.';
$string['deletearchivedstillenrolled'] = 'This student is still actively enrolled on this course, so their data cannot be deleted here.';
$string['showarchived']            = 'Show archived (unenrolled) students';
$string['finalstatuspass']         = 'Pass';
$string['finalstatusfail']         = 'Fail';
$string['finalstatusnotset']       = 'Not set';
$string['nostudentsmatch']         = 'No students match your search or filter.';
$string['completedfrom']           = 'Completed from';
$string['completedto']             = 'Completed to';
$string['completedondate']         = 'Completed {$a}';
$string['togglesearch']            = 'Search students';
$string['togglefilter']            = 'Filter students';
$string['idleprompt']              = 'Select a student above to view their competence matrix.';
$string['nostudents']              = 'No students are enrolled in this course.';
$string['noevidence']              = 'No evidence linked yet';
$string['nodata']                  = 'No competence data found. Ensure the Exabis Competence Grid block is configured for this course and students have linked portfolio evidence.';
$string['assessmentcriterion']     = 'Assessment Criterion';
$string['evidencelinked']          = 'Evidence Linked';
$string['assessorverdict']         = 'Assessor Verdict';
$string['competent']               = 'Competent';
$string['notcompetent']            = 'Not Yet Competent';
$string['notyet']                  = 'Not Yet Competent';
$string['notgraded']               = 'Not yet graded';
$string['notassessed']             = 'Not Yet Assessed';
$string['col_grade']               = 'Assessor Grade';
$string['gradecomment']            = 'Add a comment (optional)';
$string['commentdate']             = 'Comment date';
$string['commentdatehelp']         = 'Defaults to today. Change this to backdate the comment, e.g. when grading a portfolio that was actually completed earlier.';
$string['sampledate']              = 'Sampled date';
$string['sampledatehelp']          = 'Defaults to today. Change this to backdate the sampling record, e.g. when recording sampling that actually took place earlier.';
$string['gradesaved']              = 'Grade saved';
$string['gradecleared']            = 'Grade removed';
$string['gradeerror']              = 'Could not save grade. Please try again.';
$string['gradenopermission']       = 'You do not have permission to grade this student.';
$string['gradenoevidence']         = 'Evidence must be linked before this criterion can be graded.';
$string['gradedisabledtext']       = 'Link evidence to enable grading';
$string['confirmgradecompetent']   = 'Mark this criterion as Competent for this student?';
$string['confirmgradenotyet']      = 'Mark this criterion as Not Yet Competent for this student?';
$string['confirmgradeclear']       = 'Remove the grade for this unit? The student will show as Not Graded.';
$string['cleargrade']              = 'Clear grade';
$string['orphannograde']           = '—';
$string['sampled']                 = 'Sampled';
$string['notyetsampled']           = 'Not Yet Sampled';
$string['samplingstatus']          = 'Sampling Status';
$string['samplingblank']           = '— Not set —';
$string['samplingsaved']           = 'Sampling status saved';
$string['samplingerror']           = 'Could not save sampling status. Please try again.';
$string['samplingnopermission']    = 'You do not have permission to set sampling status.';
$string['evidencecommentplaceholder'] = 'Add a comment on this evidence (optional)';
$string['evidencecommentsaved']       = 'Comment saved';
$string['iqacommentlabel']            = 'IQA comment';
$string['iqacommentplaceholder']      = 'Add an IQA comment (optional)';
$string['commentedbyprefix']          = '— {$a}';
$string['unitcommentsaved']           = 'Comment saved';
$string['unitcommenterror']           = 'Could not save comment. Please try again.';
$string['unitcommentnopermission']    = 'You do not have permission to add this comment.';
$string['unitprogress']            = '{$a->met} of {$a->total} criteria evidenced';
$string['overallprogress']         = 'Overall: {$a->met} of {$a->total} criteria evidenced across all units';
$string['assessorprogress']        = 'Assessor progress: {$a->graded} of {$a->total} units graded';
$string['gapanalysis']             = 'Gaps Remaining';
$string['gapanalysisdone']         = 'Showing Gaps Only';
$string['nogapsremaining']         = 'Fully Evidenced';
$string['viewportfolio']           = 'View in portfolio';
$string['configtitle']             = 'Block title';
$string['configtitle_desc']        = 'The title displayed on the block header.';
$string['defaulttitle']            = 'NVQ Competence Matrix';
$string['viewfullpage']            = 'View full matrix page';
$string['backtodashboard']         = 'Back to dashboard';
// Deprecated: this block used to declare null_provider and cite this reason.
// Kept only so old language-pack overrides/caches don't show a missing-string
// warning; no longer referenced by classes/privacy/provider.php since v25.
$string['privacy:no_data_reason']  = 'This block displays data from Exabis ePortfolio and Exabis Competence Grid. It does not store any personal data of its own.';

$string['privacy:authoredentries'] = 'Entries you wrote about other students';
$string['privacy:historyentries'] = 'Grading and status history';
$string['privacy:metadata:block_nvq_matrix_history:archivedtime'] = 'The date and time this value stopped being current (i.e. was overwritten or deleted).';

$string['privacy:metadata:block_nvq_matrix_grades'] = 'The unit-level assessor grade (Competent / Not Yet Competent) and optional comment recorded for a student against a unit.';
$string['privacy:metadata:block_nvq_matrix_grades:studentid'] = 'The ID of the student the grade applies to.';
$string['privacy:metadata:block_nvq_matrix_grades:topicid'] = 'The unit (exacomp topic) the grade applies to.';
$string['privacy:metadata:block_nvq_matrix_grades:courseid'] = 'The course the unit belongs to.';
$string['privacy:metadata:block_nvq_matrix_grades:value'] = 'The grade verdict: Competent or Not Yet Competent.';
$string['privacy:metadata:block_nvq_matrix_grades:comment'] = 'An optional comment the assessor wrote alongside the grade.';
$string['privacy:metadata:block_nvq_matrix_grades:gradedby'] = 'The ID of the assessor who last saved this grade.';
$string['privacy:metadata:block_nvq_matrix_grades:timemodified'] = 'The date and time the grade was last saved.';
$string['privacy:metadata:block_nvq_matrix_grades:commentedby'] = 'The ID of the assessor who last saved the comment. Tracked separately from the assessor who set the grade verdict, since clearing the verdict does not clear the comment.';
$string['privacy:metadata:block_nvq_matrix_grades:commenttime'] = 'The date shown against the comment. Normally the save time, but may be manually backdated by the assessor.';

$string['privacy:metadata:block_nvq_matrix_sampling'] = 'The IQA/EQA sampling status recorded for a student against a unit.';
$string['privacy:metadata:block_nvq_matrix_sampling:studentid'] = 'The ID of the student the sampling status applies to.';
$string['privacy:metadata:block_nvq_matrix_sampling:topicid'] = 'The unit (exacomp topic) the sampling status applies to.';
$string['privacy:metadata:block_nvq_matrix_sampling:courseid'] = 'The course the unit belongs to.';
$string['privacy:metadata:block_nvq_matrix_sampling:status'] = 'The sampling status: Sampled or Not Yet Sampled.';
$string['privacy:metadata:block_nvq_matrix_sampling:sampledby'] = 'The ID of the IQA/EQA user who last set this status.';
$string['privacy:metadata:block_nvq_matrix_sampling:timemodified'] = 'The date and time the sampling status was last saved.';

$string['privacy:metadata:block_nvq_matrix_evidence_comments'] = 'An optional comment an assessor attached to a single piece of evidence for a student, plus its evidence type classification.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:studentid'] = 'The ID of the student who owns the evidence item being commented on.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:comment'] = 'The comment text.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:evidencetype'] = 'The evidence type classification chosen for this evidence item (e.g. Observation, Product, Witness Testimony).';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:commentedby'] = 'The ID of the user who wrote this comment.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:timemodified'] = 'The date and time the comment was last saved.';
$string['privacy:metadata:block_nvq_matrix_evidence_types'] = 'One or more evidence type classifications chosen for a single piece of evidence (an item can genuinely fit more than one type, e.g. both Observation and Professional Discussion).';
$string['privacy:metadata:block_nvq_matrix_evidence_types:evidencecommentid'] = 'The evidence item this type classification applies to.';
$string['privacy:metadata:block_nvq_matrix_evidence_types:code'] = 'The evidence type code (e.g. Observation, Product, Witness Testimony).';
$string['privacy:metadata:block_nvq_matrix_status'] = 'The final Pass/Fail status recorded for a student on a course, and a record of when/by whom a completion notification was sent.';
$string['privacy:metadata:block_nvq_matrix_status:studentid'] = 'The ID of the student this final status applies to.';
$string['privacy:metadata:block_nvq_matrix_status:courseid'] = 'The course this final status applies to.';
$string['privacy:metadata:block_nvq_matrix_status:status'] = 'The final result: Pass or Fail.';
$string['privacy:metadata:block_nvq_matrix_status:setby'] = 'The ID of the assessor who set this final status.';
$string['privacy:metadata:block_nvq_matrix_status:timemodified'] = 'The date and time the final status was last set.';
$string['privacy:metadata:block_nvq_matrix_status:notifiedtime'] = 'The date and time the completion notification was last sent to the student, if ever.';
$string['privacy:metadata:block_nvq_matrix_status:notifiedby'] = 'The ID of the assessor who last sent the completion notification.';
$string['privacy:metadata:block_nvq_matrix_assessor'] = 'The designated assessor for a student\'s course.';
$string['privacy:metadata:block_nvq_matrix_assessor:courseid'] = 'The ID of the course this assessor assignment applies to.';
$string['privacy:metadata:block_nvq_matrix_assessor:userid'] = 'The ID of the user assigned as assessor.';
$string['privacy:metadata:block_nvq_matrix_assessor:setby'] = 'The ID of the user who made this assessor assignment.';
$string['privacy:metadata:block_nvq_matrix_assessor:timemodified'] = 'When this assessor assignment was last changed.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive'] = 'A record of when an archived student\'s NVQ matrix data was permanently deleted.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:studentid'] = 'The ID of the student whose archived data was deleted.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:courseid'] = 'The ID of the course the deleted data belonged to.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:timecleared'] = 'When the archived data was deleted.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:clearedby'] = 'The ID of the user who deleted the archived data.';
$string['privacy:metadata:block_nvq_matrix_withdrawals'] = 'A log of a learner being marked as withdrawn from, or reinstated to, a course.';
$string['privacy:metadata:block_nvq_matrix_withdrawals:studentid'] = 'The ID of the learner the entry is about.';
$string['privacy:metadata:block_nvq_matrix_withdrawals:courseid'] = 'The ID of the course concerned.';
$string['privacy:metadata:block_nvq_matrix_withdrawals:action'] = 'Whether the learner was withdrawn or reinstated.';
$string['privacy:metadata:block_nvq_matrix_withdrawals:actiondate'] = 'The date recorded for the withdrawal or reinstatement.';
$string['privacy:metadata:block_nvq_matrix_withdrawals:actionby'] = 'The ID of the staff member who recorded it.';
$string['privacy:metadata:block_nvq_matrix_withdrawals:timecreated'] = 'When the entry was saved.';
$string['privacy:withdrawals'] = 'Withdrawals and reinstatements';
$string['privacy:metadata:block_nvq_matrix_notified_items'] = 'A record that a given eportfolio item has already triggered an assessor-submission notification at least once, used by the scheduled task that notifies assessors of new evidence to avoid re-notifying on every resubmission when the renotifyonedit setting is turned off.';
$string['privacy:metadata:block_nvq_matrix_notified_items:itemid'] = 'The eportfolio item (owned by the student whose submission triggered the notification) this record applies to.';
$string['privacy:metadata:block_nvq_matrix_notified_items:timenotified'] = 'The date and time the assessor was first notified about this item.';
$string['privacy:assessorrole:assignee'] = 'Assigned as assessor';
$string['privacy:assessorrole:assignedby'] = 'Made this assessor assignment';
$string['privacy:metadata:block_nvq_matrix:core_message'] = 'This plugin sends a completion notification to the student via Moodle\'s own messaging system when an assessor confirms they want to notify the student of their final status.';

$string['privacy:metadata:block_nvq_matrix_unit_comments'] = 'Optional unit-level assessor and IQA comments recorded for a student against a unit.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:studentid'] = 'The ID of the student the comments apply to.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:topicid'] = 'The unit (exacomp topic) the comments apply to.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:courseid'] = 'The course the unit belongs to.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:iqacomment'] = 'The IQA comment text.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:iqacommentby'] = 'The ID of the IQA user who wrote the IQA comment.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:iqacommenttime'] = 'The date and time the IQA comment was last saved.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:assessorcomment'] = 'A legacy unit-level assessor comment field, not shown in the current UI, kept for sites that briefly ran the version of this plugin that displayed it.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:assessorcommentby'] = 'The ID of the assessor who wrote the legacy assessor comment.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:assessorcommenttime'] = 'The date and time the legacy assessor comment was last saved.';
$string['launcherdesc']   = 'View your NVQ competence matrix, track evidence against each assessment criterion, and monitor your qualification progress.';
$string['launcherbutton'] = 'View Matrix';
$string['assessmentplan'] = 'Assessment plan';
$string['samplingplan'] = 'Sampling plan';
$string['samplingrecord'] = 'Sampling record';

// Evidence type dropdown.
$string['evidencetype']            = 'Evidence type';
$string['evidencetypeedit']        = 'Edit types';
$string['evidencetypenotset']      = 'Not set';
$string['editmatrix']              = 'Edit matrix';
$string['doneediting']             = 'Done editing';
$string['evidencetypesaved']       = 'Evidence type saved';

// Final Pass/Fail status + completion notification.
$string['finalstatusheading']  = 'Final status';
$string['statusnotset']        = 'Not yet set';
$string['statuspass']          = 'Pass';
$string['statusfail']          = 'Fail';
$string['setpass']             = 'Pass';
$string['setfail']             = 'Fail';
$string['statussaved']         = 'Status saved';
$string['statussetby']         = 'Set by {$a->name}, {$a->date}';
$string['statusdate']          = 'Status date';
$string['statusdatehelp']      = 'Defaults to today. Change this to backdate the status, e.g. recording a result for a student who genuinely completed before this feature existed.';
$string['clearstatus']         = 'Clear status';
$string['clearstatusconfirm']  = 'Clear the final status for {$a->name} on {$a->course}? This cannot be undone — you\'ll need to set it again if this was wrong.';
$string['statuscleared']       = 'Status cleared';
$string['notifystudent']       = 'Notify student';
$string['notifyconfirm']       = 'Send a "{$a->status}" completion notification to {$a->name} for {$a->course} now?';
$string['notificationsent']    = 'Notification sent';
$string['nostatusset']         = 'Set a Pass or Fail status first, before sending a notification.';
$string['notifiedbyondate']    = 'Notified by {$a->name} on {$a->date}';
$string['notifiedondate']      = 'Notified on {$a->date}';
$string['viewyourmatrix']      = 'View your competence matrix';

// Completion notification content — sent via Moodle messaging (see db/messages.php).
$string['completionsubjectpass'] = 'You\'ve completed {$a}!';
$string['completionbodypass']    = '<p>Hi {$a->name},</p><p><strong>Congratulations — you have successfully completed {$a->course}!</strong></p><p>Well done on all the hard work that got you here. Your assessor has confirmed your final result as a <strong>Pass</strong>.</p><p>You can view your full portfolio and competence matrix at any time by logging into your account and accessing your NVQ competence matrix.</p>';
$string['completionsubjectfail'] = 'Your {$a} result is now available';
$string['completionbodyfail']    = '<p>Hi {$a->name},</p><p>Your assessor has recorded your final result for <strong>{$a->course}</strong>.</p><p>This has been recorded as <strong>Not Yet Achieved</strong>. This isn\'t the end of the road — please speak to your assessor about what\'s needed to get there.</p>';

// Assessor designation (dropdown at the top of the matrix) + submission notification.
$string['assessorheading']       = 'Assessor';
$string['assessornotset']        = 'Not set';
$string['assessorsaving']        = 'Saving…';
$string['assessorsaved']         = 'Assessor saved';
$string['assessorcleared']       = 'Assessor cleared';
$string['assessorstale']         = '(no longer eligible)';
$string['backtomatrix']          = 'Back to matrix';
$string['assessorpageintro']     = 'Choose the designated Assessor for each course you manage. The Assessor is notified when a student submits new evidence.';
$string['tasknotifyassessors']   = 'Notify assessors of new evidence submissions';

// History (audit trail UI, v26.6.16).
$string['historybutton']          = 'History';
$string['historytitle']           = 'Change history';
$string['historynotifiedon']      = 'Notified {$a->name}, {$a->date}';
$string['historysamplingblank']   = 'Not set';
$string['historystatusnotset']    = 'Not yet set';
$string['historynone']            = 'No changes recorded yet.';
$string['historygradelabel']      = 'Grade';
$string['historyunitcommentlabel'] = 'IQA comment';
$string['historysamplinglabel']   = 'Sampling';
$string['historystatuslabel']     = 'Final status';
$string['historyerror']           = 'Could not load history.';
$string['historydeletebtn']       = 'Delete';
$string['historydeleteconfirm']   = 'Permanently delete this history entry? This cannot be undone.';
$string['historydeleteerror']     = 'Could not delete this history entry.';
$string['historynopermission']    = 'You do not have permission to view this history.';
$string['renotifyonedit']        = 'Notify assessor on every resubmission';
$string['renotifyonedit_desc']   = 'When enabled (default), the designated Assessor is notified every time a student saves changes to an eportfolio item that has competencies linked to it - including a re-edit where the linked competencies haven\'t actually changed (exaport re-links them on every save regardless). When disabled, the Assessor is only notified the first time a given item triggers a submission; later re-edits of that same item are silent.';
$string['assessorerror']         = 'Could not save assessor. Please try again.';
$string['assessornopermission']  = 'You do not have permission to set the assessor for this course.';
$string['invalidassessor']       = 'The selected user is not a valid assessor for this course.';
$string['assessorsubmissionsubject'] = 'New submission in {$a}';
$string['assessorsubmissionbody']    = '<p>{$a->student} has submitted new evidence in <strong>{$a->course}</strong>.</p><p>You are receiving this because you are the designated Assessor for this course.</p>';

// ---------------------------------------------------------------------
// Quality Assurance documents (v26.7.1).
// ---------------------------------------------------------------------
$string['nvq_matrix:qareview'] = 'Accept or return Quality Assurance document uploads';
$string['messageprovider:qasubmission'] = 'A learner uploads a Quality Assurance document';
$string['messageprovider:qareview'] = 'Your Quality Assurance document is accepted or returned';

$string['qabutton'] = 'Quality Assurance documents';
$string['qatrackerbutton'] = 'QA course tracker';
$string['qapagetitle'] = 'Quality Assurance documents';
$string['qaeyebrow'] = 'Quality Assurance';
$string['qatrackertitle'] = 'QA documents tracker';
$string['qatrackerlink'] = 'Course tracker';

// Admin page.
$string['qaadmintitle'] = 'Quality Assurance documents';
$string['qaadminintro'] = 'The documents every learner is asked to read or sign. Each document applies only to the courses you choose, so documents for one awarding body are not shown on another.';
$string['qaadminsettingslink'] = 'Manage Quality Assurance documents';
$string['qaadddocument'] = 'Add a document';
$string['qaeditdocument'] = 'Edit document';
$string['qacopydocument'] = 'Duplicate document';
$string['qacopyhint'] = 'The type and the courses are copied. Add a title and a file for the new document.';
$string['qabacktolist'] = 'Back to the documents';
$string['qatabdocuments'] = 'Documents';
$string['qatabcourses'] = 'By course';
$string['qacoursesintro'] = 'The documents each course receives. Hidden documents are shown faded.';
$string['qastatdocs'] = 'Documents';
$string['qastatsign'] = 'Sign and return';
$string['qastatread'] = 'Read only';
$string['qastatcourses'] = 'Courses covered';
$string['qastatpending'] = 'Awaiting a check';
$string['qaemptytitle'] = 'No documents yet';
$string['qaemptyadmin'] = 'Add the first document to start building the checklist. You can upload a file or import one from an existing course activity.';
$string['qaemptylearner'] = 'There are no Quality Assurance documents for this course.';
$string['qanomaster'] = 'No file attached';
$string['qanocourses'] = 'Not applied to any course';
$string['qahidden'] = 'Hidden';
$string['qahide'] = 'Hide';
$string['qashow'] = 'Show';
$string['qaduplicate'] = 'Duplicate';
$string['qamoveup'] = 'Move up';
$string['qamovedown'] = 'Move down';
$string['qacountersign'] = 'Staff countersign';
$string['qadocsaved'] = 'The document has been saved.';
$string['qadocdeleted'] = 'The document has been deleted.';
$string['qadocdeleteconfirm'] = 'Delete the document "{$a->title}"? Its file will be removed. This cannot be undone.';
$string['qadocdeleteconfirmsubs'] = 'Delete the document "{$a->title}"? Its file will be removed, together with {$a->count} learner upload(s) and their countersigned copies. This cannot be undone. To keep the uploads, hide the document instead.';

// Form.
$string['qaformdetails'] = 'Document';
$string['qaformcourses'] = 'Courses';
$string['qaformfile'] = 'File';
$string['qadoctitle'] = 'Title';
$string['qadocdescription'] = 'Short guidance';
$string['qadocdescription_help'] = 'Optional. One or two sentences shown under the title, for example when the document must be completed.';
$string['qadoctype'] = 'Type';
$string['qadoctype_help'] = '**Read** documents are opened in the browser and nothing is returned. **Sign and return** documents are downloaded, signed and uploaded back by the learner. Use a PDF for anything to be read in the browser, because browsers cannot display Word files.';
$string['qatyperead'] = 'Read';
$string['qatypesign'] = 'Sign and return';
$string['qaneedscounter'] = 'Countersignature';
$string['qaneedscounter_desc'] = 'Staff must upload a countersigned copy (the "For Centre use" section) when they accept it';
$string['qadocenabled'] = 'Visible';
$string['qadocenabled_desc'] = 'Show this document to learners and staff';
$string['qadoccourses'] = 'Applies to these courses';
$string['qadoccourses_help'] = 'Only learners and staff in the chosen courses see this document. Choose the courses of one awarding body to keep its documents away from the others.';
$string['qadoccourses_placeholder'] = 'Search for a course';
$string['qamasterfile'] = 'Upload a file';
$string['qafilelimit'] = 'PDF, Word or image, up to {$a}.';
$string['qaimportcmid'] = 'Or import from an existing activity';
$string['qaimportcmid_help'] = 'Enter the number after "id=" in the web address of an existing File activity, for example 1130. Its file is copied here, replacing any file above. The original activity is not changed.';
$string['qasavedocument'] = 'Save document';
$string['qaerrornocourses'] = 'Choose at least one course.';
$string['qaerrornomaster'] = 'Upload a file, or import one from an existing activity.';
$string['qaimportnotfound'] = 'No File activity with that number was found.';
$string['qaimportnofile'] = 'That activity has no file to import.';
$string['qaimportbadtype'] = 'That activity\'s file is not a PDF, Word or image file. The document was saved without a file.';

// Checklist.
$string['qalearnerintro'] = 'Read the documents below, and download, sign and return the ones that need your signature.';
$string['qalearnerlabel'] = 'Learner: {$a}';
$string['qaringlabel'] = '{$a->done} of {$a->total} signed documents accepted';
$string['qaringcaption'] = 'accepted';
$string['qawithdrawnnote'] = 'This learner is withdrawn from the course, so documents cannot be uploaded, accepted or returned until they are reinstated.';
$string['qasectionread'] = 'Read these documents';
$string['qasectionreadhint'] = 'Open each one. There is nothing to return.';
$string['qasectionsign'] = 'Sign and return';
$string['qasectionsignhint'] = 'Download the form, sign it, then upload your signed copy. Your assessor will check it.';
$string['qasectionsignstaffhint'] = 'Check each signed copy, then accept it or return it with a reason.';
$string['qaopen'] = 'Open';
$string['qadownload'] = 'Download';
$string['qanotavailable'] = 'The file is not available yet.';
$string['qastepdownload'] = 'Download the form';
$string['qastepsign'] = 'Sign it';
$string['qablankform'] = 'Blank form';
$string['qastepupload'] = 'Upload your signed copy';
$string['qalearnerfiles'] = 'Uploaded by the learner';
$string['qanotuploaded'] = 'Nothing uploaded yet.';
$string['qadropzone'] = 'Drop your signed copy here, or browse';
$string['qareplace'] = 'Upload a different copy';
$string['qauploadhint'] = 'PDF, Word or image. Up to {$a->size} each, up to {$a->count} files.';
$string['qasubmitsigned'] = 'Submit signed copy';
$string['qauploading'] = 'Uploading...';
$string['qacounterfinal'] = 'Final countersigned copy';
$string['qaacceptedtitle'] = 'Accepted.';
$string['qaacceptedby'] = 'Checked by {$a}';
$string['qareturnedtitle'] = 'Returned for changes';
$string['qareturnedby'] = 'Returned by {$a}. Please correct it and upload a new copy.';
$string['qawaitinglearner'] = 'Waiting for the learner to upload a new copy.';
$string['qaaccept'] = 'Accept';
$string['qareturn'] = 'Return';
$string['qaaccepttitle'] = 'Accept this document';
$string['qacounterhelp'] = 'Upload the countersigned copy, with the "For Centre use" section completed and signed. It becomes the final version; the learner\'s own copy is kept.';
$string['qacounterpick'] = 'Choose the countersigned copy';
$string['qanocounternote'] = 'This document does not need a countersigned copy.';
$string['qareturntitle'] = 'Return this document';
$string['qareturnreason'] = 'Tell the learner what to correct';

// Statuses.
$string['qastatusnone'] = 'Not uploaded';
$string['qastatusawaiting'] = 'Awaiting check';
$string['qastatusaccepted'] = 'Accepted';
$string['qastatusreturned'] = 'Returned';

// Tracker.
$string['qalearner'] = 'Learner';
$string['qaprogress'] = 'Progress';
$string['qastatlearners'] = 'Learners';
$string['qastatawaiting'] = 'Awaiting a check';
$string['qastatreturned'] = 'Returned';
$string['qastatcomplete'] = 'All accepted';
$string['qasearchlearners'] = 'Search learners';
$string['qafilterlabel'] = 'Filter learners';
$string['qafilterall'] = 'All';
$string['qafilterneeds'] = 'Needs checking';
$string['qafilterreturned'] = 'Returned';
$string['qafilterincomplete'] = 'Not complete';
$string['qafiltercomplete'] = 'Complete';
$string['qanoresults'] = 'No learners match.';
$string['qatrackernolearners'] = 'There are no learners in this course.';
$string['qatrackernosign'] = 'This course has no documents that need signing, so there is nothing to track.';
$string['qaviewreaddocs'] = 'View the documents to read';

// Errors.
$string['qaerrornotallowed'] = 'You do not have permission to do that.';
$string['qaerrorsession'] = 'Your session has expired. Reload the page and try again.';
$string['qaerrortype'] = 'That file type is not allowed. Use a PDF, Word or image file.';
$string['qaerrortoolarge'] = 'That file is too large.';
$string['qaerrortoomany'] = 'Too many files. Upload fewer files at once.';
$string['qaerrorempty'] = 'That file is empty.';
$string['qaerrorcontent'] = 'The file does not look like a genuine file of that type.';
$string['qaerrornofile'] = 'Choose a file first.';
$string['qaerrorfailed'] = 'The upload could not be completed. Please try again.';
$string['qaerroraccepted'] = 'This document has already been accepted and cannot be replaced.';
$string['qaerroralreadyaccepted'] = 'This document has already been accepted.';
$string['qaerrorcounterrequired'] = 'Upload the countersigned copy before accepting.';
$string['qaerrornoreason'] = 'Write a reason so the learner knows what to correct.';

// Notifications.
$string['qanotifyuploadsubject'] = '{$a->student} uploaded "{$a->document}"';
$string['qanotifyuploadbody'] = '{$a->student} has uploaded a signed copy of "{$a->document}" in {$a->course}. It is waiting for you to check.';
$string['qanotifylink'] = 'Check the document';
$string['qanotifyacceptedsubject'] = 'Accepted: {$a->document}';
$string['qanotifyacceptedbody'] = 'Your signed copy of "{$a->document}" in {$a->course} has been accepted.';
$string['qanotifyreturnedsubject'] = 'Returned: {$a->document}';
$string['qanotifyreturnedbody'] = 'Your signed copy of "{$a->document}" in {$a->course} was returned.

Reason: {$a->reason}

Please correct it and upload a new copy.';
$string['qanotifylinklearner'] = 'Open your documents';

// Privacy.
$string['privacy:qadocuments'] = 'Quality Assurance documents';
$string['privacy:metadata:core_files'] = 'The files learners upload as signed copies, and the countersigned copies uploaded by staff.';
$string['privacy:metadata:block_nvq_matrix_qasubs'] = 'A learner\'s upload of a signed Quality Assurance document in a course, and whether staff accepted or returned it.';
$string['privacy:metadata:block_nvq_matrix_qasubs:userid'] = 'The ID of the learner.';
$string['privacy:metadata:block_nvq_matrix_qasubs:courseid'] = 'The ID of the course.';
$string['privacy:metadata:block_nvq_matrix_qasubs:docid'] = 'The ID of the document.';
$string['privacy:metadata:block_nvq_matrix_qasubs:status'] = 'Awaiting check, accepted or returned.';
$string['privacy:metadata:block_nvq_matrix_qasubs:returnreason'] = 'The reason staff gave when returning the upload.';
$string['privacy:metadata:block_nvq_matrix_qasubs:reviewedby'] = 'The ID of the staff member who accepted or returned it.';
$string['privacy:metadata:block_nvq_matrix_qasubs:reviewedtime'] = 'When it was accepted or returned.';
$string['privacy:metadata:block_nvq_matrix_qasubs:timecreated'] = 'When the learner first uploaded.';
$string['privacy:metadata:block_nvq_matrix_qasubs:timemodified'] = 'When the entry last changed.';
$string['privacy:metadata:block_nvq_matrix_qalog'] = 'A log of every upload, acceptance and return of a Quality Assurance document.';
$string['privacy:metadata:block_nvq_matrix_qalog:userid'] = 'The ID of the learner the entry is about.';
$string['privacy:metadata:block_nvq_matrix_qalog:courseid'] = 'The ID of the course.';
$string['privacy:metadata:block_nvq_matrix_qalog:docid'] = 'The ID of the document.';
$string['privacy:metadata:block_nvq_matrix_qalog:action'] = 'What happened: upload, replacement, acceptance or return.';
$string['privacy:metadata:block_nvq_matrix_qalog:actionby'] = 'The ID of the person who did it.';
$string['privacy:metadata:block_nvq_matrix_qalog:note'] = 'The file names, or the reason for a return.';
$string['privacy:metadata:block_nvq_matrix_qalog:timecreated'] = 'When it happened.';

// Quality Assurance documents, second release (v26.7.2).
$string['nvq_matrix:qaiqasign'] = 'Give the IQA signature on Quality Assurance documents that need one';
$string['qastatusawaitingiqa'] = 'Waiting for IQA';
$string['qaerrorwithiqa'] = 'This document has been signed by the Assessor and is now with the IQA, so it cannot be replaced.';
$string['qaerroriqaonly'] = 'Only an IQA can give the final signature on this document.';
$string['qaerroriqarequired'] = 'Upload the copy that carries the IQA signature.';
$string['qanotifyiqasubject'] = 'IQA signature needed: {$a->document}';
$string['qanotifyiqabody'] = 'The Assessor has signed "{$a->document}" for {$a->student} on {$a->course}. It now needs the IQA signature before it can be accepted.';
$string['qasignoff'] = 'Signatures needed';
$string['qasignoff_help'] = 'Who must sign the document. **Learner only**: the learner signs and uploads it. **Learner and Assessor**: the Assessor then adds their signature and uploads the signed copy. **Learner, Assessor and IQA**: after the Assessor, the IQA signs and uploads the final copy, and only then is the document accepted.';
$string['qasignoff0'] = 'Learner only';
$string['qasignoff1'] = 'Learner and Assessor';
$string['qasignoff2'] = 'Learner, Assessor and IQA';
$string['qasigtrack'] = 'Signatures';
$string['qasiglearner'] = 'Learner';
$string['qasigassessor'] = 'Assessor';
$string['qasigiqa'] = 'IQA';
$string['qasigdone'] = 'signed';
$string['qasigpending'] = 'not yet';
$string['qacounterfinaliqa'] = 'Copy signed by the Assessor';
$string['qaiqafinal'] = 'Final copy signed by the IQA';
$string['qaiqaaccept'] = 'Add IQA signature';
$string['qaiqaaccepttitle'] = 'IQA signature';
$string['qaiqahelp'] = 'Upload the copy that carries the IQA signature. It becomes the final version of this document.';
$string['qaiqapick'] = 'Choose the IQA-signed copy';
$string['qacounterhelpiqa'] = 'Upload the copy with your signature added. It then goes to the IQA, who gives the final signature.';
$string['qawaitingiqa'] = 'Waiting for the IQA to sign.';
$string['qadeleteupload'] = 'Delete upload';
$string['qadeleteuploadtitle'] = 'Delete this upload?';
$string['qadeleteuploadtext'] = 'The uploaded files and any signed copies are removed and the learner can upload again. This cannot be undone.';
$string['qaadminuploadtitle'] = 'Upload for this learner';
$string['qaadminuploadhint'] = 'For a document the learner has already signed. The page does not say who uploaded it.';
$string['qaadminuploadaccepted'] = 'Already checked: mark as accepted';
$string['qaadminuploadsubmit'] = 'Upload for learner';
$string['qasignoffchip1'] = 'Assessor signs';
$string['qasignoffchip2'] = 'Assessor and IQA sign';

// Privacy descriptions that were missing for the history tables (v26.7.2).
$string['privacy:metadata:block_nvq_matrix_grades_history'] = 'Earlier versions of unit grades, kept when a grade is changed, cleared or deleted.';
$string['privacy:metadata:block_nvq_matrix_sampling_history'] = 'Earlier versions of the IQA/EQA sampling status, kept when a status is changed, cleared or deleted.';
$string['privacy:metadata:block_nvq_matrix_status_history'] = 'Earlier versions of the final Pass/Fail status, kept when a status is changed, cleared or deleted.';
$string['privacy:metadata:block_nvq_matrix_unit_comments_history'] = 'Earlier versions of unit-level assessor and IQA comments, kept when a comment is changed, cleared or deleted.';
