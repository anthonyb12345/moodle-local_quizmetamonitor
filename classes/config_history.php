<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles saving and retrieving configuration change history
 * for both scoring criteria and threshold changes.
 */
class config_history {

    const TYPE_CRITERIA  = 'criteria';
    const TYPE_THRESHOLD = 'threshold';

    /**
     * Save a history record when criteria or threshold changes.
     *
     * @param string   $type        'criteria' or 'threshold'
     * @param int|null $categoryid  null = site default
     * @param int      $changedby   userid of admin making the change
     * @param string   $description Admin's written reason for the change
     * @param array    $snapshot    The new state as an array (will be JSON encoded)
     */
    public static function save(
        string $type,
        ?int $categoryid,
        int $changedby,
        string $description,
        array $snapshot
    ): void {
        global $DB;

        $DB->insert_record('local_quizmeta_config_history', (object)[
            'change_type' => $type,
            'categoryid'  => $categoryid,
            'changed_by'  => $changedby,
            'description' => $description,
            'snapshot'    => json_encode($snapshot),
            'timecreated' => time(),
        ]);
    }

    /**
     * Get history records for a given type and category.
     * Returns most recent first, limited to $limit rows.
     *
     * @param  string   $type
     * @param  int|null $categoryid
     * @param  int      $limit
     * @return array
     */
    public static function get_history(
        string $type,
        ?int $categoryid,
        int $limit = 20
    ): array {
        global $DB;

        $conditions = ['change_type' => $type];

        // categoryid can be null (site default) — handle separately.
        if ($categoryid === null) {
            $sql = "SELECT ch.*, u.firstname, u.lastname,
                           u.firstnamephonetic, u.lastnamephonetic,
                           u.middlename, u.alternatename
                      FROM {local_quizmeta_config_history} ch
                      JOIN {user} u ON u.id = ch.changed_by
                     WHERE ch.change_type = :type
                       AND ch.categoryid IS NULL
                  ORDER BY ch.timecreated DESC";
            $params = ['type' => $type];
        } else {
            $sql = "SELECT ch.*, u.firstname, u.lastname,
                           u.firstnamephonetic, u.lastnamephonetic,
                           u.middlename, u.alternatename
                      FROM {local_quizmeta_config_history} ch
                      JOIN {user} u ON u.id = ch.changed_by
                     WHERE ch.change_type = :type
                       AND ch.categoryid  = :categoryid
                  ORDER BY ch.timecreated DESC";
            $params = ['type' => $type, 'categoryid' => $categoryid];
        }

        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Render the history table as HTML.
     * Called from both admin_criteria.php and admin_threshold.php.
     *
     * @param  array  $history      Records from get_history()
     * @param  string $type         'criteria' or 'threshold'
     * @return string HTML
     */
    public static function render_history_table(array $history, string $type): string {
        global $OUTPUT;

        if (empty($history)) {
            return $OUTPUT->notification(
                get_string('nochangehistory', 'local_quizmetamonitor'),
                'info'
            );
        }

        $table              = new \html_table();
        $table->attributes  = ['class' => 'generaltable table table-sm table-striped'];

        if ($type === self::TYPE_CRITERIA) {
            $table->head = [
                get_string('date'),
                get_string('changedby',       'local_quizmetamonitor'),
                get_string('changereason',    'local_quizmetamonitor'),
                get_string('newcriteria',     'local_quizmetamonitor'),
            ];
        } else {
            $table->head = [
                get_string('date'),
                get_string('changedby',       'local_quizmetamonitor'),
                get_string('changereason',    'local_quizmetamonitor'),
                get_string('newthreshold',    'local_quizmetamonitor'),
            ];
        }

        foreach ($history as $record) {
            $snapshot = json_decode($record->snapshot, true) ?? [];

            if ($type === self::TYPE_CRITERIA) {
                $snapshothtml = self::render_criteria_snapshot($snapshot);
            } else {
                $snapshothtml = self::render_threshold_snapshot($snapshot);
            }

            $table->data[] = [
                userdate($record->timecreated),
                fullname($record),
                \html_writer::tag(
                    'span',
                    format_text($record->description, FORMAT_PLAIN),
                    ['class' => 'text-muted font-italic']
                ),
                $snapshothtml,
            ];
        }

        return \html_writer::table($table);
    }

    // ---------------------------------------------------------------- //
    // Private snapshot renderers                                        //
    // ---------------------------------------------------------------- //

    private static function render_criteria_snapshot(array $snapshot): string {
        if (empty($snapshot)) {
            return '—';
        }

        $items = [];
        foreach ($snapshot as $key => $def) {
            $enabled = $def['enabled'] ? '✓' : '✗';
            $color   = $def['enabled'] ? '#2e7d32' : '#c62828';
            $items[] = \html_writer::tag(
                'span',
                sprintf(
                    '<span style="color:%s">%s</span> %s (%s pts)',
                    $color,
                    $enabled,
                    htmlspecialchars($def['label'], ENT_QUOTES),
                    $def['points']
                ),
                ['class' => 'd-block small']
            );
        }

        return implode('', $items);
    }

    private static function render_threshold_snapshot(array $snapshot): string {
        if (!isset($snapshot['threshold_percent'])) {
            return '—';
        }
        return \html_writer::tag(
            'strong',
            $snapshot['threshold_percent'] . '%'
        );
    }
}