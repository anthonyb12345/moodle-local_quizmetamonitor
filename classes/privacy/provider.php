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

namespace local_quizmetamonitor\privacy;

use context;
use context_coursecat;
use context_module;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_quizmetamonitor.
 *
 * Personal data is stored in four tables:
 *  - local_quizmeta_score_history: who created, edited or restored a quiz (quiz context);
 *  - local_quizmeta_notif_log: which notifications were sent to whom (quiz context);
 *  - local_quizmeta_exammonitor: Exam Monitor assignments and who made them (category context);
 *  - local_quizmeta_config_history: who changed the criteria/threshold (category or system context).
 *
 * On deletion, notification log rows and Exam Monitor assignments are deleted; in the two history
 * tables the user ID is replaced by 0 (system) so scores and the configuration audit trail remain.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by the plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_quizmeta_score_history', [
            'changed_by_userid' => 'privacy:metadata:local_quizmeta_score_history:changed_by_userid',
            'quizid' => 'privacy:metadata:local_quizmeta_score_history:quizid',
            'score_percent' => 'privacy:metadata:local_quizmeta_score_history:score_percent',
            'timecreated' => 'privacy:metadata:local_quizmeta_score_history:timecreated',
        ], 'privacy:metadata:local_quizmeta_score_history');

        $collection->add_database_table('local_quizmeta_notif_log', [
            'userid' => 'privacy:metadata:local_quizmeta_notif_log:userid',
            'quizid' => 'privacy:metadata:local_quizmeta_notif_log:quizid',
            'notification_type' => 'privacy:metadata:local_quizmeta_notif_log:notification_type',
            'score_percent' => 'privacy:metadata:local_quizmeta_notif_log:score_percent',
            'timesent' => 'privacy:metadata:local_quizmeta_notif_log:timesent',
        ], 'privacy:metadata:local_quizmeta_notif_log');

        $collection->add_database_table('local_quizmeta_exammonitor', [
            'userid' => 'privacy:metadata:local_quizmeta_exammonitor:userid',
            'categoryid' => 'privacy:metadata:local_quizmeta_exammonitor:categoryid',
            'assignedby' => 'privacy:metadata:local_quizmeta_exammonitor:assignedby',
            'timecreated' => 'privacy:metadata:local_quizmeta_exammonitor:timecreated',
        ], 'privacy:metadata:local_quizmeta_exammonitor');

        $collection->add_database_table('local_quizmeta_config_history', [
            'changed_by' => 'privacy:metadata:local_quizmeta_config_history:changed_by',
            'change_type' => 'privacy:metadata:local_quizmeta_config_history:change_type',
            'categoryid' => 'privacy:metadata:local_quizmeta_config_history:categoryid',
            'description' => 'privacy:metadata:local_quizmeta_config_history:description',
            'timecreated' => 'privacy:metadata:local_quizmeta_config_history:timecreated',
        ], 'privacy:metadata:local_quizmeta_config_history');

        return $collection;
    }

    /**
     * SQL fragment joining a quizid column to its course module context.
     *
     * @param string $quizcolumn Fully qualified column holding the quiz id.
     * @return string
     */
    protected static function quiz_context_join(string $quizcolumn): string {
        return "JOIN {course_modules} cm ON cm.instance = $quizcolumn
                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel";
    }

    /**
     * Get the contexts that hold personal data for the user.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $join = self::quiz_context_join('h.quizid');
        $contextlist->add_from_sql(
            "SELECT ctx.id FROM {local_quizmeta_score_history} h $join WHERE h.changed_by_userid = :userid",
            ['modulelevel' => CONTEXT_MODULE, 'userid' => $userid]
        );
        $join = self::quiz_context_join('n.quizid');
        $contextlist->add_from_sql(
            "SELECT ctx.id FROM {local_quizmeta_notif_log} n $join WHERE n.userid = :userid",
            ['modulelevel' => CONTEXT_MODULE, 'userid' => $userid]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_quizmeta_exammonitor} em
               JOIN {context} ctx ON ctx.instanceid = em.categoryid AND ctx.contextlevel = :catlevel
              WHERE em.userid = :userid1 OR em.assignedby = :userid2",
            ['catlevel' => CONTEXT_COURSECAT, 'userid1' => $userid, 'userid2' => $userid]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_quizmeta_config_history} ch
               JOIN {context} ctx ON ctx.instanceid = ch.categoryid AND ctx.contextlevel = :catlevel
              WHERE ch.changed_by = :userid",
            ['catlevel' => CONTEXT_COURSECAT, 'userid' => $userid]
        );
        // Site-default criteria/threshold changes live in the system context.
        global $DB;
        if (
            $DB->record_exists_select(
                'local_quizmeta_config_history',
                'changed_by = :userid AND categoryid IS NULL',
                ['userid' => $userid]
            )
        ) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the users who have personal data in a context.
     *
     * @param userlist $userlist The userlist to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context instanceof context_module) {
            $quizid = self::get_quizid($context);
            if (!$quizid) {
                return;
            }
            $userlist->add_from_sql(
                'changed_by_userid',
                "SELECT changed_by_userid FROM {local_quizmeta_score_history} WHERE quizid = :quizid AND changed_by_userid > 0",
                ['quizid' => $quizid]
            );
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {local_quizmeta_notif_log} WHERE quizid = :quizid",
                ['quizid' => $quizid]
            );
        } else if ($context instanceof context_coursecat) {
            $params = ['categoryid' => $context->instanceid];
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {local_quizmeta_exammonitor} WHERE categoryid = :categoryid",
                $params
            );
            $userlist->add_from_sql(
                'assignedby',
                "SELECT assignedby FROM {local_quizmeta_exammonitor} WHERE categoryid = :categoryid AND assignedby > 0",
                $params
            );
            $userlist->add_from_sql(
                'changed_by',
                "SELECT changed_by FROM {local_quizmeta_config_history} WHERE categoryid = :categoryid AND changed_by > 0",
                $params
            );
        } else if ($context instanceof context_system) {
            $userlist->add_from_sql(
                'changed_by',
                "SELECT changed_by FROM {local_quizmeta_config_history} WHERE categoryid IS NULL AND changed_by > 0",
                []
            );
        }
    }

    /**
     * Export the user's personal data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $writer = writer::with_context($context);

            if ($context instanceof context_module) {
                $quizid = self::get_quizid($context);
                if (!$quizid) {
                    continue;
                }
                $history = $DB->get_records(
                    'local_quizmeta_score_history',
                    ['quizid' => $quizid, 'changed_by_userid' => $userid],
                    'timecreated ASC',
                    'id, score_percent, timecreated'
                );
                if ($history) {
                    $writer->export_data([get_string('privacy:path:scorehistory', 'local_quizmetamonitor')], (object) [
                        'changes' => array_values(array_map(fn($r) => (object) [
                            'score_percent' => $r->score_percent,
                            'timecreated' => transform::datetime($r->timecreated),
                        ], $history)),
                    ]);
                }
                $notifications = $DB->get_records(
                    'local_quizmeta_notif_log',
                    ['quizid' => $quizid, 'userid' => $userid],
                    'timesent ASC',
                    'id, notification_type, score_percent, timesent'
                );
                if ($notifications) {
                    $writer->export_data([get_string('privacy:path:notifications', 'local_quizmetamonitor')], (object) [
                        'notifications' => array_values(array_map(fn($r) => (object) [
                            'notification_type' => $r->notification_type,
                            'score_percent' => $r->score_percent,
                            'timesent' => transform::datetime($r->timesent),
                        ], $notifications)),
                    ]);
                }
            } else if ($context instanceof context_coursecat) {
                $assignments = $DB->get_records_select(
                    'local_quizmeta_exammonitor',
                    'categoryid = :categoryid AND (userid = :userid1 OR assignedby = :userid2)',
                    ['categoryid' => $context->instanceid, 'userid1' => $userid, 'userid2' => $userid],
                    'timecreated ASC'
                );
                if ($assignments) {
                    $writer->export_data([get_string('privacy:path:exammonitor', 'local_quizmetamonitor')], (object) [
                        'assignments' => array_values(array_map(fn($r) => (object) [
                            'isexammonitor' => transform::yesno($r->userid == $userid),
                            'assignedbyyou' => transform::yesno($r->assignedby == $userid),
                            'timecreated' => transform::datetime($r->timecreated),
                        ], $assignments)),
                    ]);
                }
                self::export_config_history($context, $userid, (int) $context->instanceid);
            } else if ($context instanceof context_system) {
                self::export_config_history($context, $userid, null);
            }
        }
    }

    /**
     * Export the criteria/threshold changes a user made for a category (or the site default).
     *
     * @param context $context Context to export into.
     * @param int $userid The user.
     * @param int|null $categoryid Course category, or null for the site default.
     */
    protected static function export_config_history(context $context, int $userid, ?int $categoryid): void {
        global $DB;

        $select = 'changed_by = :userid AND ' . ($categoryid === null ? 'categoryid IS NULL' : 'categoryid = :categoryid');
        $params = ['userid' => $userid] + ($categoryid === null ? [] : ['categoryid' => $categoryid]);
        $changes = $DB->get_records_select(
            'local_quizmeta_config_history',
            $select,
            $params,
            'timecreated ASC',
            'id, change_type, description, timecreated'
        );
        if (!$changes) {
            return;
        }
        writer::with_context($context)->export_data(
            [get_string('privacy:path:confighistory', 'local_quizmetamonitor')],
            (object) ['changes' => array_values(array_map(fn($r) => (object) [
                'change_type' => $r->change_type,
                'description' => $r->description,
                'timecreated' => transform::datetime($r->timecreated),
            ], $changes))]
        );
    }

    /**
     * Delete all personal data in a context.
     *
     * @param context $context The context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        self::delete_in_context($context, null);
    }

    /**
     * Delete one user's personal data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_in_context($context, [$userid]);
        }
    }

    /**
     * Delete the personal data of several users in one context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $userids = array_map('intval', $userlist->get_userids());
        if ($userids) {
            self::delete_in_context($userlist->get_context(), $userids);
        }
    }

    /**
     * Delete or anonymise personal data in a context, for the given users or for everyone.
     *
     * @param context $context The context.
     * @param int[]|null $userids Users to delete for, or null for all users.
     */
    protected static function delete_in_context(context $context, ?array $userids): void {
        global $DB;

        $userfilter = function (string $column) use ($DB, $userids): array {
            if ($userids === null) {
                return ['', []];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u' . $column);
            return [" AND $column $insql", $inparams];
        };

        if ($context instanceof context_module) {
            $quizid = self::get_quizid($context);
            if (!$quizid) {
                return;
            }
            [$sql, $params] = $userfilter('userid');
            $DB->delete_records_select('local_quizmeta_notif_log', 'quizid = :quizid' . $sql, ['quizid' => $quizid] + $params);
            [$sql, $params] = $userfilter('changed_by_userid');
            $DB->set_field_select(
                'local_quizmeta_score_history',
                'changed_by_userid',
                0,
                'quizid = :quizid' . $sql,
                ['quizid' => $quizid] + $params
            );
        } else if ($context instanceof context_coursecat) {
            $category = ['categoryid' => $context->instanceid];
            [$sql, $params] = $userfilter('userid');
            $DB->delete_records_select('local_quizmeta_exammonitor', 'categoryid = :categoryid' . $sql, $category + $params);
            [$sql, $params] = $userfilter('assignedby');
            $DB->set_field_select(
                'local_quizmeta_exammonitor',
                'assignedby',
                0,
                'categoryid = :categoryid' . $sql,
                $category + $params
            );
            [$sql, $params] = $userfilter('changed_by');
            $DB->set_field_select(
                'local_quizmeta_config_history',
                'changed_by',
                0,
                'categoryid = :categoryid' . $sql,
                $category + $params
            );
        } else if ($context instanceof context_system) {
            [$sql, $params] = $userfilter('changed_by');
            $DB->set_field_select('local_quizmeta_config_history', 'changed_by', 0, 'categoryid IS NULL' . $sql, $params);
        }
    }

    /**
     * The quiz id of a quiz course module context, or 0 if the context is not a quiz.
     *
     * @param context_module $context The module context.
     * @return int
     */
    protected static function get_quizid(context_module $context): int {
        global $DB;

        return (int) $DB->get_field_sql(
            "SELECT cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              WHERE cm.id = :cmid",
            ['cmid' => $context->instanceid]
        );
    }
}
