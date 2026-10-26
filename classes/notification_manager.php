<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

class notification_manager {

    const TYPE_TEACHER_CREATED       = 'teacher_created';
    const TYPE_TEACHER_SCORE_CHANGED = 'teacher_score_changed';
    const TYPE_EXAMMONITOR_ALERT     = 'exammonitor_below_threshold';
    const TYPE_TEACHER_RESTORED      = 'teacher_quizzes_restored';
    const TYPE_TEACHER_ALERT         = 'teacher_below_threshold';

    // ---------------------------------------------------------------- //
    // Public senders                                                    //
    // ---------------------------------------------------------------- //

    /**
     * Email the teacher who restored/imported a quiz, with the same criteria table as the
     * "quiz created" email. Called once per restored quiz.
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

    public static function notify_teachers_created(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        int $actorid
    ): void {
        $userids = self::get_actor_recipients($actorid, (int) $course->id);

        // debugging(
        //     'local_quizmetamonitor: notify_teachers_created for quiz '
        //     . $quiz->id . ' found ' . count($userids) . ' teacher(s)',
        //     DEBUG_DEVELOPER
        // );

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

    public static function notify_teachers_score_changed(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        ?float $previousscore,
        int $actorid,
        array $previousbreakdown = []
    ): void {
        $userids = self::get_actor_recipients($actorid, (int) $course->id);

        // debugging(
        //     'local_quizmetamonitor: notify_teachers_score_changed for quiz '
        //     . $quiz->id . ' found ' . count($userids) . ' teacher(s)',
        //     DEBUG_DEVELOPER
        // );

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

    public static function notify_exam_monitors_alert(
        array $scoreresult,
        \stdClass $quiz,
        \stdClass $course,
        string $categoryname
    ): void {
        global $DB;

        $userids = category_util::get_monitors_for_category((int) $scoreresult['categoryid']);

        // if (empty($userids)) {
        //     debugging(
        //         'local_quizmetamonitor: no exam monitors found for category '
        //         . $scoreresult['categoryid'],
        //         DEBUG_DEVELOPER
        //     );
        //     return;
        // }

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

    // ---------------------------------------------------------------- //
    // Recipient helpers                                                 //
    // ---------------------------------------------------------------- //

    /**
     * Only the user who created/edited the quiz is notified, and only if
     * they are a teacher or non-editing teacher in the course.
     *
     * @return int[]
     */
    private static function get_actor_recipients(int $actorid, int $courseid): array {
        if ($actorid <= 0) {
            return [];
        }

        $teachers = category_util::get_teachers_for_course($courseid);
        return in_array($actorid, $teachers, true) ? [$actorid] : [];
    }

    // ---------------------------------------------------------------- //
    // Core dispatch                                                     //
    // ---------------------------------------------------------------- //

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

        // if (empty($userids)) {
        //     debugging(
        //         'local_quizmetamonitor: send_to_users called with empty userids for type=' . $type,
        //         DEBUG_DEVELOPER
        //     );
        //     return;
        // }

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

            // if (!$user) {
            //     debugging('local_quizmetamonitor: user ' . $userid . ' not found', DEBUG_DEVELOPER);
            //     continue;
            // }

            // if (empty($user->email) || !validate_email($user->email)) {
            //     debugging('local_quizmetamonitor: user ' . $userid . ' has no valid email', DEBUG_DEVELOPER);
            //     continue;
            // }

            // // Check user is not deleted or suspended.
            // if ($user->deleted || $user->suspended) {
            //     debugging('local_quizmetamonitor: user ' . $userid . ' is deleted or suspended', DEBUG_DEVELOPER);
            //     continue;
            // }

            $result = email_to_user($user, $noreply, $subject, $bodyplain, $bodyhtml);

            // debugging(
            //     'local_quizmetamonitor: email_to_user for userid=' . $userid
            //     . ' type=' . $type
            //     . ' result=' . ($result ? 'OK' : 'FAILED'),
            //     DEBUG_DEVELOPER
            // );

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

    // ---------------------------------------------------------------- //
    // Template helpers                                                  //
    // ---------------------------------------------------------------- //

    private static function get_template(string $key): \stdClass {
        global $DB;

        $tmpl = $DB->get_record('local_quizmeta_email_tmpl', ['template_key' => $key]);
        if ($tmpl) {
            return $tmpl;
        }

        // Emergency fallback — should not occur after install seeding.
        // debugging(
        //     'local_quizmetamonitor: no template found for key=' . $key . ', using fallback',
        //     DEBUG_DEVELOPER
        // );

        return (object)[
            'subject'    => '[Quiz Monitor] ' . $key . ' — {quiz_name}',
            'body_html'  => '<p>Quiz: {quiz_name}</p><p>Course: {course_name}</p>'
                          . '<p>Score: {score_percent}%</p><p>{criteria_breakdown}</p>',
            'body_plain' => "Quiz: {quiz_name}\nCourse: {course_name}\n"
                          . "Score: {score_percent}%\n\n{criteria_breakdown}",
        ];
    }

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
     * @return string[]
     */
    private static function get_criteria_changes(array $previous, array $current): array {
        $previousbykey = array_column($previous, null, 'key');

        $describe = function(array $item): string {
            $label = scoring_engine::display_label($item);
            return isset($item['choice']) ? $label : $label . ': ' . ($item['met'] ? 'PASS' : 'FAIL');
        };

        $lines = [];
        foreach ($current as $item) {
            $before = $previousbykey[$item['key']] ?? null;
            if ($before === null
                    || ((bool) $before['met'] === (bool) $item['met']
                        && ($before['choice'] ?? null) === ($item['choice'] ?? null))) {
                continue;
            }

            // Old and new values are bracketed so they stand out from the criterion name.
            if (!isset($before['choice']) && !isset($item['choice'])) {
                $lines[] = scoring_engine::display_label($item) . ': '
                    . '[' . ($before['met'] ? 'PASS' : 'FAIL') . '] → [' . ($item['met'] ? 'PASS' : 'FAIL') . ']';
            } else {
                $lines[] = '[' . $describe($before) . '] → [' . $describe($item) . ']';
            }
        }
        return $lines;
    }

    /**
     * True if the quiz's SEB criterion records the "No" option.
     */
    private static function seb_not_used(array $breakdown): bool {
        foreach ($breakdown as $item) {
            if ($item['key'] === 'seb_enabled') {
                return ($item['choice'] ?? null) === 'no';
            }
        }
        return false;
    }

    private static function build_changes_plain(array $changes): string {
        if (empty($changes)) {
            return get_string('criteria_nochange', 'local_quizmetamonitor');
        }
        return implode("\n", array_map(fn($line) => '- ' . $line, $changes));
    }

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
     */
    private static function build_score_change(?float $previousscore, float $score): string {
        if ($previousscore === null || abs($previousscore - $score) < 0.001) {
            return get_string('score_unchanged', 'local_quizmetamonitor', (string) $score);
        }
        return get_string('score_changed_from', 'local_quizmetamonitor',
            (object) ['previous' => (string) $previousscore, 'current' => (string) $score]);
    }

    private static function build_breakdown_plain(array $breakdown): string {
        $lines = [];
        foreach ($breakdown as $item) {
            $status  = $item['met'] ? 'PASS' : 'FAIL';
            $lines[] = sprintf(
                '[%s] %s — %s / %s pts',
                $status,
                scoring_engine::display_label($item),
                $item['points_earned'],
                $item['points_possible']
            );
        }
        return implode("\n", $lines);
    }

    private static function build_breakdown_html(array $breakdown): string {
        // Every cell gets an explicit border colour; without it, mail clients draw the
        // border in the cell's text colour, so PASS/FAIL cells got green/red edges.
        // The status colour therefore lives on an inner span, not the cell.
        $cell = 'border:1px solid #333333;padding:6px';

        $rows = '';
        foreach ($breakdown as $item) {
            $color  = $item['met'] ? '#2e7d32' : '#c62828';
            $status = $item['met'] ? 'PASS' : 'FAIL';
            $rows  .= sprintf(
                '<tr>
                    <td style="%1$s">%2$s</td>
                    <td style="%1$s"><span style="color:%3$s;font-weight:bold">%4$s</span></td>
                    <td style="%1$s">%5$s / %6$s pts</td>
                </tr>',
                $cell,
                htmlspecialchars(scoring_engine::display_label($item), ENT_QUOTES),
                $color,
                $status,
                $item['points_earned'],
                $item['points_possible']
            );
        }

        return '<table border="1" cellpadding="6" cellspacing="0"'
             . ' style="border-collapse:collapse;border:1px solid #333333">'
             . '<thead><tr>'
             . '<th style="' . $cell . '">Criterion</th>'
             . '<th style="' . $cell . '">Status</th>'
             . '<th style="' . $cell . '">Points</th>'
             . '</tr></thead>'
             . '<tbody>' . $rows . '</tbody>'
             . '</table>';
    }
}
