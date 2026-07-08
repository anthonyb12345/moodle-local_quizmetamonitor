<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_quizmetamonitor_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024010103) {

        // ------------------------------------------------------------ //
        // New table: local_quizmeta_config_history                      //
        // Stores a record every time criteria or threshold is changed.  //
        // ------------------------------------------------------------ //
        $table = new xmldb_table('local_quizmeta_config_history');

        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('change_type',  XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL); // 'criteria' or 'threshold'
        $table->add_field('categoryid',   XMLDB_TYPE_INTEGER, '10',  null, null);           // null = site default
        $table->add_field('changed_by',   XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
        $table->add_field('description',  XMLDB_TYPE_TEXT,    null,  null, XMLDB_NOTNULL); // admin's reason
        $table->add_field('snapshot',     XMLDB_TYPE_TEXT,    null,  null, XMLDB_NOTNULL); // JSON of new state
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('change_type_idx', XMLDB_INDEX_NOTUNIQUE, ['change_type']);
        $table->add_index('categoryid_idx',  XMLDB_INDEX_NOTUNIQUE, ['categoryid']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024010103, 'local', 'quizmetamonitor');
    }

    return true;
}