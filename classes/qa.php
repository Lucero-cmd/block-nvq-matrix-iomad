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
 * Quality Assurance documents (v26.7.1).
 *
 * All business rules for the QA documents feature live here so the
 * pages (qa.php, qa_admin.php) and endpoints (qa_action.php, qa_file.php)
 * stay thin and every rule is enforced in one place, on the server.
 *
 * Storage (Moodle File API, component block_nvq_matrix):
 *   qa_master        system context, itemid = document id
 *   qa_learner       course context, itemid = submission id
 *   qa_countersigned course context, itemid = submission id (Assessor)
 *   qa_iqasigned     course context, itemid = submission id (IQA, final)
 * None of these is reachable through pluginfile.php (blocks only serve
 * files from block contexts there); every download goes through
 * qa_file.php, which checks who is asking.
 *
 * Dates and times are stored for integrity but never displayed anywhere
 * (client decision).
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_nvq_matrix;

defined('MOODLE_INTERNAL') || die();

/**
 * Rules and data access for Quality Assurance documents.
 */
class qa {

    /** Document type: opened in the browser, nothing returned. */
    const TYPE_READ = 0;
    /** Document type: downloaded, signed, uploaded back by the learner. */
    const TYPE_SIGN = 1;

    /** Submission status: no row exists. */
    const ST_NONE = 0;
    /** Submission status: uploaded, waiting for staff. */
    const ST_AWAITING = 1;
    /** Submission status: accepted by staff. */
    const ST_ACCEPTED = 2;
    /** Submission status: returned to the learner. */
    const ST_RETURNED = 3;
    /** Submission status: the Assessor has signed, the IQA signature is still needed. */
    const ST_AWAITING_IQA = 4;

    /** File area names. */
    const AREA_MASTER = 'qa_master';
    const AREA_LEARNER = 'qa_learner';
    const AREA_COUNTER = 'qa_countersigned';
    const AREA_IQA = 'qa_iqasigned';

    /** Client decision: 20 MB per file. */
    const MAXBYTES = 20971520;
    /** Most files in one upload (a signed form photographed page by page). */
    const MAXFILES = 5;

    /** Extensions a learner or staff member may upload (PDF, Word, images). */
    const UPLOAD_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'];
    /** Extensions an administrator may use for a master document. */
    const MASTER_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    /** Extensions a browser can show itself (everything else downloads). */
    const INLINE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    // ------------------------------------------------------------------
    // Limits and file validation.
    // ------------------------------------------------------------------

    /**
     * The real per-file limit: 20 MB, or less if the server's PHP or
     * Moodle site limit is lower.
     *
     * @return int bytes
     */
    public static function max_upload_bytes(): int {
        global $CFG;
        $server = get_max_upload_file_size($CFG->maxbytes ?? 0);
        if ($server <= 0) {
            return self::MAXBYTES;
        }
        return (int) min(self::MAXBYTES, $server);
    }

    /**
     * Lower-case extension of a file name.
     *
     * @param string $filename
     * @return string
     */
    public static function extension(string $filename): string {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Checks a candidate upload against the extension list, size limit
     * and its actual content (the first bytes must match the claimed
     * type, so a renamed program cannot be uploaded as a PDF).
     *
     * @param string $path    Temporary file on disk.
     * @param string $name    Original file name.
     * @param array  $allowed Allowed extensions.
     * @return string Empty when fine, otherwise a lang string identifier.
     */
    public static function validate_file(string $path, string $name, array $allowed = self::UPLOAD_EXTENSIONS): string {
        $ext = self::extension($name);
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return 'qaerrortype';
        }
        if (!is_file($path)) {
            return 'qaerrorfailed';
        }
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return 'qaerrorempty';
        }
        if ($size > self::max_upload_bytes()) {
            return 'qaerrortoolarge';
        }
        $head = file_get_contents($path, false, null, 0, 16);
        if ($head === false) {
            return 'qaerrorfailed';
        }
        $ok = true;
        switch ($ext) {
            case 'pdf':
                // A PDF may have a few stray bytes before the header.
                $big = file_get_contents($path, false, null, 0, 1024);
                $ok = ($big !== false && strpos($big, '%PDF-') !== false);
                break;
            case 'docx':
                $ok = (strncmp($head, "PK\x03\x04", 4) === 0);
                break;
            case 'doc':
                $ok = (strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0);
                break;
            case 'jpg':
            case 'jpeg':
                $ok = (strncmp($head, "\xFF\xD8\xFF", 3) === 0);
                break;
            case 'png':
                $ok = (strncmp($head, "\x89PNG", 4) === 0);
                break;
            case 'gif':
                $ok = (strncmp($head, 'GIF8', 4) === 0);
                break;
            case 'webp':
                $ok = (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP');
                break;
            case 'heic':
                $ok = (substr($head, 4, 4) === 'ftyp');
                break;
        }
        return $ok ? '' : 'qaerrorcontent';
    }

    /**
     * Normalises a $_FILES entry (single or multiple) to a flat list.
     *
     * @param string $key
     * @return array of ['name','tmp_name','error','size']
     */
    public static function collect_uploads(string $key = 'files'): array {
        $out = [];
        if (empty($_FILES[$key])) {
            return $out;
        }
        $f = $_FILES[$key];
        if (is_array($f['name'])) {
            foreach ($f['name'] as $i => $name) {
                $out[] = ['name' => (string) $name, 'tmp_name' => $f['tmp_name'][$i],
                          'error' => (int) $f['error'][$i], 'size' => (int) $f['size'][$i]];
            }
        } else {
            $out[] = ['name' => (string) $f['name'], 'tmp_name' => $f['tmp_name'],
                      'error' => (int) $f['error'], 'size' => (int) $f['size']];
        }
        return array_values(array_filter($out, function ($u) {
            return $u['error'] !== UPLOAD_ERR_NO_FILE;
        }));
    }

    /**
     * Validates a collected upload list as a whole.
     *
     * @param array $uploads From collect_uploads().
     * @return string Empty when fine, otherwise a lang string identifier.
     */
    public static function validate_uploads(array $uploads): string {
        if (empty($uploads)) {
            return 'qaerrornofile';
        }
        if (count($uploads) > self::MAXFILES) {
            return 'qaerrortoomany';
        }
        foreach ($uploads as $u) {
            if ($u['error'] === UPLOAD_ERR_INI_SIZE || $u['error'] === UPLOAD_ERR_FORM_SIZE) {
                return 'qaerrortoolarge';
            }
            if ($u['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($u['tmp_name'])) {
                return 'qaerrorfailed';
            }
            $err = self::validate_file($u['tmp_name'], $u['name']);
            if ($err !== '') {
                return $err;
            }
        }
        return '';
    }

    // ------------------------------------------------------------------
    // Documents.
    // ------------------------------------------------------------------

    /**
     * Documents with their course ids: every document (the administrator's
     * list, $companyid null) or only one owner's (0 = platform documents,
     * otherwise one company's - the Company Manager's list).
     *
     * @param bool     $enabledonly
     * @param int|null $companyid Null for every owner.
     * @return array docid => object (with ->courseids int[])
     */
    public static function get_documents(bool $enabledonly = false, ?int $companyid = null): array {
        global $DB;
        $conditions = [];
        if ($enabledonly) {
            $conditions['enabled'] = 1;
        }
        if ($companyid !== null) {
            $conditions['companyid'] = $companyid;
        }
        $docs = $DB->get_records('block_nvq_matrix_qadocs', $conditions ?: null, 'companyid ASC, sortorder ASC, id ASC');
        foreach ($docs as $d) {
            $d->courseids = [];
        }
        if (!empty($docs)) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($docs), SQL_PARAMS_NAMED, 'gd');
            $rs = $DB->get_recordset_select('block_nvq_matrix_qadoccourses', "docid $insql", $params, 'courseid ASC', 'id, docid, courseid');
            foreach ($rs as $r) {
                if (isset($docs[$r->docid])) {
                    $docs[$r->docid]->courseids[] = (int) $r->courseid;
                }
            }
            $rs->close();
        }
        return $docs;
    }

    /**
     * Enabled documents on a course, in display order: always the platform
     * documents, plus the documents of the companies named in $companyids.
     *
     * IOMAD fork (v27.2.0): this is deliberately NOT "every document on
     * the course". On a shared course, several companies can each attach
     * their own documents; a caller must say whose it wants, normally
     * through get_learner_documents() or get_tracker_documents(). An empty
     * list gives platform documents only, so a caller that forgets can
     * never show one company's document to another company.
     *
     * @param int   $courseid
     * @param int[] $companyids Companies whose own documents to include.
     * @return array docid => record
     */
    public static function get_course_documents(int $courseid, array $companyids = []): array {
        global $DB;
        $params = ['courseid' => $courseid];
        $ownersql = 'd.companyid = 0';
        $companyids = array_values(array_unique(array_filter(array_map('intval', $companyids))));
        if (!empty($companyids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'cdco');
            $ownersql = "(d.companyid = 0 OR d.companyid $insql)";
            $params += $inparams;
        }
        return $DB->get_records_sql("
            SELECT d.*
              FROM {block_nvq_matrix_qadocs} d
              JOIN {block_nvq_matrix_qadoccourses} dc ON dc.docid = d.id
             WHERE dc.courseid = :courseid AND d.enabled = 1 AND $ownersql
          ORDER BY CASE WHEN d.companyid = 0 THEN 0 ELSE 1 END, d.sortorder ASC, d.id ASC
        ", $params);
    }

    /**
     * The documents one learner gets on a course: the platform documents
     * and the documents of the company (or companies) the learner belongs
     * to - never another company's, even on a shared course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array docid => record
     */
    public static function get_learner_documents(int $courseid, int $userid): array {
        return self::get_course_documents($courseid, self::get_user_company_ids($userid));
    }

    /**
     * The documents shown as columns on a course tracker: the platform
     * documents plus the documents of every company the viewer belongs to
     * or any of the listed learners belongs to. A viewer only ever lists
     * learners they are allowed to see (get_tracker_learners()), so a
     * group-restricted viewer never sees another company's document title.
     * A site administrator sees every document on the course.
     *
     * @param int   $courseid
     * @param int[] $learnerids The learners on the viewer's tracker.
     * @return array docid => record
     */
    public static function get_tracker_documents(int $courseid, array $learnerids): array {
        global $USER, $DB;
        if (is_siteadmin()) {
            $all = $DB->get_fieldset_select('block_nvq_matrix_qadocs', 'DISTINCT companyid', 'companyid > 0');
            return self::get_course_documents($courseid, $all);
        }
        $companyids = self::get_user_company_ids((int) $USER->id);
        foreach (self::get_company_ids_for_users($learnerids) as $ids) {
            $companyids = array_merge($companyids, $ids);
        }
        return self::get_course_documents($courseid, $companyids);
    }

    /**
     * Whether a document applies to one learner on one course: it is on
     * that course and it is a platform document or one of the learner's
     * own company's. Re-checked server-side on every upload, review,
     * download and administrator action.
     *
     * @param \stdClass $doc
     * @param int       $courseid
     * @param int       $userid
     * @return bool
     */
    public static function doc_applies_to_learner(\stdClass $doc, int $courseid, int $userid): bool {
        global $DB;
        if (!$DB->record_exists('block_nvq_matrix_qadoccourses', ['docid' => $doc->id, 'courseid' => $courseid])) {
            return false;
        }
        return self::doc_owned_for_learner($doc, $userid);
    }

    /**
     * The ownership half of doc_applies_to_learner(): a platform document,
     * or one owned by a company the learner belongs to.
     *
     * @param \stdClass $doc
     * @param int       $userid
     * @return bool
     */
    public static function doc_owned_for_learner(\stdClass $doc, int $userid): bool {
        $owner = (int) ($doc->companyid ?? 0);
        return $owner === 0 || in_array($owner, self::get_user_company_ids($userid), true);
    }

    // ------------------------------------------------------------------
    // Companies (IOMAD fork, v27.2.0).
    // ------------------------------------------------------------------

    /**
     * Whether this site has IOMAD's company tables.
     *
     * @return bool
     */
    protected static function has_companies(): bool {
        global $DB;
        static $has = null;
        if ($has === null) {
            $dbman = $DB->get_manager();
            $has = $dbman->table_exists('local_iomad_company_users')
                && $dbman->table_exists('local_iomad_company_courses')
                && $dbman->table_exists('local_iomad_companies');
        }
        return $has;
    }

    /**
     * Company ids for several users at once.
     *
     * @param int[] $userids
     * @return array userid => int[] company ids (users in no company are absent)
     */
    public static function get_company_ids_for_users(array $userids): array {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (empty($userids) || !self::has_companies()) {
            return [];
        }
        $out = [];
        foreach (array_chunk($userids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'ucu');
            $rs = $DB->get_recordset_select('local_iomad_company_users', "userid $insql", $params, '', 'id, userid, companyid');
            foreach ($rs as $r) {
                $out[(int) $r->userid][(int) $r->companyid] = (int) $r->companyid;
            }
            $rs->close();
        }
        return array_map('array_values', $out);
    }

    /**
     * The companies one user belongs to.
     *
     * @param int $userid
     * @return int[]
     */
    public static function get_user_company_ids(int $userid): array {
        static $cache = [];
        if (!array_key_exists($userid, $cache)) {
            $cache[$userid] = self::get_company_ids_for_users([$userid])[$userid] ?? [];
        }
        return $cache[$userid];
    }

    /**
     * Company names by id.
     *
     * @param int[] $companyids
     * @return array companyid => name
     */
    public static function get_company_names(array $companyids): array {
        global $DB;
        $companyids = array_values(array_unique(array_filter(array_map('intval', $companyids))));
        if (empty($companyids) || !self::has_companies()) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'cn');
        return $DB->get_records_select_menu('local_iomad_companies', "id $insql", $params, 'name ASC', 'id, name');
    }

    /**
     * The companies whose QA documents a user may manage, each with the
     * courses they may attach those documents to.
     *
     * A company is listed only when all three hold:
     *   - the user is a manager of that company in IOMAD
     *     (local_iomad_company_users.managertype = 1) and the company is
     *     neither suspended nor terminated;
     *   - the course is linked to that company (local_iomad_company_courses
     *     or local_iomad_company_shared_courses - the company's own courses
     *     and the shared courses it uses);
     *   - the user holds block/nvq_matrix:qamanage on that course.
     * So a manager can only ever reach their own company's documents, and
     * only attach them to courses their company actually uses.
     *
     * @param int|null $userid Defaults to the current user.
     * @return array companyid => (object) {id, name, courses: [courseid => name]}
     */
    public static function get_manageable_companies(?int $userid = null): array {
        global $USER, $DB;
        static $cache = [];
        $userid = $userid ?? (int) $USER->id;
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }
        if (!self::has_companies()) {
            return $cache[$userid] = [];
        }
        // DISTINCT: IOMAD keeps one company_users row per department, so a
        // manager in several departments of one company has several rows.
        $companies = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.name
              FROM {local_iomad_company_users} cu
              JOIN {local_iomad_companies} c ON c.id = cu.companyid
             WHERE cu.userid = :userid AND cu.managertype = 1 AND c.suspended = 0 AND c.isterminated = 0
          ORDER BY c.name ASC
        ", ['userid' => $userid]);
        // A company's courses are its own (local_iomad_company_courses) and
        // the shared courses it has been given (also recorded per company
        // in local_iomad_company_shared_courses by IOMAD's sharing flow).
        // Both are read, so a shared course is always offered even where
        // IOMAD did not also write a company_courses row for it.
        $sharedsql = $DB->get_manager()->table_exists('local_iomad_company_shared_courses')
            ? 'UNION SELECT sc.courseid FROM {local_iomad_company_shared_courses} sc WHERE sc.companyid = :c2'
            : '';
        $out = [];
        foreach ($companies as $c) {
            // Moodle refuses a named parameter the SQL does not use, so :c2
            // is only passed when the shared-courses part is present.
            $params = ['c1' => $c->id, 'siteid' => SITEID];
            if ($sharedsql !== '') {
                $params['c2'] = $c->id;
            }
            $courses = $DB->get_records_sql("
                SELECT co.id, co.fullname
                  FROM {course} co
                 WHERE co.id <> :siteid
                   AND co.id IN (SELECT cc.courseid FROM {local_iomad_company_courses} cc WHERE cc.companyid = :c1
                                 $sharedsql)
              ORDER BY co.fullname ASC
            ", $params);
            $allowed = [];
            foreach ($courses as $co) {
                $ctx = \context_course::instance((int) $co->id, IGNORE_MISSING);
                if ($ctx && has_capability('block/nvq_matrix:qamanage', $ctx, $userid)) {
                    $allowed[(int) $co->id] = format_string($co->fullname, true, ['context' => $ctx, 'escape' => false]);
                }
            }
            if (!empty($allowed)) {
                $out[(int) $c->id] = (object) ['id' => (int) $c->id, 'name' => $c->name, 'courses' => $allowed];
            }
        }
        return $cache[$userid] = $out;
    }

    /**
     * Whether the current user manages the QA documents of one company
     * (see get_manageable_companies()).
     *
     * @param int $companyid
     * @return bool
     */
    public static function can_manage_company(int $companyid): bool {
        return $companyid > 0 && isset(self::get_manageable_companies()[$companyid]);
    }

    /**
     * The course ids a document should keep after a Company Manager saves
     * it: what they chose, limited to the courses they may manage, plus
     * any existing links outside their reach (left exactly as they were,
     * never removed or added by someone who could not see them).
     *
     * @param int[] $existing  Current course ids of the document.
     * @param int[] $submitted Course ids the manager chose.
     * @param int[] $allowed   Course ids the manager may manage.
     * @return int[]
     */
    public static function merge_managed_courses(array $existing, array $submitted, array $allowed): array {
        $existing = array_map('intval', $existing);
        $allowed = array_map('intval', $allowed);
        $chosen = array_intersect(array_map('intval', $submitted), $allowed);
        $untouchable = array_diff($existing, $allowed);
        return array_values(array_unique(array_merge($chosen, $untouchable)));
    }

    /**
     * Replaces the set of courses a document applies to.
     *
     * @param int   $docid
     * @param int[] $courseids
     */
    public static function set_document_courses(int $docid, array $courseids): void {
        global $DB;
        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        $existing = $DB->get_fieldset_select('block_nvq_matrix_qadoccourses', 'courseid', 'docid = ?', [$docid]);
        $existing = array_map('intval', $existing);
        foreach (array_diff($existing, $courseids) as $cid) {
            $DB->delete_records('block_nvq_matrix_qadoccourses', ['docid' => $docid, 'courseid' => $cid]);
        }
        foreach (array_diff($courseids, $existing) as $cid) {
            if ($cid > 0) {
                $DB->insert_record('block_nvq_matrix_qadoccourses', (object) ['docid' => $docid, 'courseid' => $cid]);
            }
        }
    }

    /**
     * Deletes a document, its course links, every submission, log entry
     * and every stored file belonging to it.
     *
     * @param int $docid
     */
    public static function delete_document(int $docid): void {
        global $DB;
        $fs = get_file_storage();
        $subs = $DB->get_records('block_nvq_matrix_qasubs', ['docid' => $docid]);
        foreach ($subs as $s) {
            self::delete_submission_files($s);
        }
        $fs->delete_area_files(\context_system::instance()->id, 'block_nvq_matrix', self::AREA_MASTER, $docid);
        $DB->delete_records('block_nvq_matrix_qalog', ['docid' => $docid]);
        $DB->delete_records('block_nvq_matrix_qasubs', ['docid' => $docid]);
        $DB->delete_records('block_nvq_matrix_qadoccourses', ['docid' => $docid]);
        $DB->delete_records('block_nvq_matrix_qadocs', ['id' => $docid]);
    }

    /**
     * Swaps a document's position with its neighbour.
     *
     * @param int $docid
     * @param int $direction -1 = up, 1 = down
     */
    public static function move_document(int $docid, int $direction): void {
        global $DB;
        // Only among documents with the same owner (IOMAD fork, v27.2.0):
        // a company reordering its own list must never shift the platform
        // documents or another company's.
        $owner = $DB->get_field('block_nvq_matrix_qadocs', 'companyid', ['id' => $docid]);
        if ($owner === false) {
            return;
        }
        $docs = array_values($DB->get_records('block_nvq_matrix_qadocs', ['companyid' => (int) $owner],
            'sortorder ASC, id ASC', 'id, sortorder'));
        // Re-number first so equal sort orders cannot make a move a no-op.
        foreach ($docs as $i => $d) {
            if ((int) $d->sortorder !== $i + 1) {
                $DB->set_field('block_nvq_matrix_qadocs', 'sortorder', $i + 1, ['id' => $d->id]);
                $d->sortorder = $i + 1;
            }
        }
        foreach ($docs as $i => $d) {
            if ((int) $d->id !== $docid) {
                continue;
            }
            $j = $i + ($direction < 0 ? -1 : 1);
            if (isset($docs[$j])) {
                $DB->set_field('block_nvq_matrix_qadocs', 'sortorder', $docs[$j]->sortorder, ['id' => $d->id]);
                $DB->set_field('block_nvq_matrix_qadocs', 'sortorder', $d->sortorder, ['id' => $docs[$j]->id]);
            }
            return;
        }
    }

    /**
     * The master file of a document, if any.
     *
     * @param int $docid
     * @return \stored_file|null
     */
    public static function get_master_file(int $docid): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id, 'block_nvq_matrix', self::AREA_MASTER, $docid, 'id ASC', false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Checks the master file waiting in a form's draft area the same way a
     * learner's upload is checked (extension, size and actual content),
     * before anything is saved. Used for documents a Company Manager
     * uploads (IOMAD fork, v27.2.0); the file picker only checks the
     * extension. Run from the form's validation, so a bad file is refused
     * on the form and neither the document nor its current master file is
     * touched.
     *
     * @param int $draftitemid
     * @param int $userid Owner of the draft area.
     * @return string Empty when fine (or no file), otherwise a lang string identifier.
     */
    public static function check_draft_master(int $draftitemid, int $userid): string {
        $files = get_file_storage()->get_area_files(
            \context_user::instance($userid)->id, 'user', 'draft', $draftitemid, 'id', false
        );
        foreach ($files as $file) {
            $tmp = make_request_directory() . '/master';
            $file->copy_content_to($tmp);
            $err = self::validate_file($tmp, $file->get_filename(), self::MASTER_EXTENSIONS);
            @unlink($tmp);
            if ($err !== '') {
                return $err;
            }
        }
        return '';
    }

    /**
     * Replaces a document's master with a copy of a Moodle File resource's
     * file (the "import from existing activity" option).
     *
     * @param int $docid
     * @param int $cmid Course module id of a File resource.
     * @return string Empty on success, otherwise a lang string identifier.
     */
    public static function import_master_from_resource(int $docid, int $cmid): string {
        global $DB;
        $cm = $DB->get_record_sql("
            SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.id = :cmid AND m.name = 'resource' AND cm.deletioninprogress = 0
        ", ['cmid' => $cmid]);
        if (!$cm) {
            return 'qaimportnotfound';
        }
        $modcontext = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$modcontext) {
            return 'qaimportnotfound';
        }
        $files = get_file_storage()->get_area_files($modcontext->id, 'mod_resource', 'content', 0, 'sortorder, id', false);
        if (empty($files)) {
            return 'qaimportnofile';
        }
        $source = reset($files);
        if (!in_array(self::extension($source->get_filename()), self::MASTER_EXTENSIONS, true)) {
            return 'qaimportbadtype';
        }
        $fs = get_file_storage();
        $sysid = \context_system::instance()->id;
        $fs->delete_area_files($sysid, 'block_nvq_matrix', self::AREA_MASTER, $docid);
        $fs->create_file_from_storedfile([
            'contextid' => $sysid,
            'component' => 'block_nvq_matrix',
            'filearea'  => self::AREA_MASTER,
            'itemid'    => $docid,
            'filepath'  => '/',
            'filename'  => $source->get_filename(),
        ], $source);
        return '';
    }

    // ------------------------------------------------------------------
    // Submissions.
    // ------------------------------------------------------------------

    /**
     * @param int $docid
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_submission(int $docid, int $courseid, int $userid): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('block_nvq_matrix_qasubs', ['docid' => $docid, 'courseid' => $courseid, 'userid' => $userid]);
        return $rec ?: null;
    }

    /**
     * All of one learner's submissions in a course, keyed by document id.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_learner_submissions(int $courseid, int $userid): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('block_nvq_matrix_qasubs', ['courseid' => $courseid, 'userid' => $userid]) as $s) {
            $out[(int) $s->docid] = $s;
        }
        return $out;
    }

    /**
     * All submissions in a course: userid => [docid => submission].
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_submissions(int $courseid): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('block_nvq_matrix_qasubs', ['courseid' => $courseid]) as $s) {
            $out[(int) $s->userid][(int) $s->docid] = $s;
        }
        return $out;
    }

    /**
     * Stored files of a submission.
     *
     * @param \stdClass $sub
     * @param string    $area AREA_LEARNER or AREA_COUNTER
     * @return \stored_file[]
     */
    public static function get_submission_files(\stdClass $sub, string $area): array {
        $ctx = \context_course::instance((int) $sub->courseid, IGNORE_MISSING);
        if (!$ctx) {
            return [];
        }
        return array_values(get_file_storage()->get_area_files(
            $ctx->id, 'block_nvq_matrix', $area, (int) $sub->id, 'filename ASC', false
        ));
    }

    /**
     * Removes the stored files of a submission.
     *
     * @param \stdClass $sub
     * @param string|null $area Limit to one area, or null for all three.
     */
    public static function delete_submission_files(\stdClass $sub, ?string $area = null): void {
        $ctx = \context_course::instance((int) $sub->courseid, IGNORE_MISSING);
        if (!$ctx) {
            return;
        }
        $fs = get_file_storage();
        foreach ([self::AREA_LEARNER, self::AREA_COUNTER, self::AREA_IQA] as $a) {
            if ($area === null || $area === $a) {
                $fs->delete_area_files($ctx->id, 'block_nvq_matrix', $a, (int) $sub->id);
            }
        }
    }

    /**
     * Saves validated uploads into a file area, replacing what was there.
     *
     * @param array     $uploads Validated list from collect_uploads().
     * @param \stdClass $sub
     * @param string    $area
     * @return string[] Stored file names.
     */
    protected static function store_files(array $uploads, \stdClass $sub, string $area): array {
        $ctx = \context_course::instance((int) $sub->courseid);
        $fs = get_file_storage();
        $fs->delete_area_files($ctx->id, 'block_nvq_matrix', $area, (int) $sub->id);
        $names = [];
        foreach ($uploads as $u) {
            $name = clean_param($u['name'], PARAM_FILE);
            if ($name === '' || $name === '.' || $name === '..') {
                $name = 'document.' . self::extension($u['name']);
            }
            // Never overwrite a sibling that arrived with the same name.
            $base = pathinfo($name, PATHINFO_FILENAME);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $n = 1;
            while (in_array($name, $names, true)) {
                $n++;
                $name = $base . '-' . $n . ($ext !== '' ? '.' . $ext : '');
            }
            $fs->create_file_from_pathname([
                'contextid' => $ctx->id,
                'component' => 'block_nvq_matrix',
                'filearea'  => $area,
                'itemid'    => (int) $sub->id,
                'filepath'  => '/',
                'filename'  => $name,
            ], $u['tmp_name']);
            $names[] = $name;
        }
        return $names;
    }

    /**
     * Appends an audit entry.
     *
     * @param \stdClass $sub
     * @param string    $action
     * @param int       $actionby
     * @param string    $note
     */
    protected static function log(\stdClass $sub, string $action, int $actionby, string $note = ''): void {
        global $DB;
        $DB->insert_record('block_nvq_matrix_qalog', (object) [
            'subid'       => (int) $sub->id,
            'docid'       => (int) $sub->docid,
            'courseid'    => (int) $sub->courseid,
            'userid'      => (int) $sub->userid,
            'action'      => $action,
            'actionby'    => $actionby,
            'note'        => $note,
            'timecreated' => time(),
        ]);
    }

    /**
     * Learner uploads (or replaces) their signed copy. Re-checks every
     * rule server-side; the caller has already validated the files.
     *
     * @param \stdClass $doc
     * @param int       $courseid
     * @param int       $userid   The learner (must be the current user).
     * @param array     $uploads
     * @return string Empty on success, otherwise a lang string identifier.
     */
    public static function learner_upload(\stdClass $doc, int $courseid, int $userid, array $uploads): string {
        global $DB;

        if ((int) $doc->doctype !== self::TYPE_SIGN || !(int) $doc->enabled) {
            return 'qaerrornotallowed';
        }
        // On this course AND the platform's or this learner's own
        // company's (IOMAD fork, v27.2.0).
        if (!self::doc_applies_to_learner($doc, $courseid, $userid)) {
            return 'qaerrornotallowed';
        }
        if (matrix_data::is_withdrawn($userid, $courseid)) {
            return 'withdrawnlocked';
        }

        $now = time();
        $sub = self::get_submission((int) $doc->id, $courseid, $userid);
        if ($sub && (int) $sub->status === self::ST_ACCEPTED) {
            return 'qaerroraccepted';
        }
        if ($sub && (int) $sub->status === self::ST_AWAITING_IQA) {
            return 'qaerrorwithiqa';
        }
        $isreplace = (bool) $sub;
        if (!$sub) {
            $sub = (object) [
                'docid' => (int) $doc->id, 'courseid' => $courseid, 'userid' => $userid,
                'status' => self::ST_AWAITING, 'returnreason' => null, 'reviewedby' => null, 'reviewedtime' => null,
                'timecreated' => $now, 'timemodified' => $now,
            ];
            $sub->id = $DB->insert_record('block_nvq_matrix_qasubs', $sub);
        }

        // A new upload replaces the old copy outright, and any staff
        // signature on the old copy no longer applies to it.
        $names = self::store_files($uploads, $sub, self::AREA_LEARNER);
        self::delete_submission_files($sub, self::AREA_COUNTER);
        self::delete_submission_files($sub, self::AREA_IQA);

        $DB->update_record('block_nvq_matrix_qasubs', (object) [
            'id' => $sub->id, 'status' => self::ST_AWAITING, 'returnreason' => null,
            'timemodified' => $now,
        ]);
        $sub->status = self::ST_AWAITING;
        self::log($sub, $isreplace ? 'reupload' : 'upload', $userid, implode(', ', $names));

        self::notify_assessors($doc, $courseid, $userid);
        return '';
    }

    /**
     * Whether a document needs the IQA signature as well as the Assessor's.
     *
     * @param \stdClass $doc
     * @return bool
     */
    public static function needs_iqa(\stdClass $doc): bool {
        return (int) $doc->doctype === self::TYPE_SIGN && !empty($doc->needscounter) && !empty($doc->needsiqa);
    }

    /**
     * Whether the current user may give the IQA signature in a course.
     *
     * @param \context_course $ctx
     * @return bool
     */
    public static function can_iqa_sign(\context_course $ctx): bool {
        return is_siteadmin() || has_capability('block/nvq_matrix:qaiqasign', $ctx);
    }

    /**
     * Staff accept a submission. The signing runs in turn:
     *   Learner uploads, the Assessor signs (countersigned copy), and for a
     *   document that needs it the IQA signs last (final copy). Accepted
     *   only when the last needed signature is in.
     *
     * @param \stdClass $doc
     * @param \stdClass $sub
     * @param int       $staffid
     * @param array     $counteruploads Validated uploads, possibly empty.
     * @return string Empty on success, otherwise a lang string identifier.
     */
    public static function accept(\stdClass $doc, \stdClass $sub, int $staffid, array $counteruploads): string {
        global $DB;

        $status = (int) $sub->status;
        if ($status === self::ST_ACCEPTED) {
            return 'qaerroralreadyaccepted';
        }
        if (matrix_data::is_withdrawn((int) $sub->userid, (int) $sub->courseid)) {
            return 'withdrawnlocked';
        }
        $ctx = \context_course::instance((int) $sub->courseid, IGNORE_MISSING);
        if (!$ctx) {
            return 'qaerrornotallowed';
        }
        $now = time();

        // ---- IQA stage: the Assessor has already signed. ----
        if ($status === self::ST_AWAITING_IQA) {
            if (!self::can_iqa_sign($ctx)) {
                return 'qaerroriqaonly';
            }
            if (empty($counteruploads) && empty(self::get_submission_files($sub, self::AREA_IQA))) {
                return 'qaerroriqarequired';
            }
            $note = '';
            if (!empty($counteruploads)) {
                $note = implode(', ', self::store_files($counteruploads, $sub, self::AREA_IQA));
            }
            $DB->update_record('block_nvq_matrix_qasubs', (object) [
                'id' => $sub->id, 'status' => self::ST_ACCEPTED, 'returnreason' => null,
                'reviewedby' => $staffid, 'reviewedtime' => $now, 'timemodified' => $now,
            ]);
            self::log($sub, 'iqasign', $staffid, $note);
            self::notify_learner($doc, $sub, true, '');
            return '';
        }

        // ---- First staff stage: Assessor check (and signature). ----
        if ((int) $doc->needscounter && empty($counteruploads) && empty(self::get_submission_files($sub, self::AREA_COUNTER))) {
            return 'qaerrorcounterrequired';
        }
        $note = '';
        if (!empty($counteruploads)) {
            $note = implode(', ', self::store_files($counteruploads, $sub, self::AREA_COUNTER));
        }
        $needsiqa = self::needs_iqa($doc);
        $DB->update_record('block_nvq_matrix_qasubs', (object) [
            'id' => $sub->id, 'status' => $needsiqa ? self::ST_AWAITING_IQA : self::ST_ACCEPTED, 'returnreason' => null,
            'reviewedby' => $staffid, 'reviewedtime' => $now, 'timemodified' => $now,
        ]);
        if ($needsiqa) {
            self::log($sub, 'accept', $staffid, $note);
            self::notify_iqa($doc, (int) $sub->courseid, (int) $sub->userid);
        } else {
            self::log($sub, 'accept', $staffid, $note);
            self::notify_learner($doc, $sub, true, '');
        }
        return '';
    }

    /**
     * Administrator deletes a submission (a wrong document uploaded by a
     * learner or an assessor): the row, every file and the learner's
     * checklist entry go, so the learner can upload again. The audit log
     * keeps a "delete" entry.
     *
     * @param \stdClass $sub
     * @param int       $adminid
     * @return string Empty on success, otherwise a lang string identifier.
     */
    public static function delete_submission(\stdClass $sub, int $adminid): string {
        global $DB;
        if (!is_siteadmin()) {
            return 'qaerrornotallowed';
        }
        $names = [];
        foreach ([self::AREA_LEARNER, self::AREA_COUNTER, self::AREA_IQA] as $area) {
            foreach (self::get_submission_files($sub, $area) as $f) {
                $names[] = $f->get_filename();
            }
        }
        self::log($sub, 'delete', $adminid, implode(', ', $names));
        self::delete_submission_files($sub);
        $DB->delete_records('block_nvq_matrix_qasubs', ['id' => $sub->id]);
        return '';
    }

    /**
     * Administrator uploads a document on a learner's behalf (for example
     * one the learner had already signed before this feature existed).
     * Nothing shown to the learner or staff says who uploaded it.
     *
     * @param \stdClass $doc
     * @param int       $courseid
     * @param int       $userid   The learner.
     * @param array     $uploads  Validated uploads.
     * @param bool      $accepted Mark it as already checked and accepted.
     * @param int       $adminid
     * @return string Empty on success, otherwise a lang string identifier.
     */
    public static function admin_upload(\stdClass $doc, int $courseid, int $userid, array $uploads, bool $accepted, int $adminid): string {
        global $DB;
        if (!is_siteadmin()) {
            return 'qaerrornotallowed';
        }
        if ((int) $doc->doctype !== self::TYPE_SIGN || !self::doc_applies_to_learner($doc, $courseid, $userid)) {
            return 'qaerrornotallowed';
        }
        $ctx = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$ctx || !(is_enrolled($ctx, $userid) || $DB->record_exists('block_nvq_matrix_qasubs', ['courseid' => $courseid, 'userid' => $userid]))) {
            return 'qaerrornotallowed';
        }
        if (matrix_data::is_withdrawn($userid, $courseid)) {
            return 'withdrawnlocked';
        }

        $now = time();
        $sub = self::get_submission((int) $doc->id, $courseid, $userid);
        if (!$sub) {
            $sub = (object) [
                'docid' => (int) $doc->id, 'courseid' => $courseid, 'userid' => $userid,
                'status' => self::ST_AWAITING, 'returnreason' => null, 'reviewedby' => null, 'reviewedtime' => null,
                'timecreated' => $now, 'timemodified' => $now,
            ];
            $sub->id = $DB->insert_record('block_nvq_matrix_qasubs', $sub);
        }
        $names = self::store_files($uploads, $sub, self::AREA_LEARNER);
        self::delete_submission_files($sub, self::AREA_COUNTER);
        self::delete_submission_files($sub, self::AREA_IQA);
        $DB->update_record('block_nvq_matrix_qasubs', (object) [
            'id' => $sub->id, 'status' => $accepted ? self::ST_ACCEPTED : self::ST_AWAITING, 'returnreason' => null,
            'reviewedby' => null, 'reviewedtime' => null, 'timemodified' => $now,
        ]);
        $sub->status = $accepted ? self::ST_ACCEPTED : self::ST_AWAITING;
        self::log($sub, 'adminupload', $adminid, implode(', ', $names));
        if (!$accepted) {
            self::notify_assessors($doc, $courseid, $userid);
        }
        return '';
    }

    /**
     * Staff return a submission to the learner with a reason.
     *
     * @param \stdClass $doc
     * @param \stdClass $sub
     * @param int       $staffid
     * @param string    $reason
     * @return string Empty on success, otherwise a lang string identifier.
     */
    public static function return_submission(\stdClass $doc, \stdClass $sub, int $staffid, string $reason): string {
        global $DB;

        $reason = trim($reason);
        if ($reason === '') {
            return 'qaerrornoreason';
        }
        if (matrix_data::is_withdrawn((int) $sub->userid, (int) $sub->courseid)) {
            return 'withdrawnlocked';
        }
        // A returned document starts again, so every staff signature on
        // it is removed; the learner's own upload stays until they replace
        // it with a corrected one.
        self::delete_submission_files($sub, self::AREA_COUNTER);
        self::delete_submission_files($sub, self::AREA_IQA);
        $now = time();
        $DB->update_record('block_nvq_matrix_qasubs', (object) [
            'id' => $sub->id, 'status' => self::ST_RETURNED, 'returnreason' => $reason,
            'reviewedby' => $staffid, 'reviewedtime' => $now, 'timemodified' => $now,
        ]);
        self::log($sub, 'return', $staffid, $reason);
        self::notify_learner($doc, $sub, false, $reason);
        return '';
    }

    // ------------------------------------------------------------------
    // Access rules.
    // ------------------------------------------------------------------

    /**
     * Which learners the current user is limited to in a course, or null
     * when not limited.
     *
     * Rewritten for the IOMAD fork (v27.0.4) to replace the plain-Moodle
     * source's hardcoded 'companymanager'/'eqa' role-shortname check,
     * which violates the multi-tenant isolation guide's Section 2
     * principle ("restrict to whatever group(s) the viewer actually
     * belongs to — never hardcode a role shortname") and would also
     * simply never fire on this site, since the Company Manager role
     * here is 'companycoursenoneditor', not 'companymanager'. Two
     * genuinely distinct policies, same as everywhere else in this
     * plugin:
     *
     *   Mandatory isolation (matrix_data::is_group_restricted()): any
     *     role lacking moodle/site:accessallgroups on a forced-
     *     separate-groups course — fail-closed, "no group" means sees
     *     nobody. Covers Company Manager identically to Assessor/IQA
     *     wherever this plugin already enforces isolation, without
     *     naming a single role.
     *   Opt-in inspection-group scoping
     *     (matrix_data::get_viewer_inspection_group_memberids()): a
     *     viewer who is NOT mandatorily restricted (holds
     *     accessallgroups, e.g. EQA) but has been placed in a course
     *     group for a one-off inspection — voluntary narrowing, not a
     *     security boundary, so "no group" falls back to seeing
     *     everyone, as before this feature existed.
     * Order matters, and mirrors view.php's own student-list scoping
     * exactly: site administrators are never limited; mandatory
     * isolation is checked NEXT and applies to everyone it covers,
     * including holders of :qareview (an Assessor or IQA on a shared
     * course is still confined to their own company's group); only an
     * unrestricted :qareview holder is exempt from the opt-in
     * inspection-group narrowing. The plain-Moodle source exempted
     * every :qareview holder up front - harmless on a single-tenant
     * site, but on this fork it let an Assessor/IQA see and act on
     * every company's learners on a shared course.
     *
     * @param \context_course $ctx
     * @return array|null userid => true, or null for no limit
     */
    public static function get_viewer_scope(\context_course $ctx): ?array {
        global $USER;
        static $cache = [];
        $key = $ctx->id . ':' . (int) $USER->id;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (is_siteadmin()) {
            return $cache[$key] = null;
        }
        $courseid = (int) $ctx->instanceid;
        if (matrix_data::is_group_restricted($courseid, (int) $USER->id)) {
            return $cache[$key] = matrix_data::get_viewer_group_memberids($courseid, (int) $USER->id);
        }
        if (has_capability('block/nvq_matrix:qareview', $ctx)) {
            return $cache[$key] = null;
        }
        return $cache[$key] = matrix_data::get_viewer_inspection_group_memberids($courseid, (int) $USER->id);
    }

    /**
     * Works out what the current user may do on a learner's checklist.
     * Returns null when the user may not see it at all.
     *
     * @param \context_course $ctx
     * @param int             $studentid
     * @return \stdClass|null  {isself, canview, canupload, canreview, candelete, canadminupload}
     */
    public static function get_access(\context_course $ctx, int $studentid): ?\stdClass {
        global $USER, $DB;

        $viewall = has_capability('block/nvq_matrix:viewall', $ctx);
        $isself = ($studentid === (int) $USER->id);

        if ($isself) {
            // Own checklist: any active participant of the course, or a
            // user who can see everything (an administrator checking the
            // page).
            if (!$viewall && !is_enrolled($ctx, $USER->id, '', true)) {
                return null;
            }
            return (object) [
                'isself' => true, 'canview' => true,
                'canupload' => is_enrolled($ctx, $USER->id, '', true),
                'canreview' => false, 'candelete' => false, 'canadminupload' => false,
            ];
        }

        if (!$viewall) {
            return null;
        }
        $scope = self::get_viewer_scope($ctx);
        if ($scope !== null && !isset($scope[$studentid])) {
            return null;
        }
        $known = is_enrolled($ctx, $studentid)
            || $DB->record_exists('block_nvq_matrix_qasubs', ['courseid' => $ctx->instanceid, 'userid' => $studentid]);
        if (!$known) {
            return null;
        }
        $admin = is_siteadmin();
        return (object) [
            'isself' => false, 'canview' => true, 'canupload' => false,
            'canreview' => has_capability('block/nvq_matrix:qareview', $ctx),
            'candelete' => $admin, 'canadminupload' => $admin,
        ];
    }

    /**
     * Whether the current user may open a master document.
     *
     * @param int $docid
     * @return bool
     */
    public static function can_open_master(int $docid): bool {
        global $USER, $DB;
        if (is_siteadmin()) {
            return true;
        }
        // A company's own document (IOMAD fork, v27.2.0): only people in
        // that company may open it, whatever course they share with it -
        // on a shared course another company's staff and learners are
        // enrolled too, so being on the course is not enough.
        $owner = (int) $DB->get_field('block_nvq_matrix_qadocs', 'companyid', ['id' => $docid]);
        if ($owner > 0 && self::can_manage_company($owner)) {
            // The owning company's manager, previewing on qa_manage.php.
            return true;
        }
        $staffonly = false;
        if ($owner > 0) {
            $mine = self::get_user_company_ids((int) $USER->id);
            if (!in_array($owner, $mine, true)) {
                if (!empty($mine)) {
                    // A member of another company: never.
                    return false;
                }
                // Platform staff who belong to no company (for example an
                // IQA or EQA working across providers) see this company's
                // learners and so the document's column and checklist
                // entry; they may open its form, but only as staff on a
                // course it is on - never through enrolment alone, which
                // would let a learner outside every company in.
                $staffonly = true;
            }
        }
        $courseids = $DB->get_fieldset_select('block_nvq_matrix_qadoccourses', 'courseid', 'docid = ?', [$docid]);
        foreach ($courseids as $cid) {
            $ctx = \context_course::instance((int) $cid, IGNORE_MISSING);
            if (!$ctx) {
                continue;
            }
            if (has_capability('block/nvq_matrix:viewall', $ctx)
                    || (!$staffonly && is_enrolled($ctx, $USER->id, '', true))) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Presentation helpers.
    // ------------------------------------------------------------------

    /**
     * Effective status of a document for a learner.
     *
     * @param \stdClass|null $sub
     * @return int
     */
    public static function status_of(?\stdClass $sub): int {
        return $sub ? (int) $sub->status : self::ST_NONE;
    }

    /**
     * Counts for a learner's Sign and return documents in a course.
     *
     * @param array $docs   From get_course_documents().
     * @param array $subs   Learner submissions keyed by docid.
     * @return \stdClass {total, accepted, awaiting, returned, none, complete}
     */
    public static function summarise(array $docs, array $subs): \stdClass {
        $s = (object) ['total' => 0, 'accepted' => 0, 'awaiting' => 0, 'returned' => 0, 'none' => 0, 'complete' => false];
        foreach ($docs as $d) {
            if ((int) $d->doctype !== self::TYPE_SIGN) {
                continue;
            }
            $s->total++;
            switch (self::status_of($subs[(int) $d->id] ?? null)) {
                case self::ST_ACCEPTED:
                    $s->accepted++;
                    break;
                case self::ST_AWAITING:
                case self::ST_AWAITING_IQA:
                    $s->awaiting++;
                    break;
                case self::ST_RETURNED:
                    $s->returned++;
                    break;
                default:
                    $s->none++;
            }
        }
        $s->complete = ($s->total > 0 && $s->accepted === $s->total);
        return $s;
    }

    /**
     * URL for a stored file, served by qa_file.php.
     *
     * @param string    $kind master|learner|counter
     * @param int       $id   Document id (master) or submission id.
     * @param \stored_file $file
     * @param bool      $download Force a download.
     * @return \moodle_url
     */
    public static function file_url(string $kind, int $id, \stored_file $file, bool $download = false): \moodle_url {
        $params = ['kind' => $kind, 'id' => $id, 'fileid' => (int) $file->get_id()];
        if ($download) {
            $params['download'] = 1;
        }
        return new \moodle_url('/blocks/nvq_matrix/qa_file.php', $params);
    }

    /**
     * Whether a file name can be shown inside the browser.
     *
     * @param string $filename
     * @return bool
     */
    public static function is_inline(string $filename): bool {
        return in_array(self::extension($filename), self::INLINE_EXTENSIONS, true);
    }

    /**
     * Counts for the link shown in the matrix: the learner's progress in
     * one course, or null when the course has no Sign and return
     * documents and no Read documents.
     *
     * @param int $courseid
     * @param int $studentid
     * @return \stdClass|null {docs, total, accepted, awaiting, returned}
     */
    public static function link_summary(int $courseid, int $studentid): ?\stdClass {
        $docs = self::get_learner_documents($courseid, $studentid);
        if (empty($docs)) {
            return null;
        }
        $sum = self::summarise($docs, self::get_learner_submissions($courseid, $studentid));
        $sum->docs = count($docs);
        return $sum;
    }

    /**
     * Learners to show on a course tracker: active participants who are
     * not staff.
     *
     * @param \context_course $ctx
     * @return array userid => user record (name fields only)
     */
    public static function get_tracker_learners(\context_course $ctx): array {
        $fields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
        $users = get_enrolled_users($ctx, '', 0, $fields, 'u.lastname ASC, u.firstname ASC', 0, 0, true);
        $scope = self::get_viewer_scope($ctx);
        $out = [];
        foreach ($users as $u) {
            if (has_capability('block/nvq_matrix:viewall', $ctx, $u->id)) {
                continue;
            }
            if ($scope !== null && !isset($scope[(int) $u->id])) {
                continue;
            }
            $out[(int) $u->id] = $u;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Notifications.
    // ------------------------------------------------------------------

    /**
     * Tells the course's assessors a learner has uploaded a document.
     * Recipients are found by role in the course (the same rule as
     * evidence notifications).
     *
     * @param \stdClass $doc
     * @param int       $courseid
     * @param int       $studentid
     */
    protected static function notify_assessors(\stdClass $doc, int $courseid, int $studentid): void {
        global $DB;
        try {
            $ctx = \context_course::instance($courseid, IGNORE_MISSING);
            $student = $DB->get_record('user', ['id' => $studentid], '*', IGNORE_MISSING);
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
            if (!$ctx || !$student || !$course) {
                return;
            }
            $a = (object) [
                'student'  => fullname($student),
                'course'   => format_string($course->fullname, true, ['context' => $ctx, 'escape' => false]),
                'document' => format_string($doc->title, true, ['context' => $ctx, 'escape' => false]),
            ];
            $subject = get_string('qanotifyuploadsubject', 'block_nvq_matrix', $a);
            $body = get_string('qanotifyuploadbody', 'block_nvq_matrix', $a);
            $url = new \moodle_url('/blocks/nvq_matrix/qa.php', ['courseid' => $courseid, 'studentid' => $studentid]);

            foreach (matrix_data::get_assessor_recipient_ids($courseid, $ctx, $studentid) as $uid) {
                if ((int) $uid === $studentid) {
                    continue;
                }
                $to = $DB->get_record('user', ['id' => $uid, 'deleted' => 0], '*', IGNORE_MISSING);
                if (!$to) {
                    continue;
                }
                $m = new \core\message\message();
                $m->component = 'block_nvq_matrix';
                $m->name = 'qasubmission';
                $m->userfrom = $student;
                $m->userto = $to;
                $m->subject = $subject;
                $m->fullmessage = $body;
                $m->fullmessageformat = FORMAT_PLAIN;
                $m->fullmessagehtml = '<p>' . s($body) . '</p>';
                $m->smallmessage = $subject;
                $m->notification = 1;
                $m->contexturl = $url->out(false);
                $m->contexturlname = get_string('qanotifylink', 'block_nvq_matrix');
                message_send($m);
            }
        } catch (\Throwable $e) {
            // A notification problem must never undo or block an upload.
            debugging('block_nvq_matrix QA notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Tells the course's IQA users that the Assessor has signed a document
     * and the IQA signature is now needed.
     *
     * @param \stdClass $doc
     * @param int       $courseid
     * @param int       $studentid
     */
    protected static function notify_iqa(\stdClass $doc, int $courseid, int $studentid): void {
        global $DB, $USER;
        try {
            $ctx = \context_course::instance($courseid, IGNORE_MISSING);
            $student = $DB->get_record('user', ['id' => $studentid], '*', IGNORE_MISSING);
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
            if (!$ctx || !$student || !$course) {
                return;
            }
            $a = (object) [
                'student'  => fullname($student),
                'course'   => format_string($course->fullname, true, ['context' => $ctx, 'escape' => false]),
                'document' => format_string($doc->title, true, ['context' => $ctx, 'escape' => false]),
            ];
            $subject = get_string('qanotifyiqasubject', 'block_nvq_matrix', $a);
            $body = get_string('qanotifyiqabody', 'block_nvq_matrix', $a);
            $url = new \moodle_url('/blocks/nvq_matrix/qa.php', ['courseid' => $courseid, 'studentid' => $studentid]);

            $recipients = get_users_by_capability($ctx, 'block/nvq_matrix:qaiqasign', 'u.id');
            foreach ($recipients as $r) {
                if ((int) $r->id === $studentid || (int) $r->id === (int) $USER->id) {
                    continue;
                }
                // Group isolation (v27.1.0, IOMAD fork): only an IQA allowed
                // to access this learner is told about them - same rule and
                // reasoning as matrix_data::get_assessor_recipient_ids().
                if (!matrix_data::viewer_can_access_student($courseid, $studentid, (int) $r->id)) {
                    continue;
                }
                $to = $DB->get_record('user', ['id' => $r->id, 'deleted' => 0], '*', IGNORE_MISSING);
                if (!$to) {
                    continue;
                }
                $m = new \core\message\message();
                $m->component = 'block_nvq_matrix';
                $m->name = 'qasubmission';
                $m->userfrom = $USER;
                $m->userto = $to;
                $m->subject = $subject;
                $m->fullmessage = $body;
                $m->fullmessageformat = FORMAT_PLAIN;
                $m->fullmessagehtml = '<p>' . s($body) . '</p>';
                $m->smallmessage = $subject;
                $m->notification = 1;
                $m->contexturl = $url->out(false);
                $m->contexturlname = get_string('qanotifylink', 'block_nvq_matrix');
                message_send($m);
            }
        } catch (\Throwable $e) {
            debugging('block_nvq_matrix QA IQA notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Tells the learner their document was accepted or returned.
     *
     * @param \stdClass $doc
     * @param \stdClass $sub
     * @param bool      $accepted
     * @param string    $reason
     */
    protected static function notify_learner(\stdClass $doc, \stdClass $sub, bool $accepted, string $reason): void {
        global $DB, $USER;
        try {
            $ctx = \context_course::instance((int) $sub->courseid, IGNORE_MISSING);
            $learner = $DB->get_record('user', ['id' => $sub->userid, 'deleted' => 0], '*', IGNORE_MISSING);
            $course = $DB->get_record('course', ['id' => $sub->courseid], 'id, fullname', IGNORE_MISSING);
            if (!$ctx || !$learner || !$course) {
                return;
            }
            $a = (object) [
                'course'   => format_string($course->fullname, true, ['context' => $ctx, 'escape' => false]),
                'document' => format_string($doc->title, true, ['context' => $ctx, 'escape' => false]),
                'reason'   => $reason,
            ];
            $subject = get_string($accepted ? 'qanotifyacceptedsubject' : 'qanotifyreturnedsubject', 'block_nvq_matrix', $a);
            $body = get_string($accepted ? 'qanotifyacceptedbody' : 'qanotifyreturnedbody', 'block_nvq_matrix', $a);
            $url = new \moodle_url('/blocks/nvq_matrix/qa.php', ['courseid' => (int) $sub->courseid]);

            $m = new \core\message\message();
            $m->component = 'block_nvq_matrix';
            $m->name = 'qareview';
            $m->userfrom = $USER;
            $m->userto = $learner;
            $m->subject = $subject;
            $m->fullmessage = $body;
            $m->fullmessageformat = FORMAT_PLAIN;
            $m->fullmessagehtml = '<p>' . nl2br(s($body)) . '</p>';
            $m->smallmessage = $subject;
            $m->notification = 1;
            $m->contexturl = $url->out(false);
            $m->contexturlname = get_string('qanotifylinklearner', 'block_nvq_matrix');
            message_send($m);
        } catch (\Throwable $e) {
            debugging('block_nvq_matrix QA notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
