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
 * Upgrade helpers for local_quizmetamonitor: default email templates and the steps that add or update them.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Default "teacher_score_changed" template (since 2024010106).
 *
 * Works whether or not the score moved: {score_change} reads "71.43% → 85.71%" or
 * "85.71% (unchanged)", and {criteria_changes} lists only the criteria that changed.
 *
 * @return array ['subject' => string, 'body_html' => string, 'body_plain' => string]
 */
function local_quizmetamonitor_default_settings_changed_template(): array {
    return [
        'subject'    => 'Quiz security settings updated: {quiz_name}',
        'body_html'  => '<p>Hello,</p>'
            . "\n" . '<p>The security settings of the quiz <strong>{quiz_name}</strong> in'
            . ' <strong>{course_name}</strong> were changed.</p>'
            . "\n" . '<p>Security score: <strong>{score_change}</strong> (threshold: {threshold_percent}%)</p>'
            . "\n" . '<h3>What changed:</h3>'
            . "\n" . '{criteria_changes}'
            . "\n" . '<h3>Full criteria breakdown:</h3>'
            . "\n" . '{criteria_breakdown}',
        'body_plain' => "Hello,\n\nThe security settings of the quiz \"{quiz_name}\" in \"{course_name}\" were changed.\n\n"
            . "Security score: {score_change} (threshold: {threshold_percent}%)\n\n"
            . "What changed:\n{criteria_changes}\n\n"
            . "Full criteria breakdown:\n{criteria_breakdown}",
    ];
}

/**
 * Default "teacher_quizzes_restored" template (since 2024010109): one email per restored quiz,
 * with the same criteria table as the "quiz created" email.
 *
 * @return array ['subject' => string, 'body_html' => string, 'body_plain' => string]
 */
function local_quizmetamonitor_default_restored_template(): array {
    return [
        'subject'    => 'Restored quiz: {quiz_name}',
        'body_html'  => '<p>Hello,</p>'
            . "\n" . '<p>The quiz <strong>{quiz_name}</strong> has been restored or imported into the course'
            . ' <strong>{course_name}</strong>. Restored quizzes keep the settings from the backup'
            . ' (dates, password, Safe Exam Browser, etc.), so please review them.</p>'
            . "\n" . '<p>Security score: <strong>{score_percent}%</strong> (threshold: {threshold_percent}%)</p>'
            . "\n" . '<h3>Criteria breakdown:</h3>'
            . "\n" . '{criteria_breakdown}'
            . "\n" . '<p>Please review the quiz settings to ensure compliance.</p>',
        'body_plain' => "Hello,\n\nThe quiz \"{quiz_name}\" has been restored or imported into the course \"{course_name}\". "
            . "Restored quizzes keep the settings from the backup (dates, password, Safe Exam Browser, etc.), "
            . "so please review them.\n\n"
            . "Security score: {score_percent}% (threshold: {threshold_percent}%)\n\n"
            . "Criteria breakdown:\n{criteria_breakdown}\n\n"
            . "Please review the quiz settings to ensure compliance.",
    ];
}

/**
 * Switch the "teacher_quizzes_restored" template from the 2024010107 summary format to the
 * per-quiz format, unless an admin has customised it. Adds it if it is missing.
 *
 * @param string $table Email template table (upgrade steps before 2026102900 pass the old table name).
 * @return bool true if the template was added or updated
 */
function local_quizmetamonitor_update_restored_template(string $table = 'local_quizmetamonitor_email_tmpl'): bool {
    global $DB;

    $record = $DB->get_record($table, ['template_key' => 'teacher_quizzes_restored']);
    if (!$record) {
        local_quizmetamonitor_add_restored_template($table);
        return true;
    }

    // Summary-format default seeded by 2024010107/2024010108.
    $original = [
        'subject'    => 'Restored quizzes to review: {course_name}',
        'body_html'  => '<p>Hello,</p>'
            . "\n" . '<p>{quiz_count} quiz(zes) were restored or imported into the course <strong>{course_name}</strong>.'
            . ' Restored quizzes keep the settings from the backup (dates, password, Safe Exam Browser, etc.),'
            . ' so please review them.</p>'
            . "\n" . '{quiz_list}'
            . "\n" . '<p>Please review the quiz settings to ensure compliance.</p>',
        'body_plain' => "Hello,\n\n{quiz_count} quiz(zes) were restored or imported into the course \"{course_name}\". "
            . "Restored quizzes keep the settings from the backup (dates, password, Safe Exam Browser, etc.), "
            . "so please review them.\n\n{quiz_list}\n\nPlease review the quiz settings to ensure compliance.",
    ];

    $normalise = fn(string $s): string => preg_replace('/\s+/', '', $s);
    foreach ($original as $field => $text) {
        if ($normalise((string) $record->$field) !== $normalise($text)) {
            return false; // Customised by an admin; leave it alone.
        }
    }

    foreach (local_quizmetamonitor_default_restored_template() as $field => $text) {
        $record->$field = $text;
    }
    $record->timemodified = time();
    $DB->update_record($table, $record);

    return true;
}

/**
 * Default "teacher_below_threshold" template (since 2024010110): alert to the users who created
 * or edited a quiz that opens within 24 hours and is below the security threshold.
 *
 * @return array ['subject' => string, 'body_html' => string, 'body_plain' => string]
 */
function local_quizmetamonitor_default_teacher_alert_template(): array {
    return [
        'subject'    => 'ALERT: Your quiz is below the security threshold — {quiz_name}',
        'body_html'  => '<p>Hello,</p>'
            . "\n" . '<p>Your quiz <strong>{quiz_name}</strong> in the course <strong>{course_name}</strong>'
            . ' opens within 24 hours and its security score is below the required threshold.</p>'
            . "\n" . '<p>Score: <strong>{score_percent}%</strong> | Threshold: <strong>{threshold_percent}%</strong></p>'
            . "\n" . '<h3>Criteria breakdown:</h3>'
            . "\n" . '{criteria_breakdown}'
            . "\n" . '<p>Please review the quiz settings before the quiz opens.</p>',
        'body_plain' => "Hello,\n\nYour quiz \"{quiz_name}\" in the course \"{course_name}\" opens within 24 hours "
            . "and its security score is below the required threshold.\n\n"
            . "Score: {score_percent}% | Threshold: {threshold_percent}%\n\n"
            . "Criteria breakdown:\n{criteria_breakdown}\n\n"
            . "Please review the quiz settings before the quiz opens.",
    ];
}

/**
 * Add the "teacher_below_threshold" template if it does not exist yet.
 *
 * @param string $table Email template table (upgrade steps before 2026102900 pass the old table name).
 */
function local_quizmetamonitor_add_teacher_alert_template(string $table = 'local_quizmetamonitor_email_tmpl'): void {
    global $DB;

    if ($DB->record_exists($table, ['template_key' => 'teacher_below_threshold'])) {
        return;
    }
    $now      = time();
    $template = local_quizmetamonitor_default_teacher_alert_template();
    $DB->insert_record($table, (object) [
        'template_key' => 'teacher_below_threshold',
        'subject'      => $template['subject'],
        'body_html'    => $template['body_html'],
        'body_plain'   => $template['body_plain'],
        'timecreated'  => $now,
        'timemodified' => $now,
    ]);
}

/**
 * Add the "teacher_quizzes_restored" template if it does not exist yet.
 *
 * @param string $table Email template table (upgrade steps before 2026102900 pass the old table name).
 */
function local_quizmetamonitor_add_restored_template(string $table = 'local_quizmetamonitor_email_tmpl'): void {
    global $DB;

    if ($DB->record_exists($table, ['template_key' => 'teacher_quizzes_restored'])) {
        return;
    }
    $now      = time();
    $template = local_quizmetamonitor_default_restored_template();
    $DB->insert_record($table, (object) [
        'template_key' => 'teacher_quizzes_restored',
        'subject'      => $template['subject'],
        'body_html'    => $template['body_html'],
        'body_plain'   => $template['body_plain'],
        'timecreated'  => $now,
        'timemodified' => $now,
    ]);
}

/**
 * Replace the "teacher_score_changed" template with the new default, but only if an admin
 * has not customised it (i.e. it still matches the original install text).
 *
 * @param string $table Email template table (upgrade steps before 2026102900 pass the old table name).
 * @return bool true if the template was updated
 */
function local_quizmetamonitor_update_settings_changed_template(string $table = 'local_quizmetamonitor_email_tmpl'): bool {
    global $DB;

    // Original default seeded by install.php up to 2024010105.
    $original = [
        'subject'    => 'Quiz security score changed: {quiz_name}',
        'body_html'  => '<p>Hello,</p>
<p>The quiz <strong>{quiz_name}</strong> in <strong>{course_name}</strong> was recently edited.</p>
<p>Previous score: {previous_score}% &rarr; New score: <strong>{score_percent}%</strong> (threshold: {threshold_percent}%)</p>
<h3>Criteria breakdown:</h3>
{criteria_breakdown}',
        'body_plain' => "Hello,\n\nThe quiz \"{quiz_name}\" in \"{course_name}\" was recently edited.\n\n"
            . "Previous score: {previous_score}% -> New score: {score_percent}% (threshold: {threshold_percent}%)\n\n"
            . "Criteria breakdown:\n{criteria_breakdown}",
    ];

    $record = $DB->get_record($table, ['template_key' => 'teacher_score_changed']);
    if (!$record) {
        return false;
    }

    // Compare ignoring whitespace/line endings (the seeded text may have CRLF from the source file).
    $normalise = fn(string $s): string => preg_replace('/\s+/', '', $s);
    foreach ($original as $field => $text) {
        if ($normalise((string) $record->$field) !== $normalise($text)) {
            return false; // Customised by an admin; leave it alone.
        }
    }

    foreach (local_quizmetamonitor_default_settings_changed_template() as $field => $text) {
        $record->$field = $text;
    }
    $record->timemodified = time();
    $DB->update_record($table, $record);

    return true;
}
