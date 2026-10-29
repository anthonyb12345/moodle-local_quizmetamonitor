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
 * Admin page: overview of quiz security scores across the site.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/tablelib.php');

require_login();
$systemcontext = context_system::instance();
require_capability('local/quizmetamonitor:viewall', $systemcontext);

global $DB;

$PAGE->set_url('/local/quizmetamonitor/pages/admin_overview.php');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('adminoverview', 'local_quizmetamonitor'));
$PAGE->set_heading(get_string('adminoverview', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('admin');

// Read filter parameters.
$filtercategory = optional_param('categoryid', 0, PARAM_INT);
$filtercourse   = optional_param('courseid', 0, PARAM_INT);
$filterstatus   = optional_param('status', '', PARAM_ALPHA); // One of 'below', 'ok' or empty.

// Build category options (all categories).
$categoryoptions = [0 => get_string('allcategories', 'local_quizmetamonitor')]
    + core_course_category::make_categories_list();

// Build course options depending on selected category.
$courseoptions = [0 => get_string('allcourses', 'local_quizmetamonitor')];

if ($filtercategory > 0) {
    // Only courses inside selected category.
    $courses = $DB->get_records_select(
        'course',
        'category = :cat AND id <> :siteid',
        ['cat' => $filtercategory, 'siteid' => SITEID],
        'fullname ASC',
        'id, fullname'
    );
} else {
    // All courses across all categories.
    $courses = $DB->get_records_select(
        'course',
        'id <> :siteid',
        ['siteid' => SITEID],
        'fullname ASC',
        'id, fullname'
    );
}

foreach ($courses as $c) {
    $courseoptions[$c->id] = $c->fullname;
}

// If the previously selected course no longer belongs to the newly
// selected category, reset the course filter.
if ($filtercourse > 0 && !isset($courseoptions[$filtercourse])) {
    $filtercourse = 0;
}

// Render page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('adminoverview', 'local_quizmetamonitor'));

// Sub-page navigation.
$navlinks = [
    ['admin_criteria.php', get_string('configurecriteria', 'local_quizmetamonitor')],
    ['admin_threshold.php', get_string('configurethreshold', 'local_quizmetamonitor')],
    ['admin_monitors.php', get_string('assignmonitors', 'local_quizmetamonitor')],
    ['admin_templates.php', get_string('emailtemplates', 'local_quizmetamonitor')],
    ['admin_export.php', get_string('exportreport', 'local_quizmetamonitor')],
    ['admin_backfill.php', get_string('backfill', 'local_quizmetamonitor')],
];

$navhtml = '';
foreach ($navlinks as [$file, $label]) {
    $url      = new moodle_url('/local/quizmetamonitor/pages/' . $file);
    $navhtml .= html_writer::link(
        $url,
        $label,
        ['class' => 'btn btn-outline-primary mr-2 mb-2']
    );
}
echo html_writer::div($navhtml, 'mb-4');

// Summary counters.
$totaltracked = $DB->count_records('local_quizmeta_quiz_score');
$totalbelow   = $DB->count_records('local_quizmeta_quiz_score', ['is_below_threshold' => 1]);
$totalok      = $totaltracked - $totalbelow;

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::start_div('row text-center');

// Total.
echo html_writer::start_div('col-md-4');
echo html_writer::tag('h2', $totaltracked, ['class' => 'mb-0']);
echo html_writer::tag(
    'p',
    get_string('totalquizzes', 'local_quizmetamonitor'),
    ['class' => 'text-muted']
);
echo html_writer::end_div();

// OK.
echo html_writer::start_div('col-md-4');
echo html_writer::tag('h2', $totalok, ['class' => 'mb-0 text-success']);
echo html_writer::tag(
    'p',
    get_string('quizzesok', 'local_quizmetamonitor'),
    ['class' => 'text-muted']
);
echo html_writer::end_div();

// Below threshold.
echo html_writer::start_div('col-md-4');
echo html_writer::tag('h2', $totalbelow, ['class' => 'mb-0 text-danger']);
echo html_writer::tag(
    'p',
    get_string('belowthresholdcount', 'local_quizmetamonitor'),
    ['class' => 'text-muted']
);
echo html_writer::end_div();

echo html_writer::end_div(); // Row.
echo html_writer::end_div(); // Card-body.
echo html_writer::end_div(); // Card.

// Filter form.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag(
    'h5',
    get_string('filterquizzes', 'local_quizmetamonitor'),
    ['class' => 'card-title']
);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url->out_omit_querystring(),
    'id'     => 'qmm-filter-form',
]);

echo html_writer::start_div('form-row align-items-end');

// Category filter.
echo html_writer::start_div('col-md-4 form-group mb-2');
echo html_writer::tag(
    'label',
    get_string('filterbycategory', 'local_quizmetamonitor'),
    ['for' => 'filter_cat', 'class' => 'font-weight-bold']
);
echo html_writer::select(
    $categoryoptions,
    'categoryid',
    $filtercategory,
    false,
    [
        'id'       => 'filter_cat',
        'class'    => 'custom-select',
        // When category changes, auto-submit so course list updates.
        'onchange' => 'document.getElementById(\'filter_course\').value = 0;'
                    . 'document.getElementById(\'qmm-filter-form\').submit();',
    ]
);
echo html_writer::end_div();

// Course filter.
echo html_writer::start_div('col-md-4 form-group mb-2');
echo html_writer::tag(
    'label',
    get_string('filterbycourse', 'local_quizmetamonitor'),
    ['for' => 'filter_course', 'class' => 'font-weight-bold']
);
echo html_writer::select(
    $courseoptions,
    'courseid',
    $filtercourse,
    false,
    [
        'id'    => 'filter_course',
        'class' => 'custom-select',
    ]
);
echo html_writer::end_div();

// Status filter.
$statusoptions = [
    ''      => get_string('allstatuses', 'local_quizmetamonitor'),
    'below' => get_string('belowthreshold', 'local_quizmetamonitor'),
    'ok'    => get_string('ok', 'local_quizmetamonitor'),
];

echo html_writer::start_div('col-md-2 form-group mb-2');
echo html_writer::tag(
    'label',
    get_string('filterbystatus', 'local_quizmetamonitor'),
    ['for' => 'filter_status', 'class' => 'font-weight-bold']
);
echo html_writer::select(
    $statusoptions,
    'status',
    $filterstatus,
    false,
    [
        'id'    => 'filter_status',
        'class' => 'custom-select',
    ]
);
echo html_writer::end_div();

// Buttons.
echo html_writer::start_div('col-md-2 form-group mb-2 d-flex align-items-end');

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('applyfilters', 'local_quizmetamonitor'),
    'class' => 'btn btn-primary mr-2',
]);

// Reset link clears all filters.
$reseturl = new moodle_url('/local/quizmetamonitor/pages/admin_overview.php');
echo html_writer::link(
    $reseturl,
    get_string('resetfilters', 'local_quizmetamonitor'),
    ['class' => 'btn btn-outline-secondary']
);

echo html_writer::end_div();
echo html_writer::end_div(); // Form-row.
echo html_writer::end_tag('form');

// Active filter summary.
$activefilters = [];
if ($filtercategory > 0) {
    $catname         = $DB->get_field('course_categories', 'name', ['id' => $filtercategory]);
    $activefilters[] = get_string('filterbycategory', 'local_quizmetamonitor')
                     . ': <strong>' . format_string($catname) . '</strong>';
}
if ($filtercourse > 0) {
    $coursename      = $DB->get_field('course', 'fullname', ['id' => $filtercourse]);
    $activefilters[] = get_string('filterbycourse', 'local_quizmetamonitor')
                     . ': <strong>' . format_string($coursename) . '</strong>';
}
if ($filterstatus !== '') {
    $activefilters[] = get_string('filterbystatus', 'local_quizmetamonitor')
                     . ': <strong>' . $statusoptions[$filterstatus] . '</strong>';
}

if (!empty($activefilters)) {
    echo html_writer::tag(
        'p',
        get_string('activefilters', 'local_quizmetamonitor') . ': '
        . implode(' &nbsp;|&nbsp; ', $activefilters),
        ['class' => 'text-info mt-2 mb-0']
    );
}

echo html_writer::end_div(); // Card-body.
echo html_writer::end_div(); // Card.

// Build WHERE clause from filters.
$where  = '1 = 1';
$params = [];

if ($filtercategory > 0) {
    $where           .= ' AND qs.categoryid = :categoryid';
    $params['categoryid'] = $filtercategory;
}

if ($filtercourse > 0) {
    $where           .= ' AND qs.courseid = :courseid';
    $params['courseid'] = $filtercourse;
}

if ($filterstatus === 'below') {
    $where .= ' AND qs.is_below_threshold = 1';
} else if ($filterstatus === 'ok') {
    $where .= ' AND qs.is_below_threshold = 0';
}

// Filtered counter.
$filteredsql   = "SELECT COUNT(*)
                    FROM {local_quizmeta_quiz_score} qs
                   WHERE $where";
$filteredcount = $DB->count_records_sql($filteredsql, $params);

echo html_writer::tag(
    'p',
    get_string('showingquizzes', 'local_quizmetamonitor', $filteredcount),
    ['class' => 'text-muted mb-2']
);

// Quiz table.
$fields = "qs.quizid,
           qs.courseid,
           qs.categoryid,
           q.name        AS quizname,
           q.timeopen,
           q.timeclose,
           c.fullname    AS coursename,
           cc.name       AS categoryname,
           qs.score_percent,
           qs.threshold_applied,
           qs.is_below_threshold";

$from = "{local_quizmeta_quiz_score} qs
         JOIN {quiz}              q  ON q.id  = qs.quizid
         JOIN {course}            c  ON c.id  = qs.courseid
         JOIN {course_categories} cc ON cc.id = qs.categoryid";

// Pass filters into the table URL so sorting keeps them active.
$tableurlparams = [];
if ($filtercategory > 0) {
    $tableurlparams['categoryid'] = $filtercategory;
}
if ($filtercourse > 0) {
    $tableurlparams['courseid'] = $filtercourse;
}
if ($filterstatus !== '') {
    $tableurlparams['status'] = $filterstatus;
}

$table = new \local_quizmetamonitor\output\quiz_table(
    'quizmeta_admin_overview',
    true, // Show category column.
    true    // Show course column.
);

$table->define_baseurl(
    new moodle_url('/local/quizmetamonitor/pages/admin_overview.php', $tableurlparams)
);

$table->set_sql($fields, $from, $where, $params);
$table->out(50, true);

echo $OUTPUT->footer();
