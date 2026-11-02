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

namespace local_quizmetamonitor\event;

use local_quizmetamonitor\scoring_engine;
use local_quizmetamonitor\notification_manager;

/**
 * Event observers: score quizzes when they are created, updated, restored or deleted,
 * and assign Exam Monitors to new courses.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Fired when ANY course module is created.
     * We check if it is a quiz before doing anything.
     *
     * @param \core\event\course_module_created $event The event.
     */
    public static function quiz_created(\core\event\course_module_created $event): void {
        global $DB;

        // Only care about quiz modules.
        if ($event->other['modulename'] !== 'quiz') {
            return;
        }

        // Get the quiz instance id reliably from the event.
        $quizid = self::get_quizid_from_event($event);
        if (!$quizid) {
            debugging('local_quizmetamonitor: could not resolve quizid from event', DEBUG_DEVELOPER);
            return;
        }

        try {
            $result = scoring_engine::compute_score($quizid);
            scoring_engine::save_score($result, (int) $event->userid);

            $quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $result['courseid']], '*', MUST_EXIST);

            notification_manager::notify_teachers_created($result, $quiz, $course, (int) $event->userid);
        } catch (\Throwable $e) {
            debugging(
                'local_quizmetamonitor quiz_created error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Fired when ANY course module is updated.
     *
     * @param \core\event\course_module_updated $event The event.
     */
    public static function quiz_updated(\core\event\course_module_updated $event): void {
        global $DB;

        if ($event->other['modulename'] !== 'quiz') {
            return;
        }

        $quizid = self::get_quizid_from_event($event);
        if (!$quizid) {
            debugging('local_quizmetamonitor: could not resolve quizid from event', DEBUG_DEVELOPER);
            return;
        }

        try {
            $result = scoring_engine::compute_score($quizid);
            [$changed, $previous, $previousbreakdown] = scoring_engine::save_score($result, (int) $event->userid);

            if ($changed) {
                $quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
                $course = $DB->get_record('course', ['id' => $result['courseid']], '*', MUST_EXIST);

                notification_manager::notify_teachers_score_changed(
                    $result,
                    $quiz,
                    $course,
                    $previous,
                    (int) $event->userid,
                    $previousbreakdown
                );
            }
        } catch (\Throwable $e) {
            debugging(
                'local_quizmetamonitor quiz_updated error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Fired after a backup is restored, imported or a course is copied.
     *
     * Restores do not fire course_module_created, so restored quizzes would otherwise stay
     * unscored (invisible to reports and to the Exam Monitor alert). Score every quiz in the
     * course that has no score yet and email the restoring teacher once per quiz.
     *
     * @param \core\event\course_restored $event The event.
     */
    public static function course_restored(\core\event\course_restored $event): void {
        global $DB;

        $courseid = (int) $event->objectid;

        $sql = "SELECT q.id
                  FROM {quiz} q
             LEFT JOIN {local_quizmetamonitor_quiz_score} qs ON qs.quizid = q.id
                 WHERE q.course = :courseid
                   AND qs.id IS NULL
              ORDER BY q.id";
        $quizids = $DB->get_fieldset_sql($sql, ['courseid' => $courseid]);

        \local_quizmetamonitor\restore_monitor::process($quizids, $courseid, (int) $event->userid);
    }

    /**
     * Fired when ANY course module is deleted.
     *
     * @param \core\event\course_module_deleted $event The event.
     */
    public static function module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        if (($event->other['modulename'] ?? '') !== 'quiz') {
            return;
        }

        $quizid = self::get_quizid_from_event($event);
        if (!$quizid) {
            return;
        }

        $DB->delete_records('local_quizmetamonitor_quiz_score', ['quizid' => $quizid]);
        $DB->delete_records('local_quizmetamonitor_score_history', ['quizid' => $quizid]);
        $DB->delete_records('local_quizmetamonitor_notif_log', ['quizid' => $quizid]);
    }

    // Private helpers.

    /**
     * Reliably extract the quiz instance id from a course_module event.
     *
     * Moodle puts the cm instanceid in $event->other['instanceid'].
     * As a fallback we query the course_modules table using the objectid
     * which is always the cmid.
     *
     * @param \core\event\base $event The event.
     * @return int
     */
    private static function get_quizid_from_event(\core\event\base $event): int {
        global $DB;

        // Primary: instanceid is set directly on the event other array.
        if (!empty($event->other['instanceid'])) {
            return (int) $event->other['instanceid'];
        }

        // Fallback: objectid on course_module events is the cmid.
        // Use it to look up the instance (quiz) id from course_modules.
        if (!empty($event->objectid)) {
            $cmid = (int) $event->objectid;
            $instance = $DB->get_field('course_modules', 'instance', ['id' => $cmid]);
            if ($instance) {
                return (int) $instance;
            }
        }

        return 0;
    }
    /**
     * When a new course is created, assign the exam monitor role
     * to any monitors who are assigned to that course's category.
     *
     * @param \core\event\course_created $event The event.
     */
    public static function course_created(\core\event\course_created $event): void {
        global $DB;

        $courseid   = (int) $event->objectid;
        $course     = $DB->get_record('course', ['id' => $courseid]);

        if (!$course) {
            return;
        }

        $categoryid = (int) $course->category;

        // Find all monitors assigned to this category.
        $monitorids = \local_quizmetamonitor\category_util::get_monitors_for_category($categoryid);

        if (empty($monitorids)) {
            return;
        }

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'quizmeta_exammonitor']);
        if (!$roleid) {
            return;
        }

        $coursecontext = \context_course::instance($courseid);

        foreach ($monitorids as $userid) {
            role_assign($roleid, $userid, $coursecontext->id);
        }
    }
}
