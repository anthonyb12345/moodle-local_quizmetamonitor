<?php
namespace local_quizmetamonitor\task;

defined('MOODLE_INTERNAL') || die();

use local_quizmetamonitor\scoring_engine;

/**
 * Nightly safety-net: recomputes scores for tracked quizzes.
 * Useful when bulk changes (e.g. category moves) may not fire events.
 *
 * Quizzes created before the plugin was installed (config 'trackfrom') are skipped,
 * unless a teacher has since created/edited them (e.g. a restored course copy keeps
 * its old timecreated but is in active use).
 */
class resync_quiz_scores extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_resync_scores', 'local_quizmetamonitor');
    }

    /**
     * When the plugin was installed: the 'trackfrom' config if set (new installs),
     * otherwise the earliest default criteria row, which install.php seeds.
     */
    public static function get_trackfrom(): int {
        global $DB;

        $trackfrom = get_config('local_quizmetamonitor', 'trackfrom');
        if ($trackfrom !== false) {
            return (int) $trackfrom;
        }
        return (int) $DB->get_field_sql('SELECT MIN(timecreated) FROM {local_quizmeta_crit_default}');
    }

    public function execute(): void {
        global $DB;

        $trackfrom = self::get_trackfrom();

        $sql = "SELECT q.id
                  FROM {quiz} q
                 WHERE q.timecreated >= :trackfrom
                    OR EXISTS (SELECT 1
                                 FROM {local_quizmeta_score_history} h
                                WHERE h.quizid = q.id
                                  AND h.changed_by_userid > 0)";
        $quizids = $DB->get_fieldset_sql($sql, ['trackfrom' => $trackfrom]);

        foreach ($quizids as $quizid) {
            try {
                $result = scoring_engine::compute_score((int) $quizid);
                scoring_engine::save_score($result, 0); // 0 = cron / system.
            } catch (\Throwable $e) {
                debugging(
                    'local_quizmetamonitor resync: failed for quizid=' . $quizid
                    . ' — ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
    }
}