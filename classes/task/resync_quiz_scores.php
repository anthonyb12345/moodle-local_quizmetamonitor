<?php
namespace local_quizmetamonitor\task;

defined('MOODLE_INTERNAL') || die();

use local_quizmetamonitor\scoring_engine;

/**
 * Nightly safety-net: recomputes scores for every quiz in the system.
 * Useful when bulk changes (e.g. category moves) may not fire events.
 */
class resync_quiz_scores extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_resync_scores', 'local_quizmetamonitor');
    }

    public function execute(): void {
        global $DB;

        $quizids = $DB->get_fieldset_select('quiz', 'id', '1 = 1');

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