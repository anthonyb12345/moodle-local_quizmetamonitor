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

namespace local_quizmetamonitor;

/**
 * Builds and sends the plugin's notification emails (teacher emails and below-threshold alerts).
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_manager {
    /**
     * @var string Template key and log type: quiz created email to the teacher.
     */
    public const TYPE_TEACHER_CREATED       = 'teacher_created';
    /**
     * @var string Template key and log type: security settings changed email to the teacher.
     */
    public const TYPE_TEACHER_SCORE_CHANGED = 'teacher_score_changed';
    /**
     * @var string Template key and log type: below-threshold alert to Exam Monitors.
     */
    public const TYPE_EXAMMONITOR_ALERT     = 'exammonitor_below_threshold';
    /**
     * @var string Template key and log type: restored quiz email to the teacher.
     */
    public const TYPE_TEACHER_RESTORED      = 'teacher_quizzes_restored';
    /**
     * @var string Template key and log type: below-threshold alert to the quiz's creators and editors.
     */
    public const TYPE_TEACHER_ALERT         = 'teacher_below_threshold';

    // Public senders.

    /**
     * Email the teacher who restored/imported a quiz, with the same criteria table as the
     * "quiz created" email. Called once per restored quiz.
     *
     * @param array $scoreresult Score data of the quiz.
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $course Course record.
     * @param int $actorid ID of the user who made the change.
     */
    public static function notify_teacher_quiz_restored(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        int $actorid
    ): void {
        self::send_to_users(
            self::TYPE_TEACHER_RESTORED,
            self::get_actor_recipients($actorid, (int) $course->id),
            $scoreresult,
            $quiz,
            $course,
            null,
            ''
        );
    }

    /**
     * Email the teacher who created a quiz its security score.
     *
     * @param array $scoreresult Result of scoring_engine::compute_score().
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $course Course record.
     * @param int $actorid ID of the user who created the quiz.
     */
    public static function notify_teachers_created(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        int $actorid
    ): void {
        $userids = self::get_actor_recipients($actorid, (int) $course->id);

        self::send_to_users(
            self::TYPE_TEACHER_CREATED,
            $userids,
            $scoreresult,
            $quiz,
            $course,
            null,
            ''
        );
    }

    /**
     * Email the teacher who edited a quiz when its security settings changed.
     *
     * @param array $scoreresult Result of scoring_engine::compute_score().
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $course Course record.
     * @param float|null $previousscore Score before the change.
     * @param int $actorid ID of the user who edited the quiz.
     * @param array $previousbreakdown Criteria breakdown before the change.
     */
    public static function notify_teachers_score_changed(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        ?float $previousscore,
        int $actorid,
        array $previousbreakdown = []
    ): void {
        $userids = self::get_actor_recipients($actorid, (int) $course->id);

        self::send_to_users(
            self::TYPE_TEACHER_SCORE_CHANGED,
            $userids,
            $scoreresult,
            $quiz,
            $course,
            $previousscore,
            '',
            $previousbreakdown
        );
    }

    /**
     * Alert the category's Exam Monitors that a quiz opening soon is below the threshold.
     *
     * Each monitor is alerted once per quiz.
     *
     * @param array $scoreresult Score data of the quiz.
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $course Course record.
     * @param string $categoryname Name of the course category.
     */
    public static function notify_exam_monitors_alert(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        string $categoryname
    ): void {
        global $DB;

        $userids = category_util::get_monitors_for_category((int) $scoreresult['categoryid']);

        // Remove monitors who already received an alert for this quiz.
        $already = $DB->get_fieldset_select(
            'local_quizmeta_notif_log',
            'userid',
            'quizid = :qid AND notification_type = :type',
            ['qid' => $scoreresult['quizid'], 'type' => self::TYPE_EXAMMONITOR_ALERT]
        );
        $userids = array_values(array_diff($userids, array_map('intval', $already)));

        if (empty($userids)) {
            return;
        }

        self::send_to_users(
            self::TYPE_EXAMMONITOR_ALERT,
            $userids,
            $scoreresult,
            $quiz,
            $course,
            null,
            $categoryname
        );
    }

    /**
     * Below-threshold alert (quiz opens within 24 hours) to everyone who created, edited or
     * restored the quiz, whatever their role. Each person is alerted once per quiz.
     *
     * @param array $scoreresult Score data of the quiz.
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $course Course record.
     */
    public static function notify_teachers_below_threshold(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course
    ): void {
        global $DB;

        // Creators/editors/restorers are recorded in the score history (0 = system/backfill).
        $sql = "SELECT DISTINCT h.changed_by_userid
                  FROM {local_quizmeta_score_history} h
                  JOIN {user} u ON u.id = h.changed_by_userid
                 WHERE h.quizid = :quizid
                   AND h.changed_by_userid > 0
                   AND u.deleted = 0
                   AND u.suspended = 0";
        $userids = array_map('intval', $DB->get_fieldset_sql($sql, ['quizid' => $scoreresult['quizid']]));

        // Remove users who already received this alert for this quiz.
        $already = $DB->get_fieldset_select(
            'local_quizmeta_notif_log',
            'userid',
            'quizid = :qid AND notification_type = :type',
            ['qid' => $scoreresult['quizid'], 'type' => self::TYPE_TEACHER_ALERT]
        );
        $userids = array_values(array_diff($userids, array_map('intval', $already)));

        if (empty($userids)) {
            return;
        }

        self::send_to_users(
            self::TYPE_TEACHER_ALERT,
            $userids,
            $scoreresult,
            $quiz,
            $course,
            null,
            ''
        );
    }

    // Recipient helpers.

    /**
     * Only the user who created/edited the quiz is notified, and only if
     * they are a teacher or non-editing teacher in the course.
     *
     * @param int $actorid ID of the user who made the change.
     * @param int $courseid Course ID.
     * @return int[]
     */
    private static function get_actor_recipients(int $actorid, int $courseid): array {
        if ($actorid <= 0) {
            return [];
        }

        $teachers = category_util::get_teachers_for_course($courseid);
        return in_array($actorid, $teachers, true) ? [$actorid] : [];
    }

    // Core dispatch.

    /**
     * Build one email per recipient from the template for $type, send it and log it.
     *
     * @param string $type Template key / notification type.
     * @param int[] $userids Recipients.
     * @param array $scoreresult Score data of the quiz.
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $course Course record.
     * @param float|null $previousscore Previous score, for the settings changed email.
     * @param string $categoryname Name of the course category, for the Exam Monitor alert.
     * @param array $previousbreakdown Previous criteria breakdown, for the settings changed email.
     */
    private static function send_to_users(
        string $type,
        array $userids,
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        ?float $previousscore,
        string $categoryname,
        array $previousbreakdown = []
    ): void {
        global $DB;

        $tmpl = self::get_template($type);

        $tokens = [
            'quiz_name'          => $quiz->name,
            'course_name'        => $course->fullname,
            'category_name'      => $categoryname,
            'score_percent'      => $scoreresult['score_percent'],
            'previous_score'     => $previousscore !== null ? (string) $previousscore : 'N/A',
            'threshold_percent'  => $scoreresult['threshold_applied'],
        ];

        $subject = self::apply_placeholders($tmpl->subject, $tokens);

        $noreply = \core_user::get_noreply_user();
        $now     = time();

        foreach ($userids as $userid) {
            $user = \core_user::get_user($userid);

            // The breakdown, change list and score summary contain translatable text
            // (e.g. SEB choice), so build them in the recipient's own language.
            $previouslang = force_current_language($user->lang ?? '');
            $changes      = self::get_criteria_changes($previousbreakdown, $scoreresult['breakdown']);
            $scorechange  = self::build_score_change($previousscore, (float) $scoreresult['score_percent']);

            // Teachers who chose not to use SEB get a reminder right after the criteria table.
            $sebnotice = '';
            if ($type !== self::TYPE_EXAMMONITOR_ALERT && self::seb_not_used($scoreresult['breakdown'])) {
                $sebnotice = get_string('seb_no_notice', 'local_quizmetamonitor');
            }

            $bodyplain = self::apply_placeholders($tmpl->body_plain, $tokens + [
                'criteria_breakdown' => self::build_breakdown_plain($scoreresult['breakdown'])
                    . ($sebnotice !== '' ? "\n\n" . strip_tags($sebnotice) : ''),
                'criteria_changes'   => self::build_changes_plain($changes),
                'score_change'       => $scorechange,
            ]);
            $bodyhtml  = self::apply_placeholders($tmpl->body_html, $tokens + [
                'criteria_breakdown' => self::build_breakdown_html($scoreresult['breakdown'])
                    . ($sebnotice !== '' ? '<p>' . $sebnotice . '</p>' : ''),
                'criteria_changes'   => self::build_changes_html($changes),
                'score_change'       => htmlspecialchars($scorechange, ENT_QUOTES),
            ]);
            force_current_language($previouslang);

            $result = email_to_user($user, $noreply, $subject, $bodyplain, $bodyhtml);

            // Log regardless of email result so we have an audit trail.
            $log                    = new \stdClass();
            $log->quizid            = $scoreresult['quizid'];
            $log->userid            = $userid;
            $log->notification_type = $type;
            $log->score_percent     = $scoreresult['score_percent'];
            $log->timesent          = $now;
            $DB->insert_record('local_quizmeta_notif_log', $log);
        }
    }

    // Template helpers.

    /**
     * Load an email template, falling back to a minimal built-in one.
     *
     * @param string $key Template key.
     * @return \stdClass Record with subject, body_html and body_plain.
     */
    private static function get_template(string $key): \stdClass {
        global $DB;

        $tmpl = $DB->get_record('local_quizmeta_email_tmpl', ['template_key' => $key]);
        if ($tmpl) {
            return $tmpl;
        }

        return (object)[
            'subject'    => '[Quiz Monitor] ' . $key . ' — {quiz_name}',
            'body_html'  => '<p>Quiz: {quiz_name}</p><p>Course: {course_name}</p>'
                          . '<p>Score: {score_percent}%</p><p>{criteria_breakdown}</p>',
            'body_plain' => "Quiz: {quiz_name}\nCourse: {course_name}\n"
                          . "Score: {score_percent}%\n\n{criteria_breakdown}",
        ];
    }

    /**
     * Replace {placeholder} tokens in a template text.
     *
     * @param string $text Template text.
     * @param array $tokens Placeholder name => value.
     * @return string
     */
    private static function apply_placeholders(string $text, array $tokens): string {
        foreach ($tokens as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
        return $text;
    }

    /**
     * One line per criterion whose outcome or choice changed between two breakdowns,
     * e.g. "Password set: [PASS] → [FAIL]" or "[Safe Exam Browser - Yes, ...] → [Safe Exam Browser - No, ...]".
     *
     * @param array $previous Previous criteria breakdown.
     * @param array $current Current criteria breakdown.
     * @return string[]
     */
    private static function get_criteria_changes(array $previous, array $current): array {
        $previousbykey = array_column($previous, null, 'key');

        $describe = function (array $item): string {
            $label = scoring_engine::display_label($item);
            return isset($item['choice']) ? $label : $label . ': ' . self::status_word((bool) $item['met']);
        };

        $lines = [];
        foreach ($current as $item) {
            $before = $previousbykey[$item['key']] ?? null;
            if (
                $before === null
                    || ((bool) $before['met'] === (bool) $item['met']
                        && ($before['choice'] ?? null) === ($item['choice'] ?? null))
            ) {
                continue;
            }

            // Old and new values are bracketed so they stand out from the criterion name.
            if (!isset($before['choice']) && !isset($item['choice'])) {
                $lines[] = scoring_engine::display_label($item) . ': '
                    . '[' . self::status_word((bool) $before['met']) . '] → [' . self::status_word((bool) $item['met']) . ']';
            } else {
                $lines[] = '[' . $describe($before) . '] → [' . $describe($item) . ']';
            }
        }
        return $lines;
    }

    /**
     * True if the quiz's SEB criterion records the "No" option.
     *
     * @param array $breakdown Criteria breakdown.
     * @return bool
     */
    private static function seb_not_used(array $breakdown): bool {
        foreach ($breakdown as $item) {
            if ($item['key'] === 'seb_enabled') {
                return ($item['choice'] ?? null) === 'no';
            }
        }
        return false;
    }

    /**
     * Plain-text list of changed criteria for the {criteria_changes} placeholder.
     *
     * @param string[] $changes Lines from get_criteria_changes().
     * @return string
     */
    private static function build_changes_plain(array $changes): string {
        if (empty($changes)) {
            return get_string('criteria_nochange', 'local_quizmetamonitor');
        }
        return implode("\n", array_map(fn($line) => '- ' . $line, $changes));
    }

    /**
     * HTML list of changed criteria for the {criteria_changes} placeholder.
     *
     * @param string[] $changes Lines from get_criteria_changes().
     * @return string
     */
    private static function build_changes_html(array $changes): string {
        if (empty($changes)) {
            return '<p>' . htmlspecialchars(get_string('criteria_nochange', 'local_quizmetamonitor'), ENT_QUOTES) . '</p>';
        }
        return '<ul>' . implode('', array_map(
            fn($line) => '<li>' . htmlspecialchars($line, ENT_QUOTES) . '</li>',
            $changes
        )) . '</ul>';
    }

    /**
     * "71.43% → 85.71%" when the score moved, "85.71% (unchanged)" when it did not.
     *
     * @param float|null $previousscore Previous score, or null if none.
     * @param float $score Current score.
     * @return string
     */
    private static function build_score_change(?float $previousscore, float $score): string {
        if ($previousscore === null || abs($previousscore - $score) < 0.001) {
            return get_string('score_unchanged', 'local_quizmetamonitor', (string) $score);
        }
        return get_string(
            'score_changed_from',
            'local_quizmetamonitor',
            (object) ['previous' => (string) $previousscore, 'current' => (string) $score]
        );
    }

    /**
     * PASS/FAIL word used in the email breakdown and change list.
     *
     * @param bool $met Whether the criterion is met.
     * @return string
     */
    private static function status_word(bool $met): string {
        return get_string($met ? 'email_pass' : 'email_fail', 'local_quizmetamonitor');
    }

    /**
     * Points column text for one breakdown item, e.g. "1 / 1 pts".
     *
     * @param array $item Breakdown item.
     * @return string
     */
    private static function points_text(array $item): string {
        return get_string('email_points', 'local_quizmetamonitor', (object) [
            'earned' => (string) $item['points_earned'],
            'possible' => (string) $item['points_possible'],
        ]);
    }

    /**
     * Plain-text criteria breakdown for the {criteria_breakdown} placeholder.
     *
     * @param array $breakdown Criteria breakdown.
     * @return string
     */
    private static function build_breakdown_plain(array $breakdown): string {
        $lines = [];
        foreach ($breakdown as $item) {
            $lines[] = sprintf(
                '[%s] %s — %s',
                self::status_word((bool) $item['met']),
                scoring_engine::display_label($item),
                self::points_text($item)
            );
        }
        return implode("\n", $lines);
    }

    /**
     * HTML criteria table for the {criteria_breakdown} placeholder.
     *
     * @param array $breakdown Criteria breakdown.
     * @return string
     */
    private static function build_breakdown_html(array $breakdown): string {
        // Every cell gets an explicit border colour; without it, mail clients draw the
        // border in the cell's text colour, so PASS/FAIL cells got green/red edges.
        // The status colour therefore lives on an inner span, not the cell.
        $cell = 'border:1px solid #333333;padding:6px';

        $rows = '';
        foreach ($breakdown as $item) {
            $color  = $item['met'] ? '#2e7d32' : '#c62828';
            $status = self::status_word((bool) $item['met']);
            $rows  .= sprintf(
                '<tr>
                    <td style="%1$s">%2$s</td>
                    <td style="%1$s"><span style="color:%3$s;font-weight:bold">%4$s</span></td>
                    <td style="%1$s">%5$s</td>
                </tr>',
                $cell,
                htmlspecialchars(scoring_engine::display_label($item), ENT_QUOTES),
                $color,
                $status,
                self::points_text($item)
            );
        }

        return '<table border="1" cellpadding="6" cellspacing="0"'
             . ' style="border-collapse:collapse;border:1px solid #333333">'
             . '<thead><tr>'
             . '<th style="' . $cell . '">' . get_string('email_col_criterion', 'local_quizmetamonitor') . '</th>'
             . '<th style="' . $cell . '">' . get_string('email_col_status', 'local_quizmetamonitor') . '</th>'
             . '<th style="' . $cell . '">' . get_string('email_col_points', 'local_quizmetamonitor') . '</th>'
             . '</tr></thead>'
             . '<tbody>' . $rows . '</tbody>'
             . '</table>';
    }
}
