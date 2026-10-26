<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
$systemcontext = context_system::instance();
require_capability('local/quizmetamonitor:viewall', $systemcontext);

global $DB;

// -------------------------------------------------------------------- //
// Read filter parameters                                                //
// -------------------------------------------------------------------- //
$filtercategory  = optional_param('categoryid',   0,  PARAM_INT);
$filterstatus    = optional_param('status',        '', PARAM_ALPHA);  // 'below', 'ok', ''
$filterdatefrom  = optional_param('datefrom',      '', PARAM_TEXT);   // Y-m-d string
$filterdateto    = optional_param('dateto',        '', PARAM_TEXT);   // Y-m-d string
$format          = optional_param('format',        '', PARAM_ALPHA);

// -------------------------------------------------------------------- //
// Convert date strings to timestamps                                    //
// -------------------------------------------------------------------- //
$timestampfrom = 0;
$timestampto   = 0;
$dateerror     = '';

if (!empty($filterdatefrom)) {
    $parsed = \DateTime::createFromFormat('Y-m-d', $filterdatefrom);
    if ($parsed) {
        $parsed->setTime(0, 0, 0);
        $timestampfrom = $parsed->getTimestamp();
    } else {
        $dateerror = get_string('error_invaliddate', 'local_quizmetamonitor');
    }
}

if (!empty($filterdateto)) {
    $parsed = \DateTime::createFromFormat('Y-m-d', $filterdateto);
    if ($parsed) {
        // End of the selected day (23:59:59).
        $parsed->setTime(23, 59, 59);
        $timestampto = $parsed->getTimestamp();
    } else {
        $dateerror = get_string('error_invaliddate', 'local_quizmetamonitor');
    }
}

if ($timestampfrom && $timestampto && $timestampfrom > $timestampto) {
    $dateerror = get_string('error_datefromafterdate', 'local_quizmetamonitor');
}

// -------------------------------------------------------------------- //
// Build WHERE clause                                                    //
// -------------------------------------------------------------------- //
$where  = '1 = 1';
$params = [];

if ($filtercategory > 0) {
    $where               .= ' AND qs.categoryid = :categoryid';
    $params['categoryid'] = $filtercategory;
}

if ($filterstatus === 'below') {
    $where .= ' AND qs.is_below_threshold = 1';
} else if ($filterstatus === 'ok') {
    $where .= ' AND qs.is_below_threshold = 0';
}

// Date filter applies to the quiz open date.
if ($timestampfrom > 0) {
    $where                .= ' AND q.timeopen >= :datefrom';
    $params['datefrom']    = $timestampfrom;
}

if ($timestampto > 0) {
    $where               .= ' AND q.timeopen <= :dateto';
    $params['dateto']     = $timestampto;
}

// -------------------------------------------------------------------- //
// Core SQL                                                              //
// -------------------------------------------------------------------- //
$selectfields = "qs.quizid,
                 qs.score_percent,
                 qs.threshold_applied,
                 qs.is_below_threshold,
                 qs.criteria_snapshot,
                 qs.timemodified        AS last_computed,
                 q.name                AS quiz_name,
                 q.timeopen            AS open_date,
                 q.timeclose           AS close_date,
                 q.timelimit           AS time_limit,
                 q.attempts            AS allowed_attempts,
                 q.password            AS quiz_password,
                 q.subnet              AS ip_restriction,
                 q.shuffleanswers      AS shuffle_answers,
                 q.grade               AS max_grade,
                 c.fullname            AS course_name,
                 c.shortname           AS course_shortname,
                 cc.name               AS category_name";

$fromclause = "{local_quizmeta_quiz_score} qs
               JOIN {quiz}              q  ON q.id  = qs.quizid
               JOIN {course}            c  ON c.id  = qs.courseid
               JOIN {course_categories} cc ON cc.id = qs.categoryid";

// -------------------------------------------------------------------- //
// Helper: extract criterion result from snapshot                        //
// -------------------------------------------------------------------- //
function get_criterion_value(array $breakdown, string $key): string {
    foreach ($breakdown as $item) {
        if ($item['key'] === $key) {
            // Choice-based criteria (SEB) always pass; report the option actually chosen.
            if (isset($item['choice'])) {
                return $item['choice'] === 'no' ? 'No' : 'Yes';
            }
            return $item['met'] ? 'Yes' : 'No';
        }
    }
    return 'N/A';
}

// -------------------------------------------------------------------- //
// CSV download                                                          //
// -------------------------------------------------------------------- //
if ($format === 'csv' && empty($dateerror)) {

    $sql  = "SELECT $selectfields FROM $fromclause WHERE $where ORDER BY cc.name, c.fullname, q.name";
    $rows = $DB->get_records_sql($sql, $params);

    // Build a descriptive filename that reflects active filters.
    $fileparts = ['quiz_security_report'];
    if ($filtercategory > 0) {
        $catname     = $DB->get_field('course_categories', 'name', ['id' => $filtercategory]);
        $fileparts[] = preg_replace('/[^a-z0-9]/i', '_', $catname);
    }
    if ($filterstatus !== '') {
        $fileparts[] = $filterstatus;
    }
    if ($filterdatefrom !== '') {
        $fileparts[] = 'from_' . $filterdatefrom;
    }
    if ($filterdateto !== '') {
        $fileparts[] = 'to_' . $filterdateto;
    }
    $fileparts[] = date('Ymd_His');
    $filename    = implode('_', $fileparts) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM for Excel.
    fputs($out, "\xEF\xBB\xBF");

    // Header row.
    fputcsv($out, [
        'Quiz Name',
        'Course Name',
        'Course Short Name',
        'Category',
        'Open Date',
        'Close Date',
        'Time Limit',
        'Allowed Attempts',
        'Password Set',
        'IP Restriction',
        'Shuffle Answers',
        'Max Grade',
        'Criterion: Open Date Set',
        'Criterion: Close Date Set',
        'Criterion: Attempts Limited',
        'Criterion: Password Set',
        'Criterion: IP Restriction Set',
        'Criterion: Block Concurrent',
        'Criterion: SEB Enabled',
        'Security Score (%)',
        'Threshold (%)',
        'Below Threshold',
        'Last Computed',
    ]);

    foreach ($rows as $row) {
        $breakdown = json_decode($row->criteria_snapshot, true) ?? [];

        fputcsv($out, [
            $row->quiz_name,
            $row->course_name,
            $row->course_shortname,
            $row->category_name,
            $row->open_date  ? userdate($row->open_date,  '%Y-%m-%d %H:%M') : '',
            $row->close_date ? userdate($row->close_date, '%Y-%m-%d %H:%M') : '',
            $row->time_limit > 0 ? $row->time_limit . 's' : 'No limit',
            $row->allowed_attempts == 0 ? 'Unlimited' : $row->allowed_attempts,
            !empty($row->quiz_password)  ? 'Yes' : 'No',
            !empty($row->ip_restriction) ? $row->ip_restriction : 'None',
            $row->shuffle_answers ? 'Yes' : 'No',
            $row->max_grade,
            get_criterion_value($breakdown, 'open_date_set'),
            get_criterion_value($breakdown, 'close_date_set'),
            get_criterion_value($breakdown, 'allowed_attempts_set'),
            get_criterion_value($breakdown, 'password_set'),
            get_criterion_value($breakdown, 'ip_restriction_set'),
            get_criterion_value($breakdown, 'block_concurrent_set'),
            get_criterion_value($breakdown, 'seb_enabled'),
            number_format($row->score_percent,     2),
            number_format($row->threshold_applied, 2),
            $row->is_below_threshold ? 'Yes' : 'No',
            userdate($row->last_computed, '%Y-%m-%d %H:%M'),
        ]);
    }

    fclose($out);
    exit;
}

// -------------------------------------------------------------------- //
// Page view                                                             //
// -------------------------------------------------------------------- //
$PAGE->set_url('/local/quizmetamonitor/pages/admin_export.php');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('exportreport', 'local_quizmetamonitor'));
$PAGE->set_heading(get_string('exportreport', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportreport', 'local_quizmetamonitor'));

// -------------------------------------------------------------------- //
// Filter form                                                           //
// -------------------------------------------------------------------- //
$categories    = core_course_category::make_categories_list();
$statusoptions = [
    ''      => get_string('allstatuses',    'local_quizmetamonitor'),
    'below' => get_string('belowthreshold', 'local_quizmetamonitor'),
    'ok'    => get_string('ok',             'local_quizmetamonitor'),
];

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5',
    get_string('exportfilters', 'local_quizmetamonitor'),
    ['class' => 'card-title']
);

if ($dateerror) {
    echo $OUTPUT->notification($dateerror, 'error');
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url->out_omit_querystring(),
    'id'     => 'export-filter-form',
]);

echo html_writer::start_div('form-row');

// ---- Category ---------------------------------------------------- //
echo html_writer::start_div('col-md-3 form-group');
echo html_writer::tag(
    'label',
    get_string('filterbycategory', 'local_quizmetamonitor'),
    ['for' => 'exp_cat', 'class' => 'font-weight-bold']
);
echo html_writer::select(
    [0 => get_string('allcategories', 'local_quizmetamonitor')] + $categories,
    'categoryid',
    $filtercategory,
    false,
    ['id' => 'exp_cat', 'class' => 'custom-select']
);
echo html_writer::end_div();

// ---- Status ------------------------------------------------------ //
echo html_writer::start_div('col-md-2 form-group');
echo html_writer::tag(
    'label',
    get_string('filterbystatus', 'local_quizmetamonitor'),
    ['for' => 'exp_status', 'class' => 'font-weight-bold']
);
echo html_writer::select(
    $statusoptions,
    'status',
    $filterstatus,
    false,
    ['id' => 'exp_status', 'class' => 'custom-select']
);
echo html_writer::end_div();

// ---- Date range header ------------------------------------------- //
echo html_writer::start_div('col-md-4 form-group');
echo html_writer::tag(
    'label',
    get_string('quizstartdaterange', 'local_quizmetamonitor'),
    ['class' => 'font-weight-bold d-block']
);
echo html_writer::tag(
    'small',
    get_string('quizstartdaterange_help', 'local_quizmetamonitor'),
    ['class' => 'form-text text-muted d-block mb-2']
);

// Two date inputs side by side inside one column.
echo html_writer::start_div('d-flex align-items-center');

// From date.
echo html_writer::start_div('mr-2');
echo html_writer::tag(
    'label',
    get_string('startdatefrom', 'local_quizmetamonitor'),
    ['for' => 'exp_datefrom', 'class' => 'small text-muted mb-1 d-block']
);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'id'    => 'exp_datefrom',
    'name'  => 'datefrom',
    'value' => $filterdatefrom,
    'class' => 'form-control form-control-sm',
    'max'   => $filterdateto ?: '', // cannot pick from-date after to-date
]);
echo html_writer::end_div();

// Arrow separator.
echo html_writer::tag(
    'span',
    '→',
    ['class' => 'mx-2 text-muted font-weight-bold', 'style' => 'font-size:1.2em']
);

// To date.
echo html_writer::start_div();
echo html_writer::tag(
    'label',
    get_string('startdateto', 'local_quizmetamonitor'),
    ['for' => 'exp_dateto', 'class' => 'small text-muted mb-1 d-block']
);
echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'id'    => 'exp_dateto',
    'name'  => 'dateto',
    'value' => $filterdateto,
    'class' => 'form-control form-control-sm',
    'min'   => $filterdatefrom ?: '', // cannot pick to-date before from-date
]);
echo html_writer::end_div();

echo html_writer::end_div(); // d-flex
echo html_writer::end_div(); // col

// ---- Buttons ----------------------------------------------------- //
echo html_writer::start_div('col-md-3 form-group d-flex align-items-end flex-wrap');

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('applyfilters', 'local_quizmetamonitor'),
    'class' => 'btn btn-primary mr-2 mb-2',
]);

$reseturl = new moodle_url('/local/quizmetamonitor/pages/admin_export.php');
echo html_writer::link(
    $reseturl,
    get_string('resetfilters', 'local_quizmetamonitor'),
    ['class' => 'btn btn-outline-secondary mb-2']
);

echo html_writer::end_div();
echo html_writer::end_div(); // form-row
echo html_writer::end_tag('form');
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// -------------------------------------------------------------------- //
// Active filter summary                                                 //
// -------------------------------------------------------------------- //
$activefilters = [];

if ($filtercategory > 0) {
    $catname         = $DB->get_field('course_categories', 'name', ['id' => $filtercategory]);
    $activefilters[] = get_string('filterbycategory', 'local_quizmetamonitor')
                     . ': <strong>' . format_string($catname) . '</strong>';
}

if ($filterstatus !== '') {
    $activefilters[] = get_string('filterbystatus', 'local_quizmetamonitor')
                     . ': <strong>' . $statusoptions[$filterstatus] . '</strong>';
}

if ($filterdatefrom !== '' || $filterdateto !== '') {
    $from = $filterdatefrom ?: get_string('anytime', 'local_quizmetamonitor');
    $to   = $filterdateto   ?: get_string('anytime', 'local_quizmetamonitor');

    $activefilters[] = get_string('quizstartdaterange', 'local_quizmetamonitor')
                     . ': <strong>' . $from . ' → ' . $to . '</strong>';
}

if (!empty($activefilters)) {
    echo html_writer::tag(
        'p',
        get_string('activefilters', 'local_quizmetamonitor') . ': '
        . implode(' &nbsp;|&nbsp; ', $activefilters),
        ['class' => 'text-info mb-3']
    );
}

// -------------------------------------------------------------------- //
// Preview count                                                         //
// -------------------------------------------------------------------- //
$countsql   = "SELECT COUNT(*) FROM $fromclause WHERE $where";
$totalcount = $DB->count_records_sql($countsql, $params);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body d-flex justify-content-between align-items-center');

echo html_writer::tag(
    'p',
    get_string('exportpreviewcount', 'local_quizmetamonitor', $totalcount),
    ['class' => 'mb-0 font-weight-bold']
);

// Download button — carries all current filter params.
if ($totalcount > 0 && empty($dateerror)) {
    $csvparams = ['format' => 'csv'];
    if ($filtercategory > 0) {
        $csvparams['categoryid'] = $filtercategory;
    }
    if ($filterstatus !== '') {
        $csvparams['status'] = $filterstatus;
    }
    if ($filterdatefrom !== '') {
        $csvparams['datefrom'] = $filterdatefrom;
    }
    if ($filterdateto !== '') {
        $csvparams['dateto'] = $filterdateto;
    }

    $csvurl = new moodle_url('/local/quizmetamonitor/pages/admin_export.php', $csvparams);

    echo html_writer::link(
        $csvurl,
        '⬇ ' . get_string('downloadcsv', 'local_quizmetamonitor')
        . ' (' . $totalcount . ' ' . get_string('quizzes', 'local_quizmetamonitor') . ')',
        ['class' => 'btn btn-success']
    );
} else if ($totalcount === 0) {
    echo html_writer::tag(
        'span',
        get_string('noquizzesmatchfilter', 'local_quizmetamonitor'),
        ['class' => 'text-muted']
    );
}

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// -------------------------------------------------------------------- //
// Preview table — first 10 rows only                                   //
// -------------------------------------------------------------------- //
if ($totalcount > 0 && empty($dateerror)) {

    echo html_writer::tag(
        'h5',
        get_string('exportpreview', 'local_quizmetamonitor'),
        ['class' => 'mb-3']
    );

    $previewsql = "SELECT $selectfields
                     FROM $fromclause
                    WHERE $where
                 ORDER BY cc.name, c.fullname, q.name
                    LIMIT 10";

    $previewrows = $DB->get_records_sql($previewsql, $params);

    $previewtbl              = new html_table();
    $previewtbl->attributes  = ['class' => 'generaltable table table-sm table-striped'];
    $previewtbl->head        = [
        get_string('quizname',    'local_quizmetamonitor'),
        get_string('course'),
        get_string('category'),
        get_string('opendate',    'local_quizmetamonitor'),
        get_string('closedate',   'local_quizmetamonitor'),
        get_string('score',       'local_quizmetamonitor'),
        get_string('threshold',   'local_quizmetamonitor'),
        get_string('status',      'local_quizmetamonitor'),
    ];

    foreach ($previewrows as $row) {
        $isbelow = (bool) $row->is_below_threshold;

        $previewtbl->data[] = [
            format_string($row->quiz_name),
            format_string($row->course_name),
            format_string($row->category_name),
            $row->open_date  ? userdate($row->open_date,  get_string('strftimedateshort', 'langconfig')) : '—',
            $row->close_date ? userdate($row->close_date, get_string('strftimedateshort', 'langconfig')) : '—',
            number_format($row->score_percent, 1) . '%',
            number_format($row->threshold_applied, 1) . '%',
            $isbelow
                ? html_writer::tag('span', get_string('belowthreshold', 'local_quizmetamonitor'),
                    ['class' => 'badge badge-danger'])
                : html_writer::tag('span', get_string('ok', 'local_quizmetamonitor'),
                    ['class' => 'badge badge-success']),
        ];
    }

    echo html_writer::table($previewtbl);

    if ($totalcount > 10) {
        echo html_writer::tag(
            'p',
            get_string('exportpreviewlimited', 'local_quizmetamonitor',
                ['shown' => 10, 'total' => $totalcount]),
            ['class' => 'text-muted small']
        );
    }
}

echo $OUTPUT->footer();