<?php
namespace local_quizmetamonitor\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Flexible table for quiz score lists — shared by admin, monitor, and teacher views.
 */
class quiz_table extends \table_sql {

    /** @var bool Whether to show the category column */
    private bool $showcategory;

    /** @var bool Whether to show the course column */
    private bool $showcourse;

    public function __construct(
        string $uniqueid,
        bool $showcategory = true,
        bool $showcourse   = true
    ) {
        parent::__construct($uniqueid);

        $this->showcategory = $showcategory;
        $this->showcourse   = $showcourse;

        $columns = ['quizname'];
        $headers = [get_string('quizname', 'local_quizmetamonitor')];

        if ($showcourse) {
            $columns[] = 'coursename';
            $headers[] = get_string('course');
        }

        if ($showcategory) {
            $columns[] = 'categoryname';
            $headers[] = get_string('category');
        }

        $columns = array_merge($columns, [
            'timeopen', 'timeclose',
            'score_percent', 'threshold_applied',
            'is_below_threshold', 'actions',
        ]);
        $headers = array_merge($headers, [
            get_string('opendate',      'local_quizmetamonitor'),
            get_string('closedate',     'local_quizmetamonitor'),
            get_string('score',         'local_quizmetamonitor'),
            get_string('threshold',     'local_quizmetamonitor'),
            get_string('status',        'local_quizmetamonitor'),
            get_string('actions',       'local_quizmetamonitor'),
        ]);

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->sortable(true, 'score_percent', SORT_ASC);
        $this->no_sorting('actions');
        $this->pageable(true);
        $this->collapsible(false);
        $this->set_attribute('class', 'generaltable quizmetamonitor-table');
    }

    // ---- Column renderers ------------------------------------------- //

    public function col_quizname(\stdClass $row): string {
        $detailurl = new \moodle_url(
            '/local/quizmetamonitor/pages/quiz_detail.php',
            ['quizid' => $row->quizid]
        );
        return \html_writer::link($detailurl, format_string($row->quizname));
    }

    public function col_coursename(\stdClass $row): string {
        return format_string($row->coursename ?? '');
    }

    public function col_categoryname(\stdClass $row): string {
        return format_string($row->categoryname ?? '');
    }

    public function col_timeopen(\stdClass $row): string {
        return !empty($row->timeopen) ? userdate($row->timeopen) : '—';
    }

    public function col_timeclose(\stdClass $row): string {
        return !empty($row->timeclose) ? userdate($row->timeclose) : '—';
    }

    public function col_score_percent(\stdClass $row): string {
        return round($row->score_percent, 1) . '%';
    }

    public function col_threshold_applied(\stdClass $row): string {
        return round($row->threshold_applied, 1) . '%';
    }

    public function col_is_below_threshold(\stdClass $row): string {
        if ($row->is_below_threshold) {
            return \html_writer::tag(
                'span',
                get_string('belowthreshold', 'local_quizmetamonitor'),
                ['class' => 'badge badge-danger qmm-badge-danger']
            );
        }
        return \html_writer::tag(
            'span',
            get_string('ok', 'local_quizmetamonitor'),
            ['class' => 'badge badge-success qmm-badge-ok']
        );
    }

    public function col_actions(\stdClass $row): string {
        $detailurl = new \moodle_url(
            '/local/quizmetamonitor/pages/quiz_detail.php',
            ['quizid' => $row->quizid]
        );
        return \html_writer::link(
            $detailurl,
            get_string('viewdetails', 'local_quizmetamonitor'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        );
    }
}