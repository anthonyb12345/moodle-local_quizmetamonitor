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
 * Upgrade steps for local_quizmetamonitor.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the upgrade steps for local_quizmetamonitor.
 *
 * @param int $oldversion Version being upgraded from.
 * @return bool
 */
function xmldb_local_quizmetamonitor_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024010103) {
        // New table: local_quizmeta_config_history
        // Stores a record every time criteria or threshold is changed.
        $table = new xmldb_table('local_quizmeta_config_history');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('change_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL); // Either 'criteria' or 'threshold'.
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, null);           // Null = site default.
        $table->add_field('changed_by', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL); // Admin's reason.
        $table->add_field('snapshot', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL); // JSON of new state.
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('change_type_idx', XMLDB_INDEX_NOTUNIQUE, ['change_type']);
        $table->add_index('categoryid_idx', XMLDB_INDEX_NOTUNIQUE, ['categoryid']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024010103, 'local', 'quizmetamonitor');
    }
    if ($oldversion < 2024010104) {
        // No DB changes â€” just re-registers events and forces
        // role capability refresh.
        upgrade_plugin_savepoint(true, 2024010104, 'local', 'quizmetamonitor');
    }
    if ($oldversion < 2024010106) {
        // Teacher "score changed" email now also covers settings changes with an unchanged
        // score. Swap in the new default text unless an admin has customised it.
        require_once(__DIR__ . '/upgradelib.php');
        local_quizmetamonitor_update_settings_changed_template('local_quizmeta_email_tmpl');
        upgrade_plugin_savepoint(true, 2024010106, 'local', 'quizmetamonitor');
    }
    if ($oldversion < 2024010107) {
        // New template: email to the teacher who restored/imported quizzes.
        require_once(__DIR__ . '/upgradelib.php');
        local_quizmetamonitor_add_restored_template('local_quizmeta_email_tmpl');
        upgrade_plugin_savepoint(true, 2024010107, 'local', 'quizmetamonitor');
    }
    if ($oldversion < 2024010108) {
        // No DB changes: adds the restore hook for single-quiz/section restores.
        upgrade_plugin_savepoint(true, 2024010108, 'local', 'quizmetamonitor');
    }
    if ($oldversion < 2024010109) {
        // Restored-quiz email is now one email per quiz with the criteria table.
        require_once(__DIR__ . '/upgradelib.php');
        local_quizmetamonitor_update_restored_template('local_quizmeta_email_tmpl');
        upgrade_plugin_savepoint(true, 2024010109, 'local', 'quizmetamonitor');
    }
    if ($oldversion < 2024010110) {
        // New template: below-threshold alert to the users who created/edited the quiz.
        require_once(__DIR__ . '/upgradelib.php');
        local_quizmetamonitor_add_teacher_alert_template('local_quizmeta_email_tmpl');
        upgrade_plugin_savepoint(true, 2024010110, 'local', 'quizmetamonitor');
    }
    if ($oldversion < 2026102900) {
        // Table names must start with the full plugin name: local_quizmeta_* -> local_quizmetamonitor_*.
        // Renaming keeps all rows.
        $tables = [
            'crit_default', 'crit_category', 'threshold', 'quiz_score', 'score_history',
            'exammonitor', 'notif_log', 'email_tmpl', 'config_history',
        ];
        foreach ($tables as $name) {
            $table = new xmldb_table('local_quizmeta_' . $name);
            if ($dbman->table_exists($table) && !$dbman->table_exists('local_quizmetamonitor_' . $name)) {
                $dbman->rename_table($table, 'local_quizmetamonitor_' . $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026102900, 'local', 'quizmetamonitor');
    }

    return true;
}
