<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Restore hook: scores quizzes restored from a single-activity or section backup.
 *
 * Moodle fires course_restored only for course-type backups, so a quiz backup restored on
 * its own (e.g. brought over from another Moodle server via the quiz's Backup page) would
 * otherwise stay unscored and unannounced. Moodle calls after_restore_module() for every
 * restored activity, whether or not the backup file contains data from this plugin.
 *
 * @package    local_quizmetamonitor
 */
class restore_local_quizmetamonitor_plugin extends restore_local_plugin {

    /**
     * Registers this plugin with the restore of each activity. Moodle only calls
     * after_restore_module() for plugins that declare a path; the path itself never
     * has to exist in the backup file (this plugin adds nothing to backups).
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [new restore_path_element('quizmetamonitor', $this->get_pathfor('/quizmetamonitor'))];
    }

    /**
     * Data for the path above; never present in backups, nothing to restore.
     *
     * @param array $data
     */
    public function process_quizmetamonitor($data) {
    }

    /**
     * Runs once per restored activity, at the end of the restore.
     */
    protected function after_restore_module() {
        if ($this->task->get_modulename() !== 'quiz') {
            return;
        }

        $info = $this->task->get_info();

        // Course backups, Course > Import and course copy fire course_restored, which handles
        // all their quizzes (and runs after this hook).
        if ($info->type === backup::TYPE_1COURSE) {
            return;
        }
        // "Duplicate" is a single-activity import; it also fires course_module_created, which
        // already scores the quiz and sends the normal "created" email.
        if ($info->type === backup::TYPE_1ACTIVITY && (int) $info->mode === backup::MODE_IMPORT) {
            return;
        }

        \local_quizmetamonitor\restore_monitor::process(
            [(int) $this->task->get_activityid()],
            (int) $this->task->get_courseid(),
            (int) $this->task->get_userid()
        );
    }
}
