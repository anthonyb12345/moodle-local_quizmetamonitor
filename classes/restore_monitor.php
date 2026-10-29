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
 * Scores quizzes that arrived through a restore and tells the restoring teacher.
 *
 * Restores never fire course_module_created, so this is called from:
 *  - observer::course_restored()  for course backups, Course > Import and course copy;
 *  - restore_local_quizmetamonitor_plugin::after_restore_module()  for single-quiz and section
 *    restores, e.g. a quiz backup brought over from another Moodle server.
 * Either way the restoring teacher gets one email per restored quiz.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_monitor {
    /**
     * Score every quiz in $quizids that has no score yet, then email $userid once per quiz
     * (only if $userid is a teacher in the course).
     *
     * @param int[] $quizids
     * @param int $courseid Course ID.
     * @param int $userid User ID.
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
