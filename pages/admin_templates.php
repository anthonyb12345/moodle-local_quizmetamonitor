<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
$systemcontext = context_system::instance();
require_capability('local/quizmetamonitor:configure', $systemcontext);

global $DB;

$PAGE->set_url('/local/quizmetamonitor/pages/admin_templates.php');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('emailtemplates', 'local_quizmetamonitor'));
$PAGE->set_heading(get_string('emailtemplates', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('admin');

$templatekeys = [
    \local_quizmetamonitor\notification_manager::TYPE_TEACHER_CREATED
        => get_string('template_teacher_created',    'local_quizmetamonitor'),
    \local_quizmetamonitor\notification_manager::TYPE_TEACHER_SCORE_CHANGED
        => get_string('template_teacher_scorechanged', 'local_quizmetamonitor'),
    \local_quizmetamonitor\notification_manager::TYPE_EXAMMONITOR_ALERT
        => get_string('template_exammonitor_alert',   'local_quizmetamonitor'),
    \local_quizmetamonitor\notification_manager::TYPE_TEACHER_RESTORED
        => get_string('template_teacher_restored',    'local_quizmetamonitor'),
    \local_quizmetamonitor\notification_manager::TYPE_TEACHER_ALERT
        => get_string('template_teacher_alert',       'local_quizmetamonitor'),
];

// ---- Handle save --------------------------------------------------- //
if (data_submitted() && confirm_sesskey()) {
    $now = time();
    foreach ($templatekeys as $key => $label) {
        $subject   = optional_param('subject_'   . $key, '', PARAM_TEXT);
        $bodyhtml  = optional_param('bodyhtml_'  . $key, '', PARAM_RAW);
        $bodyplain = optional_param('bodyplain_' . $key, '', PARAM_TEXT);

        $existing = $DB->get_record('local_quizmeta_email_tmpl', ['template_key' => $key]);
        if ($existing) {
            $existing->subject      = $subject;
            $existing->body_html    = $bodyhtml;
            $existing->body_plain   = $bodyplain;
            $existing->timemodified = $now;
            $DB->update_record('local_quizmeta_email_tmpl', $existing);
        } else {
            $DB->insert_record('local_quizmeta_email_tmpl', (object)[
                'template_key' => $key,
                'subject'      => $subject,
                'body_html'    => $bodyhtml,
                'body_plain'   => $bodyplain,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }
    redirect(
        $PAGE->url,
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ---- Render -------------------------------------------------------- //
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('emailtemplates', 'local_quizmetamonitor'));

echo $OUTPUT->notification(
    get_string('placeholder_help', 'local_quizmetamonitor'),
    'info'
);

echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

foreach ($templatekeys as $key => $label) {
    $existing = $DB->get_record('local_quizmeta_email_tmpl', ['template_key' => $key]);

    echo html_writer::tag('h3', $label, ['class' => 'mt-4']);

    // Subject.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('subject', 'local_quizmetamonitor'),
        ['for' => 'subj_' . $key]);
    echo html_writer::empty_tag('input', [
        'type'  => 'text',
        'id'    => 'subj_' . $key,
        'name'  => 'subject_' . $key,
        'value' => $existing->subject ?? '',
        'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    // HTML body.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('bodyhtml', 'local_quizmetamonitor'),
        ['for' => 'html_' . $key]);
    echo html_writer::tag('textarea',
        htmlspecialchars($existing->body_html ?? '', ENT_QUOTES),
        [
            'id'    => 'html_' . $key,
            'name'  => 'bodyhtml_' . $key,
            'rows'  => '6',
            'class' => 'form-control',
        ]
    );
    echo html_writer::end_div();

    // Plain body.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('bodyplain', 'local_quizmetamonitor'),
        ['for' => 'plain_' . $key]);
    echo html_writer::tag('textarea',
        htmlspecialchars($existing->body_plain ?? '', ENT_QUOTES),
        [
            'id'    => 'plain_' . $key,
            'name'  => 'bodyplain_' . $key,
            'rows'  => '6',
            'class' => 'form-control',
        ]
    );
    echo html_writer::end_div();
}

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary mt-3',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();