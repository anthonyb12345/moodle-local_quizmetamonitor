<?php
namespace local_quizmetamonitor\task;

defined('MOODLE_INTERNAL') || die();

use local_quizmetamonitor\notification_manager;

/**
 * Runs every 30 minutes.
 * Emails Exam Monitor(s) for any quiz that:
 *   - starts within the next 24 hours
 *   - is currently below its applicable threshold
 *   - has not yet had an alert sent in this run window
 */
class check_upcoming_quiz_threshold extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_check_threshold', 'local_quizmetamonitor');
    }

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
                  FROM {local_quizmeta_quiz_score} qs
                  JOIN {quiz} q ON q.id = qs.quizid
                 WHERE qs.is_below_threshold = 1
                   AND q.timeopen > :now
                   AND q.timeopen <= :in24h";

        $records = $DB->get_recordset_sql($sql, ['now' => $now, 'in24h' => $in24h]);

        foreach ($records as $rec) {
            $quiz = $DB->get_record('quiz',             ['id' => $rec->quizid],    '*', IGNORE_MISSING);
            $course = $DB->get_record('course',         ['id' => $rec->courseid],  '*', IGNORE_MISSING);
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
        }

        $records->close();
    }
}