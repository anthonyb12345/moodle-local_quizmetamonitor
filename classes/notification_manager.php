<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

class notification_manager {

    const TYPE_TEACHER_CREATED       = 'teacher_created';
    const TYPE_TEACHER_SCORE_CHANGED = 'teacher_score_changed';
    const TYPE_EXAMMONITOR_ALERT     = 'exammonitor_below_threshold';

    // ---------------------------------------------------------------- //
    // Public senders                                                    //
    // ---------------------------------------------------------------- //

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
        int $actorid
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
            ''
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
        string $categoryname
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
            'criteria_breakdown' => self::build_breakdown_plain($scoreresult['breakdown']),
        ];

        $htmltokens = $tokens;
        $htmltokens['criteria_breakdown'] = self::build_breakdown_html($scoreresult['breakdown']);

        $subject   = self::apply_placeholders($tmpl->subject,   $tokens);
        $bodyplain = self::apply_placeholders($tmpl->body_plain, $tokens);
        $bodyhtml  = self::apply_placeholders($tmpl->body_html,  $htmltokens);

        $noreply = \core_user::get_noreply_user();
        $now     = time();

        foreach ($userids as $userid) {
            $user = \core_user::get_user($userid);

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

    private static function build_breakdown_plain(array $breakdown): string {
        $lines = [];
        foreach ($breakdown as $item) {
            $status  = $item['met'] ? 'PASS' : 'FAIL';
            $lines[] = sprintf(
                '[%s] %s — %s / %s pts',
                $status,
                $item['label'],
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
                htmlspecialchars($item['label'], ENT_QUOTES),
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