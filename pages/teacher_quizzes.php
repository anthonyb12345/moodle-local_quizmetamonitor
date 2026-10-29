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
 * Teacher page: security scores of the quizzes in a course.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$courseid  = required_param('courseid', PARAM_INT);
$course    = get_course($courseid);
$coursectx = context_course::instance($courseid);

require_capability('local/quizmetamonitor:viewown', $coursectx);

$PAGE->set_url(
    '/local/quizmetamonitor/pages/teacher_quizzes.php',
    ['courseid' => $courseid]
);
$PAGE->set_context($coursectx);
$PAGE->set_title(get_string('myquizzes', 'local_quizmetamonitor'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('myquizzes', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('myquizzes', 'local_quizmetamonitor'));

$table = new \local_quizmetamonitor\output\quiz_table(
    'quizmeta_teacher_' . $courseid,
    false, // Hide category — teacher already knows they're in one course.
    false    // Hide course column — already scoped to one course.
);

$table->define_baseurl(new moodle_url(
    '/local/quizmetamonitor/pages/teacher_quizzes.php',
    ['courseid' => $courseid]
));

$fields = "qs.quizid, qs.courseid, qs.categoryid,
           q.name AS quizname, q.timeopen, q.timeclose,
           c.fullname AS coursename,
           cc.name AS categoryname,
           qs.score_percent, qs.threshold_applied, qs.is_below_threshold";
$from   = "{local_quizmetamonitor_quiz_score} qs
           JOIN {quiz}              q  ON q.id  = qs.quizid
           JOIN {course}            c  ON c.id  = qs.courseid
           JOIN {course_categories} cc ON cc.id = qs.categoryid";

$table->set_sql($fields, $from, 'qs.courseid = :courseid', ['courseid' => $courseid]);
$table->out(50, true);

echo $OUTPUT->footer();
