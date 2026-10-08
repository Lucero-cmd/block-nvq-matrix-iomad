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
 * Small presentation helpers shared by the QA documents pages (v26.7.1):
 * inline SVG icons and the status pill. Kept in one place so the admin
 * page, the checklist and the tracker look identical.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_nvq_matrix;

defined('MOODLE_INTERNAL') || die();

/**
 * Presentation helpers for the QA documents pages.
 */
class qa_ui {

    /**
     * An inline SVG icon (decorative, so hidden from screen readers).
     *
     * @param string $name
     * @return string HTML
     */
    public static function icon(string $name): string {
        $paths = [
            'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
            'download' => '<path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/>',
            'upload'   => '<path d="M12 16V5"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/>',
            'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'return'   => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
            'dash'     => '<path d="M6 12h12"/>',
            'pen'      => '<path d="m4 20 4-1 11-11-3-3L5 16z"/><path d="m14 6 3 3"/>',
            'up'       => '<path d="m6 15 6-6 6 6"/>',
            'down'     => '<path d="m6 9 6 6 6-6"/>',
            'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
            'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
            'trash'    => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
            'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
            'back'     => '<path d="M15 6l-6 6 6 6"/>',
            'shield'   => '<path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z"/><path d="m9 12 2.5 2.5L15.5 10"/>',
            'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6.1"/><path d="M18 14.3c1.8.7 3 2.5 3 5.7"/>',
            'hide'     => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4"/><path d="M6.2 6.7C3.7 8.4 2 12 2 12s3.5 7 10 7c1.6 0 3-.4 4.2-1"/>',
        ];
        $p = $paths[$name] ?? $paths['file'];
        return '<svg class="nvq-qa-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"'
            . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $p . '</svg>';
    }

    /**
     * Status pill for a submission status.
     *
     * @param int $status qa::ST_*
     * @return string HTML
     */
    public static function status_pill(int $status): string {
        $map = [
            qa::ST_NONE     => ['none', 'dash', 'qastatusnone'],
            qa::ST_AWAITING => ['awaiting', 'clock', 'qastatusawaiting'],
            qa::ST_ACCEPTED => ['accepted', 'check', 'qastatusaccepted'],
            qa::ST_RETURNED => ['returned', 'return', 'qastatusreturned'],
            qa::ST_AWAITING_IQA => ['awaitingiqa', 'clock', 'qastatusawaitingiqa'],
        ];
        [$class, $icon, $string] = $map[$status] ?? $map[qa::ST_NONE];
        return '<span class="nvq-qa-pill nvq-qa-pill--' . $class . '">' . self::icon($icon)
            . '<span>' . get_string($string, 'block_nvq_matrix') . '</span></span>';
    }
}
