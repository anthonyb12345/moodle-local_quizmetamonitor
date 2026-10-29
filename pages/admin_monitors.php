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
 * Admin page: assign and remove Exam Monitors for course categories.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

// Errors/messages to display.
$errormsg   = '';
$successmsg = '';

// Handle POST actions.
if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'add') {
        $email      = trim(optional_param('useremail', '', PARAM_EMAIL));
        $categoryid = required_param('categoryid', PARAM_INT);

        if (empty($email)) {
            $errormsg = get_string('error_emailrequired', 'local_quizmetamonitor');
        } else {
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

            if (!$user) {
                $errormsg = get_string('error_usernotfound', 'local_quizmetamonitor', $email);
            } else if ($categoryid <= 0) {
                $errormsg = get_string('error_selectcategory', 'local_quizmetamonitor');
            } else {
                $exists = $DB->record_exists('local_quizmetamonitor_exammonitor', [
                    'userid'     => $user->id,
                    'categoryid' => $categoryid,
                ]);

                if ($exists) {
                    $errormsg = get_string(
                        'error_alreadyassigned',
                        'local_quizmetamonitor',
                        fullname($user)
                    );
                } else {
                    // Save to plugin table.
                    $DB->insert_record('local_quizmetamonitor_exammonitor', (object)[
                        'userid'      => $user->id,
                        'categoryid'  => $categoryid,
                        'assignedby'  => $USER->id,
                        'timecreated' => time(),
                    ]);

                    $roleid = local_quizmetamonitor_get_or_create_exammonitor_role();

                    // 1. Assign role at category context (for plugin views).
                    $catcontext = context_coursecat::instance($categoryid);
                    role_assign($roleid, $user->id, $catcontext->id);

                    // 2. Assign role at each course context inside the category
                    // so the monitor can enter courses and edit quizzes.
                    local_quizmetamonitor_assign_role_to_all_courses_in_category($user->id, $categoryid, $roleid);

                    $successmsg = get_string(
                        'monitor_assigned',
                        'local_quizmetamonitor',
                        fullname($user)
                    );
                }
            }
        }
    }

    if ($action === 'remove') {
        $id  = required_param('id', PARAM_INT);
        $rec = $DB->get_record('local_quizmetamonitor_exammonitor', ['id' => $id]);

        if ($rec) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'quizmeta_exammonitor']);

            if ($roleid) {
                // Remove from category context.
                $catcontext = context_coursecat::instance($rec->categoryid);
                role_unassign($roleid, $rec->userid, $catcontext->id);

                // Remove from all course contexts in that category.
                local_quizmetamonitor_unassign_role_from_all_courses_in_category(
                    $rec->userid,
                    $rec->categoryid,
                    $roleid
                );
            }

            $DB->delete_records('local_quizmetamonitor_exammonitor', ['id' => $id]);
            $successmsg = get_string('monitor_removed', 'local_quizmetamonitor');
        }
    }
}

// Helper function.
/**
 * Get the Exam Monitor role id, creating the role and setting its capabilities if needed.
 *
 * @return int Role id.
 */
function local_quizmetamonitor_get_or_create_exammonitor_role(): int {
    global $DB;

    $shortname = 'quizmeta_exammonitor';
    $roleid    = $DB->get_field('role', 'id', ['shortname' => $shortname]);

    if (!$roleid) {
        $roleid = create_role(
            get_string('examrole_name', 'local_quizmetamonitor'),
            $shortname,
            get_string('examrole_description', 'local_quizmetamonitor'),
            ''
        );

        set_role_contextlevels($roleid, [CONTEXT_COURSECAT, CONTEXT_COURSE]);
    }

    $sysctx = context_system::instance();

    // Plugin capabilities.
    if (get_capability_info('local/quizmetamonitor:viewcategory')) {
        assign_capability(
            'local/quizmetamonitor:viewcategory',
            CAP_ALLOW,
            $roleid,
            $sysctx->id,
            true
        );
    }
    if (get_capability_info('local/quizmetamonitor:receiveexamalert')) {
        assign_capability(
            'local/quizmetamonitor:receiveexamalert',
            CAP_ALLOW,
            $roleid,
            $sysctx->id,
            true
        );
    }

    // Course access.
    assign_capability(
        'moodle/course:view',
        CAP_ALLOW,
        $roleid,
        $sysctx->id,
        true
    );
    assign_capability(
        'moodle/course:viewhiddencourses',
        CAP_ALLOW,
        $roleid,
        $sysctx->id,
        true
    );

    // Needed so Moodle allows saving quiz settings form.
    assign_capability(
        'moodle/course:manageactivities',
        CAP_ALLOW,
        $roleid,
        $sysctx->id,
        true
    );

    // Quiz — full management access.
    $quizcaps = [
        'mod/quiz:view',
        'mod/quiz:manage',
        'mod/quiz:viewreports',
        'mod/quiz:preview',
        'mod/quiz:grade',
        'mod/quiz:viewoverrides',
        'mod/quiz:manageoverrides',
    ];

    foreach ($quizcaps as $cap) {
        if (get_capability_info($cap)) {
            assign_capability($cap, CAP_ALLOW, $roleid, $sysctx->id, true);
        }
    }

    // Prevent course-level editing that is not quiz related.
    $preventcaps = [
        'moodle/course:update', // Cannot edit course settings.
        'moodle/course:delete', // Cannot delete the course.
        'moodle/course:managecourseadd', // Cannot create courses.
        'moodle/grade:viewall', // Cannot see full gradebook.
        'moodle/grade:edit', // Cannot edit grades.
        'moodle/role:assign', // Cannot assign roles.
        'moodle/role:override', // Cannot override roles.
        'moodle/course:activityvisibility', // Cannot hide/show activities.
        'moodle/course:sectionvisibility', // Cannot hide/show sections.
    ];

    foreach ($preventcaps as $cap) {
        if (get_capability_info($cap)) {
            assign_capability($cap, CAP_PREVENT, $roleid, $sysctx->id, true);
        }
    }

    return (int) $roleid;
}
/**
 * Assign the exam monitor role at the context of every course
 * that is a direct child of the given category.
 *
 * @param int $userid User ID.
 * @param int $categoryid Course category ID.
 * @param int $roleid Role ID.
 */
function local_quizmetamonitor_assign_role_to_all_courses_in_category(
    int $userid,
    int $categoryid,
    int $roleid
): void {
    global $DB;

    $courses = $DB->get_records_select(
        'course',
        'category = :cat AND id <> :siteid',
        ['cat' => $categoryid, 'siteid' => SITEID],
        '',
        'id'
    );

    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id);
        role_assign($roleid, $userid, $coursecontext->id);
    }
}

/**
 * Remove the exam monitor role from all course contexts in a category.
 *
 * @param int $userid User ID.
 * @param int $categoryid Course category ID.
 * @param int $roleid Role ID.
 */
function local_quizmetamonitor_unassign_role_from_all_courses_in_category(
    int $userid,
    int $categoryid,
    int $roleid
): void {
    global $DB;

    $courses = $DB->get_records_select(
        'course',
        'category = :cat AND id <> :siteid',
        ['cat' => $categoryid, 'siteid' => SITEID],
        '',
        'id'
    );

    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id);
        role_unassign($roleid, $userid, $coursecontext->id);
    }
}
// Render.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assignmonitors', 'local_quizmetamonitor'));

if ($errormsg) {
    echo $OUTPUT->notification($errormsg, 'error');
}
if ($successmsg) {
    echo $OUTPUT->notification($successmsg, 'success');
}

$categories = core_course_category::make_categories_list();

// Assignment form.
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

echo html_writer::end_div(); // Card-body.
echo html_writer::end_div(); // Card.

echo html_writer::end_tag('form');

// Current assignments table.
echo html_writer::tag(
    'h4',
    get_string('currentassignments', 'local_quizmetamonitor'),
    ['class' => 'mt-4 mb-3']
);

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
          FROM {local_quizmetamonitor_exammonitor} em
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
