<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Admin settings tree entries for local_quizmetamonitor.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'localplugins',
        new admin_category(
            'local_quizmetamonitor_cat',
            get_string('pluginname', 'local_quizmetamonitor')
        )
    );

    $pages = [
        ['admin_overview', 'adminoverview', 'admin_overview.php'],
        ['admin_criteria', 'configurecriteria', 'admin_criteria.php'],
        ['admin_threshold', 'configurethreshold', 'admin_threshold.php'],
        ['admin_monitors', 'assignmonitors', 'admin_monitors.php'],
        ['admin_templates', 'emailtemplates', 'admin_templates.php'],
        ['admin_export', 'exportreport', 'admin_export.php'],
        ['admin_backfill', 'backfill', 'admin_backfill.php'],
    ];

    foreach ($pages as [$name, $strkey, $file]) {
        $ADMIN->add(
            'local_quizmetamonitor_cat',
            new admin_externalpage(
                'local_quizmetamonitor_' . $name,
                get_string($strkey, 'local_quizmetamonitor'),
                new moodle_url('/local/quizmetamonitor/pages/' . $file)
            )
        );
    }
}

$settings = null; // We manage all pages above; suppress default settings page.
