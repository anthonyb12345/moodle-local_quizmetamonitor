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
 * Admin page: configure the alert threshold, site-wide or per category.
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

$categoryid = optional_param('categoryid', 0, PARAM_INT);

$PAGE->set_url(
    '/local/quizmetamonitor/pages/admin_threshold.php',
    ['categoryid' => $categoryid]
);
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('configurethreshold', 'local_quizmetamonitor'));
$PAGE->set_heading(get_string('configurethreshold', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('admin');

$categories   = core_course_category::make_categories_list();
$isdefault    = ($categoryid === 0);
$dbcategoryid = $isdefault ? null : $categoryid;

// Handle save.
if (data_submitted() && confirm_sesskey()) {
    $description = trim(optional_param('change_description', '', PARAM_TEXT));

    if (empty($description)) {
        $saveerror = get_string('error_descriptionrequired', 'local_quizmetamonitor');
    } else {
        $threshold = (float) optional_param('threshold', 70, PARAM_FLOAT);

        // Clamp to 0-100.
        $threshold = max(0, min(100, $threshold));

        $now      = time();
        $existing = $DB->get_record('local_quizmetamonitor_threshold', ['categoryid' => $dbcategoryid]);

        if ($existing) {
            $existing->threshold_percent = $threshold;
            $existing->timemodified      = $now;
            $DB->update_record('local_quizmetamonitor_threshold', $existing);
        } else {
            $DB->insert_record('local_quizmetamonitor_threshold', (object)[
                'categoryid'        => $dbcategoryid,
                'threshold_percent' => $threshold,
                'timecreated'       => $now,
                'timemodified'      => $now,
            ]);
        }

        // Save history record.
        \local_quizmetamonitor\config_history::save(
            \local_quizmetamonitor\config_history::TYPE_THRESHOLD,
            $dbcategoryid,
            $USER->id,
            $description,
            ['threshold_percent' => $threshold]
        );

        redirect(
            $PAGE->url,
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Load current value.
$existing = $DB->get_record('local_quizmetamonitor_threshold', ['categoryid' => $dbcategoryid]);
$current  = $existing ? (float) $existing->threshold_percent : 70.0;

// If category has no override, show what fallback applies.
$fallbackmsg = '';
if (!$isdefault && !$existing) {
    $fallback    = $DB->get_record('local_quizmetamonitor_threshold', ['categoryid' => null]);
    $fallbackmsg = get_string(
        'nothresholdoverride',
        'local_quizmetamonitor',
        $fallback ? (float) $fallback->threshold_percent : 70.0
    );
}

// Load history.
$history = \local_quizmetamonitor\config_history::get_history(
    \local_quizmetamonitor\config_history::TYPE_THRESHOLD,
    $dbcategoryid
);

// Render.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('configurethreshold', 'local_quizmetamonitor'));

// Category selector.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url->out_omit_querystring(),
]);
echo html_writer::tag(
    'label',
    get_string('selectcategorylabel', 'local_quizmetamonitor'),
    ['class' => 'font-weight-bold mr-2']
);
echo html_writer::select(
    [0 => get_string('sitedefault', 'local_quizmetamonitor')] + $categories,
    'categoryid',
    $categoryid,
    false,
    ['onchange' => 'this.form.submit()', 'class' => 'custom-select mb-3', 'style' => 'width:auto']
);
echo html_writer::end_tag('form');

if ($fallbackmsg) {
    echo $OUTPUT->notification($fallbackmsg, 'info');
}

if (!empty($saveerror)) {
    echo $OUTPUT->notification($saveerror, 'error');
}

// Threshold edit form.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag(
    'h5',
    get_string('currentthreshold', 'local_quizmetamonitor'),
    ['class' => 'card-title']
);

echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::input_hidden_params($PAGE->url);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Threshold input.
echo html_writer::start_div('form-group');
echo html_writer::tag(
    'label',
    get_string('thresholdpercent', 'local_quizmetamonitor'),
    ['for' => 'qmm_threshold', 'class' => 'font-weight-bold']
);

// Visual indicator of current threshold.
echo html_writer::start_div('d-flex align-items-center mb-2');
echo html_writer::empty_tag('input', [
    'type'  => 'number',
    'id'    => 'qmm_threshold',
    'name'  => 'threshold',
    'value' => $current,
    'min'   => '0',
    'max'   => '100',
    'step'  => '0.5',
    'class' => 'form-control mr-3',
    'style' => 'width:120px',
]);
echo html_writer::tag(
    'span',
    get_string('thresholdexplain', 'local_quizmetamonitor'),
    ['class' => 'text-muted small']
);
echo html_writer::end_div();
echo html_writer::end_div();

// Change description (required).
echo html_writer::start_div('form-group');
echo html_writer::tag(
    'label',
    get_string('changereason', 'local_quizmetamonitor')
    . ' ' . html_writer::tag('span', '*', ['class' => 'text-danger']),
    ['for' => 'change_description', 'class' => 'font-weight-bold']
);
echo html_writer::tag(
    'textarea',
    isset($saveerror) ? htmlspecialchars(
        optional_param('change_description', '', PARAM_TEXT),
        ENT_QUOTES
    ) : '',
    [
        'id'          => 'change_description',
        'name'        => 'change_description',
        'rows'        => '3',
        'class'       => 'form-control',
        'placeholder' => get_string('changereason_placeholder', 'local_quizmetamonitor'),
        'required'    => 'required',
    ]
);
echo html_writer::tag(
    'small',
    get_string('changereason_help', 'local_quizmetamonitor'),
    ['class' => 'form-text text-muted']
);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary mt-2',
]);

echo html_writer::end_tag('form');
echo html_writer::end_div(); // Card-body.
echo html_writer::end_div(); // Card.

// Change history.
echo html_writer::tag(
    'h4',
    get_string('changehistory', 'local_quizmetamonitor'),
    ['class' => 'mt-4 mb-3']
);

echo \local_quizmetamonitor\config_history::render_history_table(
    $history,
    \local_quizmetamonitor\config_history::TYPE_THRESHOLD
);

echo $OUTPUT->footer();
