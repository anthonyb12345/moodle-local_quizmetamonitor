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
 * Install steps for local_quizmetamonitor.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/upgradelib.php');

/**
 * Seed the default threshold, scoring criteria and email templates on install.
 */
function xmldb_local_quizmetamonitor_install(): void {
    global $DB;

    $now = time();

    // Quizzes created before this point are not resynced nightly.
    set_config('trackfrom', $now, 'local_quizmetamonitor');

    // 1. Seed default threshold (70 %).
    if (!$DB->record_exists('local_quizmetamonitor_threshold', ['categoryid' => null])) {
        $DB->insert_record('local_quizmetamonitor_threshold', (object)[
            'categoryid'        => null,
            'threshold_percent' => 70.00,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
    }

    // 2. Seed default scoring criteria.
    $criteria = [
        ['open_date_set', 'Open date set', 1],
        ['close_date_set', 'Close date set', 1],
        ['allowed_attempts_set', 'Allowed attempts limited', 1],
        ['password_set', 'Password set', 1],
        ['ip_restriction_set', 'Restriction by IP address set', 1],
        ['block_concurrent_set', 'Block Concurrent Connections set', 1],
        ['seb_enabled', 'Safe Exam Browser enabled', 1],
    ];

    foreach ($criteria as $i => [$key, $label, $points]) {
        if (!$DB->record_exists('local_quizmetamonitor_crit_default', ['criterion_key' => $key])) {
            $DB->insert_record('local_quizmetamonitor_crit_default', (object)[
                'criterion_key'   => $key,
                'criterion_label' => $label,
                'points'          => $points,
                'enabled'         => 1,
                'sortorder'       => $i,
                'timecreated'     => $now,
                'timemodified'    => $now,
            ]);
        }
    }

    // 3. Seed default email templates.
    $templates = [
        [
            'key'   => 'teacher_created',
            'subj'  => 'New quiz created: {quiz_name}',
            'html'  => '<p>Hello,</p>
<p>A new quiz <strong>{quiz_name}</strong> has been created in the course <strong>{course_name}</strong>.</p>
<p>Security score: <strong>{score_percent}%</strong> (threshold: {threshold_percent}%)</p>
<h3>Criteria breakdown:</h3>
{criteria_breakdown}
<p>Please review the quiz settings to ensure compliance.</p>',
            'plain' => "Hello,\n\nA new quiz \"{quiz_name}\" has been created in the course \"{course_name}\".\n\n"
                . "Security score: {score_percent}% (threshold: {threshold_percent}%)\n\n"
                . "Criteria breakdown:\n{criteria_breakdown}\n\n"
                . "Please review the quiz settings to ensure compliance.",
        ],
        [
            'key'   => 'teacher_score_changed',
            'subj'  => local_quizmetamonitor_default_settings_changed_template()['subject'],
            'html'  => local_quizmetamonitor_default_settings_changed_template()['body_html'],
            'plain' => local_quizmetamonitor_default_settings_changed_template()['body_plain'],
        ],
        [
            'key'   => 'exammonitor_below_threshold',
            'subj'  => 'ALERT: Quiz below security threshold — {quiz_name}',
            'html'  => '<p>Hello,</p>
<p>The quiz <strong>{quiz_name}</strong> in course <strong>{course_name}</strong>
(category: <strong>{category_name}</strong>) is starting within 24 hours and its
security score is below the required threshold.</p>
<p>Score: <strong>{score_percent}%</strong> | Threshold: <strong>{threshold_percent}%</strong></p>
<h3>Criteria breakdown:</h3>
{criteria_breakdown}
<p>Please contact the course teacher to resolve this before the quiz opens.</p>',
            'plain' => "Hello,\n\nThe quiz \"{quiz_name}\" in course \"{course_name}\" (category: {category_name}) "
                . "is starting within 24 hours and its security score is below the required threshold.\n\n"
                . "Score: {score_percent}% | Threshold: {threshold_percent}%\n\n"
                . "Criteria breakdown:\n{criteria_breakdown}\n\n"
                . "Please contact the course teacher to resolve this before the quiz opens.",
        ],
    ];

    foreach ($templates as $t) {
        if (!$DB->record_exists('local_quizmetamonitor_email_tmpl', ['template_key' => $t['key']])) {
            $DB->insert_record('local_quizmetamonitor_email_tmpl', (object)[
                'template_key' => $t['key'],
                'subject'      => $t['subj'],
                'body_html'    => $t['html'],
                'body_plain'   => $t['plain'],
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }

    // Email for quizzes restored/imported into a course.
    local_quizmetamonitor_add_restored_template();

    // Below-threshold alert to the users who created/edited the quiz.
    local_quizmetamonitor_add_teacher_alert_template();
}
