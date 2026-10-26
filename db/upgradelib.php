<?php
defined('MOODLE_INTERNAL') || die();

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
            . "\n" . '<p>The security settings of the quiz <strong>{quiz_name}</strong> in <strong>{course_name}</strong> were changed.</p>'
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
 * Replace the "teacher_score_changed" template with the new default, but only if an admin
 * has not customised it (i.e. it still matches the original install text).
 *
 * @return bool true if the template was updated
 */
function local_quizmetamonitor_update_settings_changed_template(): bool {
    global $DB;

    // Original default seeded by install.php up to 2024010105.
    $original = [
        'subject'    => 'Quiz security score changed: {quiz_name}',
        'body_html'  => '<p>Hello,</p>
<p>The quiz <strong>{quiz_name}</strong> in <strong>{course_name}</strong> was recently edited.</p>
<p>Previous score: {previous_score}% &rarr; New score: <strong>{score_percent}%</strong> (threshold: {threshold_percent}%)</p>
<h3>Criteria breakdown:</h3>
{criteria_breakdown}',
        'body_plain' => "Hello,\n\nThe quiz \"{quiz_name}\" in \"{course_name}\" was recently edited.\n\nPrevious score: {previous_score}% -> New score: {score_percent}% (threshold: {threshold_percent}%)\n\nCriteria breakdown:\n{criteria_breakdown}",
    ];

    $record = $DB->get_record('local_quizmeta_email_tmpl', ['template_key' => 'teacher_score_changed']);
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
    $DB->update_record('local_quizmeta_email_tmpl', $record);

    return true;
}
