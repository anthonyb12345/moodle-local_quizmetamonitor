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
 * Admin page: score existing quizzes (backfill) or rescore all quizzes.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$systemcontext = context_system::instance();
require_capability('local/quizmetamonitor:configure', $systemcontext);

global $DB;

$PAGE->set_url('/local/quizmetamonitor/pages/admin_backfill.php');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('backfill', 'local_quizmetamonitor'));
$PAGE->set_heading(get_string('backfill', 'local_quizmetamonitor'));
$PAGE->set_pagelayout('admin');

$confirmed = optional_param('confirm', 0, PARAM_INT);
$results   = [];
$errors    = [];

// Run backfill when confirmed.
if ($confirmed && confirm_sesskey()) {
    // Get all quiz ids that do NOT yet have a score record.
    $sql = "SELECT q.id
              FROM {quiz} q
         LEFT JOIN {local_quizmetamonitor_quiz_score} qs ON qs.quizid = q.id
             WHERE qs.id IS NULL";

    $unscored = $DB->get_fieldset_sql($sql);

    // Also optionally rescore ALL quizzes.
    $rescore = optional_param('rescore_all', 0, PARAM_INT);
    if ($rescore) {
        $unscored = $DB->get_fieldset_select('quiz', 'id', '1 = 1');
    }

    $total    = count($unscored);
    $success  = 0;
    $failed   = 0;

    foreach ($unscored as $quizid) {
        try {
            $result = \local_quizmetamonitor\scoring_engine::compute_score((int) $quizid);

            // Save score using userid = 0 (system/backfill) and skip notifications.
            \local_quizmetamonitor\scoring_engine::save_score($result, 0);

            $quizname  = $DB->get_field('quiz', 'name', ['id' => $quizid]);
            $results[] = [
                'quizid' => $quizid,
                'name'   => $quizname,
                'score'  => $result['score_percent'],
                'status' => 'ok',
            ];
            $success++;
        } catch (\Throwable $e) {
            $quizname  = $DB->get_field('quiz', 'name', ['id' => $quizid])
                ?: get_string('backfill_unknownquiz', 'local_quizmetamonitor');
            $results[] = [
                'quizid' => $quizid,
                'name'   => $quizname,
                'score'  => null,
                'status' => 'error',
                'error'  => $e->getMessage(),
            ];
            $failed++;
        }
    }

    // Show results.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('backfill', 'local_quizmetamonitor'));

    echo $OUTPUT->notification(
        get_string(
            'backfill_complete',
            'local_quizmetamonitor',
            ['total' => $total, 'success' => $success, 'failed' => $failed]
        ),
        $failed > 0 ? 'warning' : 'success'
    );

    if (!empty($results)) {
        $table = new html_table();
        $table->head = [
            get_string('quizname', 'local_quizmetamonitor'),
            get_string('score', 'local_quizmetamonitor'),
            get_string('status', 'local_quizmetamonitor'),
        ];
        $table->attributes['class'] = 'generaltable table table-sm';

        foreach ($results as $r) {
            if ($r['status'] === 'ok') {
                $statuscell = html_writer::tag(
                    'span',
                    get_string('backfill_scored', 'local_quizmetamonitor'),
                    ['class' => 'badge badge-success']
                );
                $scorecell  = $r['score'] . '%';
            } else {
                $statuscell = html_writer::tag(
                    'span',
                    get_string('backfill_error', 'local_quizmetamonitor', s($r['error'])),
                    ['class' => 'badge badge-danger']
                );
                $scorecell  = '—';
            }

            $table->data[] = [
                format_string($r['name']),
                $scorecell,
                $statuscell,
            ];
        }

        echo html_writer::table($table);
    }

    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/quizmetamonitor/pages/admin_overview.php'),
            '← ' . get_string('adminoverview', 'local_quizmetamonitor'),
            ['class' => 'btn btn-primary']
        ),
        'mt-4'
    );

    echo $OUTPUT->footer();
    exit;
}

// Confirmation page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('backfill', 'local_quizmetamonitor'));

// Show counts so admin knows what they are about to do.
$totalquizzes = $DB->count_records('quiz');
$alreadyscored = $DB->count_records('local_quizmetamonitor_quiz_score');
$unscored      = $totalquizzes - $alreadyscored;

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag(
    'p',
    get_string('backfill_description', 'local_quizmetamonitor'),
    ['class' => 'card-text']
);

echo html_writer::start_tag('ul');
echo html_writer::tag(
    'li',
    get_string('totalquizzesindb', 'local_quizmetamonitor') . ': <strong>' . $totalquizzes . '</strong>'
);
echo html_writer::tag(
    'li',
    get_string('alreadyscored', 'local_quizmetamonitor') . ': <strong>' . $alreadyscored . '</strong>'
);
echo html_writer::tag(
    'li',
    get_string('unscoredquizzes', 'local_quizmetamonitor') . ': <strong>' . $unscored . '</strong>'
);
echo html_writer::end_tag('ul');

echo $OUTPUT->notification(
    get_string('backfill_nomail', 'local_quizmetamonitor'),
    'info'
);

echo html_writer::end_div();
echo html_writer::end_div();

// Backfill only new (unscored) quizzes.
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rescore_all', 'value' => '0']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('backfill_newonly', 'local_quizmetamonitor', $unscored),
    'class' => 'btn btn-primary mr-2',
]);
echo html_writer::end_tag('form');

// Rescore ALL quizzes.
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'mt-2']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rescore_all', 'value' => '1']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('backfill_rescore_all', 'local_quizmetamonitor', $totalquizzes),
    'class' => 'btn btn-outline-warning mr-2',
]);
echo html_writer::end_tag('form');

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/quizmetamonitor/pages/admin_overview.php'),
        '← ' . get_string('back'),
        ['class' => 'btn btn-outline-secondary mt-3']
    )
);

echo $OUTPUT->footer();
