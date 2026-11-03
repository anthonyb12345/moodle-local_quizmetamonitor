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

namespace local_quizmetamonitor\task;

use local_quizmetamonitor\scoring_engine;

/**
 * Nightly safety-net: recomputes scores for tracked quizzes.
 * Useful when bulk changes (e.g. category moves) may not fire events.
 *
 * Quizzes created before the plugin was installed (config 'trackfrom') are skipped,
 * unless a teacher has since created/edited them (e.g. a restored course copy keeps
 * its old timecreated but is in active use). Quizzes with no score at all are always
 * included, as a safety net for anything the event observers missed.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resync_quiz_scores extends \core\task\scheduled_task {
    /**
     * Name of the task shown in the admin interface.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_resync_scores', 'local_quizmetamonitor');
    }

    /**
     * When the plugin was installed: the 'trackfrom' config if set (new installs),
     * otherwise the earliest default criteria row, which install.php seeds.
     *
     * @return int
     */
    public static function get_trackfrom(): int {
        global $DB;

        $trackfrom = get_config('local_quizmetamonitor', 'trackfrom');
        if ($trackfrom !== false) {
            return (int) $trackfrom;
        }
        return (int) $DB->get_field_sql('SELECT MIN(timecreated) FROM {local_quizmetamonitor_crit_default}');
    }

    /**
     * Rescore the tracked quizzes.
     */
    public function execute(): void {
        global $DB;

        $trackfrom = self::get_trackfrom();

        $sql = "SELECT q.id
                  FROM {quiz} q
                 WHERE q.timecreated >= :trackfrom
                    OR EXISTS (SELECT 1
                                 FROM {local_quizmetamonitor_score_history} h
                                WHERE h.quizid = q.id
                                  AND h.changed_by_userid > 0)
                    OR NOT EXISTS (SELECT 1
                                     FROM {local_quizmetamonitor_quiz_score} s
                                    WHERE s.quizid = q.id)";
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
