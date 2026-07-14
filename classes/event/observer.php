<?php
namespace local_quizmetamonitor\event;

defined('MOODLE_INTERNAL') || die();

use local_quizmetamonitor\scoring_engine;
use local_quizmetamonitor\notification_manager;

class observer {

    /**
     * Fired when ANY course module is created.
     * We check if it is a quiz before doing anything.
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

            $quiz   = $DB->get_record('quiz',  ['id' => $quizid],             '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $result['courseid']], '*', MUST_EXIST);

            notification_manager::notify_teachers_created($result, $quiz, $course);

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
            [$changed, $previous] = scoring_engine::save_score($result, (int) $event->userid);

            if ($changed) {
                $quiz   = $DB->get_record('quiz',  ['id' => $quizid],             '*', MUST_EXIST);
                $course = $DB->get_record('course', ['id' => $result['courseid']], '*', MUST_EXIST);

                notification_manager::notify_teachers_score_changed(
                    $result, $quiz, $course, $previous
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
     * Fired when ANY course module is deleted.
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

        $DB->delete_records('local_quizmeta_quiz_score',    ['quizid' => $quizid]);
        $DB->delete_records('local_quizmeta_score_history', ['quizid' => $quizid]);
        $DB->delete_records('local_quizmeta_notif_log',     ['quizid' => $quizid]);
    }

    // ---------------------------------------------------------------- //
    // Private helpers                                                   //
    // ---------------------------------------------------------------- //

    /**
     * Reliably extract the quiz instance id from a course_module event.
     *
     * Moodle puts the cm instanceid in $event->other['instanceid'].
     * As a fallback we query the course_modules table using the objectid
     * which is always the cmid.
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