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
 * Admin page: configure the scoring criteria, site-wide or per category.
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
    '/local/quizmetamonitor/pages/admin_criteria.php',
    ['categoryid' => $categoryid]
);
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('configurecriteria', 'local_quizmetamonitor'));
$PAGE->set_heading(get_string('configurecriteria', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('admin');

$availablecriteria = \local_quizmetamonitor\scoring_engine::get_available_criteria();
$categories        = core_course_category::make_categories_list();
$isdefault         = ($categoryid === 0);
$dbcategoryid      = $isdefault ? null : $categoryid;

// Handle save.
if (data_submitted() && confirm_sesskey()) {
    // Require a description.
    $description = trim(optional_param('change_description', '', PARAM_TEXT));
    if (empty($description)) {
        $saveerror = get_string('error_descriptionrequired', 'local_quizmetamonitor');
    } else {
        $now      = time();
        $snapshot = [];

        foreach ($availablecriteria as $key => $defaultlabel) {
            $points  = (float) optional_param('points_'  . $key, 1, PARAM_FLOAT);
            $enabled = (int)   optional_param('enabled_' . $key, 0, PARAM_INT);

            // Build snapshot of new state for history record.
            $snapshot[$key] = [
                'label'   => $defaultlabel,
                'points'  => $points,
                'enabled' => (bool) $enabled,
            ];

            if ($isdefault) {
                $existing = $DB->get_record(
                    'local_quizmetamonitor_crit_default',
                    ['criterion_key' => $key]
                );
                if ($existing) {
                    $existing->points       = $points;
                    $existing->enabled      = $enabled;
                    $existing->timemodified = $now;
                    $DB->update_record('local_quizmetamonitor_crit_default', $existing);
                } else {
                    $DB->insert_record('local_quizmetamonitor_crit_default', (object)[
                        'criterion_key'   => $key,
                        'criterion_label' => $defaultlabel,
                        'points'          => $points,
                        'enabled'         => $enabled,
                        'sortorder'       => 0,
                        'timecreated'     => $now,
                        'timemodified'    => $now,
                    ]);
                }
            } else {
                $existing = $DB->get_record('local_quizmetamonitor_crit_category', [
                    'categoryid'    => $categoryid,
                    'criterion_key' => $key,
                ]);
                if ($existing) {
                    $existing->points       = $points;
                    $existing->enabled      = $enabled;
                    $existing->timemodified = $now;
                    $DB->update_record('local_quizmetamonitor_crit_category', $existing);
                } else {
                    $DB->insert_record('local_quizmetamonitor_crit_category', (object)[
                        'categoryid'      => $categoryid,
                        'criterion_key'   => $key,
                        'criterion_label' => $defaultlabel,
                        'points'          => $points,
                        'enabled'         => $enabled,
                        'sortorder'       => 0,
                        'timecreated'     => $now,
                        'timemodified'    => $now,
                    ]);
                }
            }
        }

        // Save history record.
        \local_quizmetamonitor\config_history::save(
            \local_quizmetamonitor\config_history::TYPE_CRITERIA,
            $dbcategoryid,
            $USER->id,
            $description,
            $snapshot
        );

        redirect(
            $PAGE->url,
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Load current values.
$table      = $isdefault ? 'local_quizmetamonitor_crit_default' : 'local_quizmetamonitor_crit_category';
$conditions = $isdefault ? [] : ['categoryid' => $categoryid];
$existing   = $DB->get_records($table, $conditions, '', 'criterion_key, points, enabled');

// Load history.
$history = \local_quizmetamonitor\config_history::get_history(
    \local_quizmetamonitor\config_history::TYPE_CRITERIA,
    $dbcategoryid
);

// Render.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('configurecriteria', 'local_quizmetamonitor'));

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

// Show which scope we are editing.
if ($isdefault) {
    echo $OUTPUT->notification(
        get_string('editingdefaultcriteria', 'local_quizmetamonitor'),
        'info'
    );
} else {
    $catname = $DB->get_field('course_categories', 'name', ['id' => $categoryid]);
    echo $OUTPUT->notification(
        get_string('editingcategorycriteria', 'local_quizmetamonitor', format_string($catname)),
        'info'
    );
}

// Show save error if description was missing.
if (!empty($saveerror)) {
    echo $OUTPUT->notification($saveerror, 'error');
}

// Criteria edit form.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag(
    'h5',
    get_string('currentcriteria', 'local_quizmetamonitor'),
    ['class' => 'card-title']
);

echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::input_hidden_params($PAGE->url);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Criteria table.
$criteriatbl                = new html_table();
$criteriatbl->head          = [
    get_string('criterion', 'local_quizmetamonitor'),
    get_string('points', 'local_quizmetamonitor'),
    get_string('enabled', 'local_quizmetamonitor'),
];
$criteriatbl->attributes    = ['class' => 'generaltable table mb-3'];

foreach ($availablecriteria as $key => $label) {
    $ex      = $existing[$key] ?? null;
    $points  = $ex ? (float) $ex->points : 1;
    $enabled = $ex ? (int)   $ex->enabled : 1;

    $criteriatbl->data[] = [
        html_writer::tag('label', $label, ['for' => 'enabled_' . $key]),
        html_writer::empty_tag('input', [
            'type'  => 'number',
            'name'  => 'points_' . $key,
            'id'    => 'points_' . $key,
            'value' => $points,
            'step'  => '0.5',
            'min'   => '0',
            'class' => 'form-control form-control-sm',
            'style' => 'width:80px',
        ]),
        html_writer::empty_tag('input', array_filter([
            'type'    => 'checkbox',
            'name'    => 'enabled_' . $key,
            'id'      => 'enabled_' . $key,
            'value'   => '1',
            'checked' => $enabled ? 'checked' : null,
            'class'   => 'form-check-input',
        ])),
    ];
}

echo html_writer::table($criteriatbl);

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
    \local_quizmetamonitor\config_history::TYPE_CRITERIA
);

echo $OUTPUT->footer();
