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
 * Library functions (navigation callbacks) for local_quizmetamonitor.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extend the flat navigation for Admins (site-level node).
 *
 * @param global_navigation $nav The navigation tree.
 */
function local_quizmetamonitor_extend_navigation(global_navigation $nav): void {
    if (has_capability('local/quizmetamonitor:viewall', context_system::instance())) {
        $node = $nav->add(
            get_string('pluginname', 'local_quizmetamonitor'),
            new moodle_url('/local/quizmetamonitor/pages/admin_overview.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'quizmetamonitor_admin'
        );
        $node->showinflatnavigation = true;
    }
}

/**
 * Extend course-level navigation for Teachers.
 *
 * @param navigation_node $nav The navigation tree.
 * @param stdClass $course Course record.
 * @param context $context The context.
 */
function local_quizmetamonitor_extend_navigation_course(
    navigation_node $nav,
    stdClass $course,
    context $context
): void {
    if (has_capability('local/quizmetamonitor:viewown', $context)) {
        $nav->add(
            get_string('myquizzes', 'local_quizmetamonitor'),
            new moodle_url(
                '/local/quizmetamonitor/pages/teacher_quizzes.php',
                ['courseid' => $course->id]
            ),
            navigation_node::TYPE_CUSTOM,
            null,
            'quizmetamonitor_teacher'
        );
    }
}

/**
 * Extend category settings navigation for Exam Monitors.
 *
 * @param navigation_node $navigation The category settings navigation node.
 * @param context $context The context.
 */
function local_quizmetamonitor_extend_navigation_category_settings(
    navigation_node $navigation,
    context $context
): void {
    if (has_capability('local/quizmetamonitor:viewcategory', $context)) {
        $navigation->add(
            get_string('monitorcategory', 'local_quizmetamonitor'),
            new moodle_url(
                '/local/quizmetamonitor/pages/monitor_category.php',
                ['categoryid' => $context->instanceid]
            ),
            navigation_node::TYPE_CUSTOM,
            null,
            'quizmetamonitor_monitor'
        );
    }
}
