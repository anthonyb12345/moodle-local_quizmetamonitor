<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

/**
 * Scores quizzes that arrived through a restore and tells the restoring teacher.
 *
 * Restores never fire course_module_created, so this is called from:
 *  - observer::course_restored()  for course backups, Course > Import and course copy;
 *  - restore_local_quizmetamonitor_plugin::after_restore_module()  for single-quiz and section
 *    restores, e.g. a quiz backup brought over from another Moodle server.
 * Either way the restoring teacher gets one email per restored quiz.
 */
class restore_monitor {

    /**
     * Score every quiz in $quizids that has no score yet, then email $userid once per quiz
     * (only if $userid is a teacher in the course).
     *
     * @param int[] $quizids
     * @return int number of quizzes scored
     */
    public static function process(array $quizids, int $courseid, int $userid): int {
        global $DB;

        $results = [];
        foreach ($quizids as $quizid) {
            if ($DB->record_exists('local_quizmeta_quiz_score', ['quizid' => $quizid])) {
                continue;
            }
            try {
                $result = scoring_engine::compute_score((int) $quizid);
                scoring_engine::save_score($result, $userid);
                $results[] = $result;
            } catch (\Throwable $e) {
                debugging(
                    'local_quizmetamonitor restore: scoring failed for quizid=' . $quizid . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        if (empty($results)) {
            return 0;
        }

        // One email per restored quiz, with the same criteria table as a newly created quiz.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        foreach ($results as $result) {
            try {
                $quiz = $DB->get_record('quiz', ['id' => $result['quizid']], '*', MUST_EXIST);
                notification_manager::notify_teacher_quiz_restored($result, $quiz, $course, $userid);
            } catch (\Throwable $e) {
                debugging('local_quizmetamonitor restore: email failed for quizid=' . $result['quizid'] . ': '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return count($results);
    }
}
