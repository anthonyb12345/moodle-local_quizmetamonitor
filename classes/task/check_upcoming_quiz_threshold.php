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

namespace local_quizmetamonitor\task;

use local_quizmetamonitor\notification_manager;

/**
 * Runs every 30 minutes.
 * Emails Exam Monitor(s), and the users who created/edited the quiz, for any quiz that:
 *   - starts within the next 24 hours
 *   - is currently below its applicable threshold
 *   - has not yet had an alert sent in this run window
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_upcoming_quiz_threshold extends \core\task\scheduled_task {
    /**
     * Name of the task shown in the admin interface.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_check_threshold', 'local_quizmetamonitor');
    }

    /**
     * Send the below-threshold alerts for quizzes that open within the next 24 hours.
     */
    public function execute(): void {
        global $DB;

        $now   = time();
        $in24h = $now + (24 * 3600);

        $sql = "SELECT qs.quizid,
                       qs.categoryid,
                       qs.courseid,
                       qs.score_percent,
                       qs.threshold_applied,
                       qs.criteria_snapshot
                  FROM {local_quizmetamonitor_quiz_score} qs
                  JOIN {quiz} q ON q.id = qs.quizid
                 WHERE qs.is_below_threshold = 1
                   AND q.timeopen > :now
                   AND q.timeopen <= :in24h";

        $records = $DB->get_recordset_sql($sql, ['now' => $now, 'in24h' => $in24h]);

        foreach ($records as $rec) {
            $quiz = $DB->get_record('quiz', ['id' => $rec->quizid], '*', IGNORE_MISSING);
            $course = $DB->get_record('course', ['id' => $rec->courseid], '*', IGNORE_MISSING);
            $category = $DB->get_record('course_categories', ['id' => $rec->categoryid], '*', IGNORE_MISSING);

            if (!$quiz || !$course) {
                continue;
            }

            $scoreresult = [
                'quizid'            => (int) $rec->quizid,
                'categoryid'        => (int) $rec->categoryid,
                'score_percent'     => (float) $rec->score_percent,
                'threshold_applied' => (float) $rec->threshold_applied,
                'breakdown'         => json_decode($rec->criteria_snapshot, true) ?? [],
            ];

            notification_manager::notify_exam_monitors_alert(
                $scoreresult,
                $quiz,
                $course,
                $category ? $category->name : ''
            );

            // Also alert whoever created/edited the quiz, so they can fix it before it opens.
            notification_manager::notify_teachers_below_threshold($scoreresult, $quiz, $course);
        }

        $records->close();
    }
}
