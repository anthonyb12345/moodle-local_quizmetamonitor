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

namespace local_quizmetamonitor;

/**
 * Helpers to look up course categories, Exam Monitors and teachers.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_util {
    /**
     * Return the course-category id for a given course.
     *
     * @param int $courseid Course ID.
     * @return int
     */
    public static function get_categoryid_for_course(int $courseid): int {
        global $DB;
        return (int) $DB->get_field('course', 'category', ['id' => $courseid], MUST_EXIST);
    }

    /**
     * Return every category id that $userid is assigned to as Exam Monitor.
     *
     * @param int $userid User ID.
     * @return int[]
     */
    public static function get_categories_for_monitor(int $userid): array {
        global $DB;
        $rows = $DB->get_fieldset_select(
            'local_quizmeta_exammonitor',
            'categoryid',
            'userid = :userid',
            ['userid' => $userid]
        );
        return array_map('intval', $rows);
    }

    /**
     * True if $userid is an Exam Monitor for $categoryid.
     *
     * @param int $userid User ID.
     * @param int $categoryid Course category ID.
     * @return bool
     */
    public static function is_monitor_for_category(int $userid, int $categoryid): bool {
        global $DB;
        return $DB->record_exists(
            'local_quizmeta_exammonitor',
            ['userid' => $userid, 'categoryid' => $categoryid]
        );
    }

    /**
     * Return all user ids who are Exam Monitors for $categoryid.
     *
     * @param int $categoryid Course category ID.
     * @return int[]
     */
    public static function get_monitors_for_category(int $categoryid): array {
        global $DB;
        $rows = $DB->get_fieldset_select(
            'local_quizmeta_exammonitor',
            'userid',
            'categoryid = :categoryid',
            ['categoryid' => $categoryid]
        );
        return array_map('intval', $rows);
    }

    /**
     * Return all user ids of teachers and editing teachers enrolled in a course.
     *
     * @param int $courseid Course ID.
     * @return int[]
     */
    public static function get_teachers_for_course(int $courseid): array {
        global $DB;

        $context = \context_course::instance($courseid);

        // Get role ids by archetype so we don't hardcode role ids.
        $teacheroleids = $DB->get_fieldset_select(
            'role',
            'id',
            "archetype IN ('teacher', 'editingteacher')"
        );

        if (empty($teacheroleids)) {
            return [];
        }

        [$rolesql, $roleparams] = $DB->get_in_or_equal($teacheroleids, SQL_PARAMS_NAMED, 'role');

        // FIX: get_parent_context_ids(true) already returns context ids as VALUES.
        // Do NOT call array_keys() on it.
        $contextids = $context->get_parent_context_ids(true); // Includes the course context itself.

        [$ctxsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        $sql = "SELECT DISTINCT ra.userid
                FROM {role_assignments} ra
                JOIN {user} u ON u.id = ra.userid
                WHERE ra.roleid $rolesql
                AND ra.contextid $ctxsql
                AND u.deleted = 0
                AND u.suspended = 0";

        $params = array_merge($roleparams, $ctxparams);
        $rows   = $DB->get_fieldset_sql($sql, $params);

        return array_map('intval', $rows);
    }
    /**
     * Return all course ids that are direct children of a category.
     *
     * @param int $categoryid Course category ID.
     * @return int[]
     */
    public static function get_course_ids_in_category(int $categoryid): array {
        global $DB;
        $rows = $DB->get_fieldset_select(
            'course',
            'id',
            'category = :cat AND id <> :siteid',
            ['cat' => $categoryid, 'siteid' => SITEID]
        );
        return array_map('intval', $rows);
    }
}
