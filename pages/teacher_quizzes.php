<?php
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
    false,   // hide category — teacher already knows they're in one course
    false    // hide course column — already scoped to one course
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
$from   = "{local_quizmeta_quiz_score} qs
           JOIN {quiz}              q  ON q.id  = qs.quizid
           JOIN {course}            c  ON c.id  = qs.courseid
           JOIN {course_categories} cc ON cc.id = qs.categoryid";

$table->set_sql($fields, $from, 'qs.courseid = :courseid', ['courseid' => $courseid]);
$table->out(50, true);

echo $OUTPUT->footer();