<?php
require_once(__DIR__ . '/../../../config.php');

require_login();

global $DB, $USER;

$quizid = required_param('quizid', PARAM_INT);

$scorerecord = $DB->get_record('local_quizmeta_quiz_score', ['quizid' => $quizid], '*', MUST_EXIST);
$quiz        = $DB->get_record('quiz',   ['id' => $quizid],               '*', MUST_EXIST);
$course      = $DB->get_record('course', ['id' => $scorerecord->courseid], '*', MUST_EXIST);
$cm          = get_coursemodule_from_instance('quiz', $quizid, $course->id, false, MUST_EXIST);

// ---- Permission check --------------------------------------------- //
$sysctx = context_system::instance();
$catctx = context_coursecat::instance($scorerecord->categoryid);
$coursectx = context_course::instance($course->id);

$allowed =
    has_capability('local/quizmetamonitor:viewall', $sysctx)
    || (has_capability('local/quizmetamonitor:viewcategory', $catctx)
        && \local_quizmetamonitor\category_util::is_monitor_for_category($USER->id, (int) $scorerecord->categoryid))
    || has_capability('local/quizmetamonitor:viewown', $coursectx);

if (!$allowed) {
    throw new moodle_exception('nopermissions', 'error', '', 'view this quiz detail');
}

$PAGE->set_url('/local/quizmetamonitor/pages/quiz_detail.php', ['quizid' => $quizid]);
$PAGE->set_context($coursectx);
$PAGE->set_title(format_string($quiz->name) . ' — ' . get_string('quizdetail', 'local_quizmetamonitor'));
$PAGE->set_heading(format_string($quiz->name));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizdetail', 'local_quizmetamonitor') . ': ' . format_string($quiz->name));

// ---- Summary card -------------------------------------------------- //
$isbelow  = (bool) $scorerecord->is_below_threshold;
$badgeclass = $isbelow ? 'badge-danger' : 'badge-success';
$badgetext  = $isbelow
    ? get_string('belowthreshold', 'local_quizmetamonitor')
    : get_string('ok',             'local_quizmetamonitor');

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

$summarydata = [
    get_string('course')                              => format_string($course->fullname),
    get_string('score',     'local_quizmetamonitor')  => '<strong>' . $scorerecord->score_percent . '%</strong>',
    get_string('threshold', 'local_quizmetamonitor')  => $scorerecord->threshold_applied . '%',
    get_string('status',    'local_quizmetamonitor')  => html_writer::tag('span', $badgetext, ['class' => 'badge ' . $badgeclass]),
    get_string('opendate',  'local_quizmetamonitor')  => $quiz->timeopen  ? userdate($quiz->timeopen)  : '—',
    get_string('closedate', 'local_quizmetamonitor')  => $quiz->timeclose ? userdate($quiz->timeclose) : '—',
];

$dl = '';
foreach ($summarydata as $label => $value) {
    $dl .= html_writer::tag('dt', $label, ['class' => 'col-sm-3'])
         . html_writer::tag('dd', $value, ['class' => 'col-sm-9']);
}
echo html_writer::tag('dl', $dl, ['class' => 'row']);

echo html_writer::end_div();
echo html_writer::end_div();

// ---- Criteria breakdown table ------------------------------------- //
$breakdown = json_decode($scorerecord->criteria_snapshot, true) ?? [];

echo $OUTPUT->heading(get_string('criteriabreakdown', 'local_quizmetamonitor'), 3);

$table = new html_table();
$table->head = [
    get_string('criterion',      'local_quizmetamonitor'),
    get_string('pointspossible', 'local_quizmetamonitor'),
    get_string('pointsearned',   'local_quizmetamonitor'),
    get_string('status',         'local_quizmetamonitor'),
];
$table->attributes['class'] = 'generaltable';

foreach ($breakdown as $item) {
    $met = (bool) $item['met'];
    $table->data[] = [
        $item['label'],
        $item['points_possible'],
        $item['points_earned'],
        $met
            ? html_writer::tag('span', get_string('pass', 'local_quizmetamonitor'),
                ['class' => 'badge badge-success'])
            : html_writer::tag('span', get_string('fail', 'local_quizmetamonitor'),
                ['class' => 'badge badge-danger']),
    ];
}

echo html_writer::table($table);

// ---- Score history ------------------------------------------------- //
$history = $DB->get_records(
    'local_quizmeta_score_history',
    ['quizid' => $quizid],
    'timecreated DESC',
    '*',
    0,
    10
);

if (!empty($history)) {
    echo $OUTPUT->heading(get_string('scorehistory', 'local_quizmetamonitor'), 3);

    $htable = new html_table();
    $htable->head = [
        get_string('score',       'local_quizmetamonitor'),
        get_string('threshold',   'local_quizmetamonitor'),
        get_string('changedby',   'local_quizmetamonitor'),
        get_string('date'),
    ];
    $htable->attributes['class'] = 'generaltable';

    foreach ($history as $h) {
        $by = ($h->changed_by_userid > 0)
            ? fullname(\core_user::get_user($h->changed_by_userid))
            : get_string('system', 'local_quizmetamonitor');

        $htable->data[] = [
            $h->score_percent . '%',
            $h->threshold_applied . '%',
            $by,
            userdate($h->timecreated),
        ];
    }

    echo html_writer::table($htable);
}

// -------------------------------------------------------------------- //
// Actions                                                               //
// -------------------------------------------------------------------- //

// View quiz URL.
$viewurl = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);

// Edit quiz settings URL — correct Moodle URL for editing a course module.
$editurl = new moodle_url('/course/modedit.php', [
    'update'   => $cm->id,
    'return'   => 0,
    'sr'       => 0,
]);

// Back URL — go back to where the user came from.
$backurl = null;
if (has_capability('local/quizmetamonitor:viewall', context_system::instance())) {
    $backurl = new moodle_url('/local/quizmetamonitor/pages/admin_overview.php');
} else if (has_capability('local/quizmetamonitor:viewown', context_course::instance($course->id))) {
    $backurl = new moodle_url('/local/quizmetamonitor/pages/teacher_quizzes.php',
        ['courseid' => $course->id]);
}

echo html_writer::start_div('mt-4 d-flex flex-wrap gap-2');

if ($backurl) {
    echo html_writer::link(
        $backurl,
        '← ' . get_string('back'),
        ['class' => 'btn btn-outline-secondary mr-2 mb-2']
    );
}

echo html_writer::link(
    $viewurl,
    get_string('gotoquiz', 'local_quizmetamonitor'),
    ['class' => 'btn btn-secondary mr-2 mb-2']
);

// Only show edit button to users who can edit the quiz.
if (has_capability('moodle/course:manageactivities', context_course::instance($course->id))) {
    echo html_writer::link(
        $editurl,
        get_string('editquiz', 'local_quizmetamonitor'),
        ['class' => 'btn btn-outline-primary mb-2']
    );
}

echo html_writer::end_div();

echo $OUTPUT->footer();