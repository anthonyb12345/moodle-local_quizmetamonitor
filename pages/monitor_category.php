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
 * Exam Monitor page: quiz security scores for one course category.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

global $DB, $USER;

$categoryid = required_param('categoryid', PARAM_INT);
$catcontext  = context_coursecat::instance($categoryid);

require_capability('local/quizmetamonitor:viewcategory', $catcontext);

// Extra: verify they are actually assigned to this category unless site admin.
$issiteadmin = has_capability('local/quizmetamonitor:viewall', context_system::instance());
if (!$issiteadmin) {
    if (!\local_quizmetamonitor\category_util::is_monitor_for_category($USER->id, $categoryid)) {
        throw new moodle_exception('nopermissions', 'error', '', 'view this category');
    }
}

$PAGE->set_url(
    '/local/quizmetamonitor/pages/monitor_category.php',
    ['categoryid' => $categoryid]
);
$PAGE->set_context($catcontext);

$categoryname = $DB->get_field('course_categories', 'name', ['id' => $categoryid], MUST_EXIST);
$PAGE->set_title(format_string($categoryname) . ' — ' . get_string('monitorcategory', 'local_quizmetamonitor'));
$PAGE->set_heading(format_string($categoryname));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('monitorcategory', 'local_quizmetamonitor') . ': ' . format_string($categoryname));

// Course filter.
$courseids  = \local_quizmetamonitor\category_util::get_course_ids_in_category($categoryid);
$courseopts = [0 => get_string('allcourses', 'local_quizmetamonitor')];
if (!empty($courseids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
    $courses = $DB->get_records_select(
        'course',
        "id $insql AND id <> :siteid",
        $inparams + ['siteid' => SITEID],
        'fullname'
    );
    foreach ($courses as $c) {
        $courseopts[$c->id] = $c->fullname;
    }
}

$coursefilter = optional_param('courseid', 0, PARAM_INT);

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'categoryid', 'value' => $categoryid]);
echo html_writer::select(
    $courseopts,
    'courseid',
    $coursefilter,
    false,
    ['onchange' => 'this.form.submit()', 'class' => 'custom-select', 'style' => 'width:auto']
);
echo html_writer::end_tag('form');

// Table.
$table = new \local_quizmetamonitor\output\quiz_table(
    'quizmeta_monitor_' . $categoryid,
    false, // Hide category column (we already know it).
    true     // Show course column.
);
$table->define_baseurl(new moodle_url(
    '/local/quizmetamonitor/pages/monitor_category.php',
    ['categoryid' => $categoryid]
));

$where  = 'qs.categoryid = :catid';
$params = ['catid' => $categoryid];

if ($coursefilter > 0) {
    $where  .= ' AND qs.courseid = :courseid';
    $params['courseid'] = $coursefilter;
}

$fields = "qs.quizid, qs.courseid, qs.categoryid,
           q.name AS quizname, q.timeopen, q.timeclose,
           c.fullname AS coursename,
           cc.name AS categoryname,
           qs.score_percent, qs.threshold_applied, qs.is_below_threshold";
$from   = "{local_quizmetamonitor_quiz_score} qs
           JOIN {quiz}              q  ON q.id  = qs.quizid
           JOIN {course}            c  ON c.id  = qs.courseid
           JOIN {course_categories} cc ON cc.id = qs.categoryid";

$table->set_sql($fields, $from, $where, $params);
$table->out(50, true);

echo $OUTPUT->footer();
