<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extend the flat navigation for Admins (site-level node).
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