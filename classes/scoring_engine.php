<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves criteria + threshold and computes the security score for a quiz.
 */
class scoring_engine {

    // ------------------------------------------------------------------ //
    // Public API                                                          //
    // ------------------------------------------------------------------ //

    /**
     * Canonical list of criterion keys → default human-readable labels.
     * The order here drives the default sortorder on install.
     */
    public static function get_available_criteria(): array {
        return [
            'open_date_set'        => 'Open date set',
            'close_date_set'       => 'Close date set',
            'allowed_attempts_set' => 'Allowed attempts limited',
            'password_set'         => 'Password set',
            'ip_restriction_set'   => 'Restriction by IP address set',
            'block_concurrent_set' => 'Block Concurrent Connections set',
            'seb_enabled'          => 'Safe Exam Browser enabled',
        ];
    }

    /**
     * Resolve the criteria set for a category, falling back to site default
     * if no category-level override rows exist.
     *
     * Returns: [ criterion_key => ['label'=>…, 'points'=>…, 'enabled'=>…] ]
     */
    public static function resolve_criteria(int $categoryid): array {
        global $DB;

        $catrows = $DB->get_records(
            'local_quizmeta_crit_category',
            ['categoryid' => $categoryid],
            'sortorder ASC'
        );

        if (!empty($catrows)) {
            return self::rows_to_map($catrows);
        }

        $defrows = $DB->get_records('local_quizmeta_crit_default', [], 'sortorder ASC');
        return self::rows_to_map($defrows);
    }

    /**
     * Resolve the threshold for a category, falling back to the site default
     * (the row where categoryid IS NULL) if no category override exists.
     */
    public static function resolve_threshold(int $categoryid): float {
        global $DB;

        $override = $DB->get_record('local_quizmeta_threshold', ['categoryid' => $categoryid]);
        if ($override) {
            return (float) $override->threshold_percent;
        }

        $default = $DB->get_record('local_quizmeta_threshold', ['categoryid' => null]);
        if ($default) {
            return (float) $default->threshold_percent;
        }

        return 70.00; // Hard fallback — should never reach here after install.
    }

    /**
     * Compute the full security score for a quiz.
     *
     * @return array{
     *   quizid: int, courseid: int, categoryid: int,
     *   score_percent: float, threshold_applied: float,
     *   is_below_threshold: int, breakdown: array, cmid: int
     * }
     */
    public static function compute_score(int $quizid): array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $cm   = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);

        $courseid   = (int) $quiz->course;
        $categoryid = category_util::get_categoryid_for_course($courseid);
        $criteria   = self::resolve_criteria($categoryid);
        $threshold  = self::resolve_threshold($categoryid);
        $accessdata = self::get_access_data($quiz);

        $totalpoints  = 0.0;
        $earnedpoints = 0.0;
        $breakdown    = [];

        foreach ($criteria as $key => $def) {
            if (!$def['enabled']) {
                continue;
            }

            $possible = (float) $def['points'];
            $totalpoints += $possible;
            $met   = self::evaluate_criterion($key, $quiz, $accessdata);
            $label = $def['label'];

            // SEB "No" is a legitimate choice (open book / software allowed), so it is never
            // penalised: always full points, and the label shows which option was picked.
            $choice = null;
            if ($key === 'seb_enabled') {
                $choice = self::seb_choice((int) $accessdata['sebmode']);
                $label  = get_string('seb_choice_' . $choice, 'local_quizmetamonitor');
                $met    = true;
            }

            if ($met) {
                $earnedpoints += $possible;
            }

            $item = [
                'key'             => $key,
                'label'           => $label,
                'points_possible' => $possible,
                'points_earned'   => $met ? $possible : 0.0,
                'met'             => $met,
            ];
            if ($choice !== null) {
                $item['choice'] = $choice;
            }
            $breakdown[] = $item;
        }

        $scorepercent = $totalpoints > 0
            ? round(($earnedpoints / $totalpoints) * 100, 2)
            : 0.0;

        return [
            'quizid'             => $quizid,
            'courseid'           => $courseid,
            'categoryid'         => $categoryid,
            'score_percent'      => $scorepercent,
            'threshold_applied'  => $threshold,
            'is_below_threshold' => (int) ($scorepercent < $threshold),
            'breakdown'          => $breakdown,
            'cmid'               => (int) $cm->id,
        ];
    }

    /**
     * Map quizaccess_seb's requiresafeexambrowser value to the stored choice key
     * (settings_provider::USE_SEB_* constants; ints used so this works without the rule installed).
     */
    private static function seb_choice(int $mode): string {
        switch ($mode) {
            case 0:  return 'no';       // USE_SEB_NO.
            case 1:  return 'manual';   // USE_SEB_CONFIG_MANUALLY.
            case 2:  return 'template'; // USE_SEB_TEMPLATE.
            case 3:  return 'upload';   // USE_SEB_UPLOAD_CONFIG.
            case 4:  return 'client';   // USE_SEB_CLIENT_CONFIG.
            default: return 'yes';      // Any other "yes" variant.
        }
    }

    /**
     * Label to show for a breakdown item, in the current language.
     *
     * Choice-based criteria (SEB) are resolved at display time from their stored
     * choice, so each reader sees their own language; other criteria use the
     * admin-configured label stored with the score.
     */
    public static function display_label(array $item): string {
        if (isset($item['choice']) && $item['key'] === 'seb_enabled') {
            return get_string('seb_choice_' . $item['choice'], 'local_quizmetamonitor');
        }
        return $item['label'];
    }

    /**
     * What a teacher can change per criterion: whether it is met and, for choice-based
     * criteria, which option was picked. Labels are ignored (they depend on language).
     */
    private static function criteria_outcomes(array $breakdown): array {
        $outcomes = [];
        foreach ($breakdown as $item) {
            $outcomes[$item['key']] = [(bool) $item['met'], $item['choice'] ?? null];
        }
        ksort($outcomes);
        return $outcomes;
    }

    /**
     * Persist the score to local_quizmeta_quiz_score and always append a
     * history row.  Returns [bool $changed, ?float $previousScore, array $previousBreakdown].
     *
     * $changed is true when the score OR any criterion outcome/choice changed
     * (e.g. SEB Yes -> No changes no points but is still a security setting change).
     */
    public static function save_score(array $result, int $changedbyuserid): array {
        global $DB;

        $now      = time();
        $existing = $DB->get_record('local_quizmeta_quiz_score', ['quizid' => $result['quizid']]);
        $snapshot = json_encode($result['breakdown']);

        $record                    = new \stdClass();
        $record->quizid            = $result['quizid'];
        $record->courseid          = $result['courseid'];
        $record->categoryid        = $result['categoryid'];
        $record->score_percent     = $result['score_percent'];
        $record->criteria_snapshot = $snapshot;
        $record->threshold_applied = $result['threshold_applied'];
        $record->is_below_threshold = $result['is_below_threshold'];
        $record->timemodified      = $now;

        $changed       = false;
        $previousscore = null;
        $previous      = [];

        if ($existing) {
            $previousscore = (float) $existing->score_percent;
            $previous      = json_decode($existing->criteria_snapshot, true) ?? [];
            $changed       = abs($previousscore - $result['score_percent']) > 0.001
                || self::criteria_outcomes($previous) !== self::criteria_outcomes($result['breakdown']);
            $record->id          = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('local_quizmeta_quiz_score', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_quizmeta_quiz_score', $record);
            $changed = true; // First-time creation always counts as changed.
        }

        // Always write a history row for a full audit trail.
        $history                    = new \stdClass();
        $history->quizid            = $result['quizid'];
        $history->score_percent     = $result['score_percent'];
        $history->criteria_snapshot = $snapshot;
        $history->threshold_applied = $result['threshold_applied'];
        $history->changed_by_userid = $changedbyuserid;
        $history->timecreated       = $now;
        $DB->insert_record('local_quizmeta_score_history', $history);

        return [$changed, $previousscore, $previous];
    }

    // ------------------------------------------------------------------ //
    // Private helpers                                                     //
    // ------------------------------------------------------------------ //

    private static function rows_to_map(array $rows): array {
        $map = [];
        foreach ($rows as $row) {
            $map[$row->criterion_key] = [
                'label'   => $row->criterion_label,
                'points'  => (float) $row->points,
                'enabled' => (bool) $row->enabled,
            ];
        }
        return $map;
    }

    /**
     * Evaluate one criterion key against real quiz data.
     */
    private static function evaluate_criterion(
        string $key,
        \stdClass $quiz,
        array $accessdata
    ): bool {
        switch ($key) {
            case 'open_date_set':
                return !empty($quiz->timeopen) && (int) $quiz->timeopen > 0;

            case 'close_date_set':
                return !empty($quiz->timeclose) && (int) $quiz->timeclose > 0;

            case 'allowed_attempts_set':
                // 0 = unlimited in Moodle quiz; anything else counts as limited.
                return isset($quiz->attempts) && (int) $quiz->attempts !== 0;

            case 'password_set':
                return !empty($accessdata['password']);

            case 'ip_restriction_set':
                return !empty($accessdata['subnet']);

            case 'block_concurrent_set':
                return !empty($accessdata['blockconcurrent']);

            case 'seb_enabled':
                return !empty($accessdata['sebenabled']);

            default:
                return false;
        }
    }

    /**
     * Gather access-rule metadata for a quiz from the DB.
     * Adjusts gracefully if optional access-rule plugins are not installed.
     */
    private static function get_access_data(\stdClass $quiz): array {
        global $DB;

        $data = [
            'password'       => $quiz->password ?? '',
            'subnet'         => $quiz->subnet   ?? '',
            'blockconcurrent' => 0,
            'sebenabled'     => 0,
            'sebmode'        => 0,
        ];

        // Safe Exam Browser — quizaccess_seb plugin.
        if ($DB->get_manager()->table_exists('quizaccess_seb_quizsettings')) {
            $seb = $DB->get_record('quizaccess_seb_quizsettings', ['quizid' => $quiz->id]);
            if ($seb && isset($seb->requiresafeexambrowser) && (int) $seb->requiresafeexambrowser > 0) {
                $data['sebenabled'] = 1;
                $data['sebmode']    = (int) $seb->requiresafeexambrowser;
            }
        }

        // Block concurrent connections — quizaccess_onesession plugin stores a
        // per-quiz row (quizid, enabled). quizaccess_concurrentlogin kept as a legacy fallback.
        foreach (['quizaccess_onesession', 'quizaccess_concurrentlogin'] as $table) {
            if (!$DB->get_manager()->table_exists($table)) {
                continue;
            }
            // Skip rather than throw if the table doesn't have the expected shape.
            $columns = $DB->get_columns($table);
            if (!isset($columns['quizid'], $columns['enabled'])) {
                continue;
            }
            if ($DB->record_exists_select($table, 'quizid = :quizid AND enabled <> 0', ['quizid' => $quiz->id])) {
                $data['blockconcurrent'] = 1;
                break;
            }
        }

        return $data;
    }
}