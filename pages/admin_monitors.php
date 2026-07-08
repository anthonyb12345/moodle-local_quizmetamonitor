<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
$systemcontext = context_system::instance();
require_capability('local/quizmetamonitor:configure', $systemcontext);

global $DB, $USER;

$PAGE->set_url('/local/quizmetamonitor/pages/admin_monitors.php');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('assignmonitors', 'local_quizmetamonitor'));
$PAGE->set_heading(get_string('assignmonitors', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('admin');

// ---- Errors/messages to display ------------------------------------ //
$errormsg   = '';
$successmsg = '';

// ---- Handle POST actions ------------------------------------------- //
if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'add') {
        $email      = trim(optional_param('useremail', '', PARAM_EMAIL));
        $categoryid = required_param('categoryid', PARAM_INT);

        if (empty($email)) {
            $errormsg = get_string('error_emailrequired', 'local_quizmetamonitor');

        } else {
            // Look up user by email.
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

            if (!$user) {
                $errormsg = get_string('error_usernotfound', 'local_quizmetamonitor', $email);

            } elseif ($categoryid <= 0) {
                $errormsg = get_string('error_selectcategory', 'local_quizmetamonitor');

            } else {
                $exists = $DB->record_exists('local_quizmeta_exammonitor', [
                    'userid'     => $user->id,
                    'categoryid' => $categoryid,
                ]);

                if ($exists) {
                    $errormsg = get_string('error_alreadyassigned', 'local_quizmetamonitor',
                        fullname($user));
                } else {
                    $DB->insert_record('local_quizmeta_exammonitor', (object)[
                        'userid'      => $user->id,
                        'categoryid'  => $categoryid,
                        'assignedby'  => $USER->id,
                        'timecreated' => time(),
                    ]);

                    // Grant Moodle capability at category context.
                    $catcontext = context_coursecat::instance($categoryid);
                    $roleid     = get_or_create_exammonitor_role();
                    if ($roleid) {
                        role_assign($roleid, $user->id, $catcontext->id);
                    }

                    $successmsg = get_string('monitor_assigned', 'local_quizmetamonitor',
                        fullname($user));
                }
            }
        }
    }

    if ($action === 'remove') {
        $id  = required_param('id', PARAM_INT);
        $rec = $DB->get_record('local_quizmeta_exammonitor', ['id' => $id]);

        if ($rec) {
            $catcontext = context_coursecat::instance($rec->categoryid);
            $roleid     = $DB->get_field('role', 'id', ['shortname' => 'quizmeta_exammonitor']);
            if ($roleid) {
                role_unassign($roleid, $rec->userid, $catcontext->id);
            }
            $DB->delete_records('local_quizmeta_exammonitor', ['id' => $id]);
            $successmsg = get_string('monitor_removed', 'local_quizmetamonitor');
        }
    }
}

// ---- Helper function ----------------------------------------------- //
function get_or_create_exammonitor_role(): int {
    global $DB;

    $shortname = 'quizmeta_exammonitor';
    $roleid    = $DB->get_field('role', 'id', ['shortname' => $shortname]);

    if (!$roleid) {
        $roleid = create_role(
            'Exam Monitor (Quiz Metadata)',
            $shortname,
            'Can view quiz metadata for their assigned category.',
            'teacher'
        );
        set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);
        assign_capability(
            'local/quizmetamonitor:viewcategory',
            CAP_ALLOW,
            $roleid,
            context_system::instance()->id
        );
        assign_capability(
            'local/quizmetamonitor:receiveexamalert',
            CAP_ALLOW,
            $roleid,
            context_system::instance()->id
        );
    }

    return (int) $roleid;
}

// ---- Render -------------------------------------------------------- //
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assignmonitors', 'local_quizmetamonitor'));

if ($errormsg) {
    echo $OUTPUT->notification($errormsg, 'error');
}
if ($successmsg) {
    echo $OUTPUT->notification($successmsg, 'success');
}

$categories = core_course_category::make_categories_list();

// ---- Assignment form ----------------------------------------------- //
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class'  => 'mb-4',
]);
echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'sesskey',
    'value' => sesskey(),
]);
echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'action',
    'value' => 'add',
]);

echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('addmonitor', 'local_quizmetamonitor'), ['class' => 'card-title']);

// Email field.
echo html_writer::start_div('form-group');
echo html_writer::tag(
    'label',
    get_string('useremail', 'local_quizmetamonitor'),
    ['for' => 'qmm_email', 'class' => 'font-weight-bold']
);
echo html_writer::empty_tag('input', [
    'type'        => 'email',
    'id'          => 'qmm_email',
    'name'        => 'useremail',
    'class'       => 'form-control',
    'placeholder' => get_string('enteruseremail', 'local_quizmetamonitor'),
    'required'    => 'required',
    'style'       => 'max-width:350px',
]);
echo html_writer::tag(
    'small',
    get_string('emailhelp', 'local_quizmetamonitor'),
    ['class' => 'form-text text-muted']
);
echo html_writer::end_div();

// Category selector.
echo html_writer::start_div('form-group');
echo html_writer::tag(
    'label',
    get_string('selectcategory', 'local_quizmetamonitor'),
    ['for' => 'qmm_cat', 'class' => 'font-weight-bold']
);
echo html_writer::select(
    $categories,
    'categoryid',
    0,
    ['' => get_string('choosecategory', 'local_quizmetamonitor')],
    ['id' => 'qmm_cat', 'class' => 'custom-select', 'style' => 'max-width:350px']
);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('assign', 'local_quizmetamonitor'),
    'class' => 'btn btn-primary',
]);

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

echo html_writer::end_tag('form');

// ---- Current assignments table ------------------------------------- //
echo html_writer::tag('h4', get_string('currentassignments', 'local_quizmetamonitor'),
    ['class' => 'mt-4 mb-3']);

$sql = "SELECT em.id,
               em.userid,
               em.categoryid,
               em.timecreated,
               u.firstname,
               u.lastname,
               u.firstnamephonetic,
               u.lastnamephonetic,
               u.middlename,
               u.alternatename,
               u.email,
               cc.name AS categoryname
          FROM {local_quizmeta_exammonitor} em
          JOIN {user}              u  ON u.id  = em.userid
          JOIN {course_categories} cc ON cc.id = em.categoryid
      ORDER BY cc.name, u.lastname, u.firstname";

$assignments = $DB->get_records_sql($sql);

if (empty($assignments)) {
    echo $OUTPUT->notification(
        get_string('noassignments', 'local_quizmetamonitor'),
        'info'
    );
} else {
    $tableout = new html_table();
    $tableout->head = [
        get_string('fullname'),
        get_string('email'),
        get_string('category'),
        get_string('dateassigned', 'local_quizmetamonitor'),
        get_string('actions', 'local_quizmetamonitor'),
    ];
    $tableout->attributes['class'] = 'generaltable table table-striped';

    foreach ($assignments as $a) {
        $tableout->data[] = [
            fullname($a),
            $a->email,
            format_string($a->categoryname),
            userdate($a->timecreated),
            html_writer::tag(
                'form',
                html_writer::empty_tag('input', [
                    'type'  => 'hidden',
                    'name'  => 'action',
                    'value' => 'remove',
                ])
                . html_writer::empty_tag('input', [
                    'type'  => 'hidden',
                    'name'  => 'id',
                    'value' => $a->id,
                ])
                . html_writer::empty_tag('input', [
                    'type'  => 'hidden',
                    'name'  => 'sesskey',
                    'value' => sesskey(),
                ])
                . html_writer::empty_tag('input', [
                    'type'  => 'submit',
                    'value' => get_string('remove', 'local_quizmetamonitor'),
                    'class' => 'btn btn-sm btn-danger',
                    'onclick' => 'return confirm("' . get_string('confirmremove', 'local_quizmetamonitor') . '")',
                ]),
                [
                    'method' => 'post',
                    'action' => $PAGE->url->out(false),
                ]
            ),
        ];
    }

    echo html_writer::table($tableout);
}

echo $OUTPUT->footer();