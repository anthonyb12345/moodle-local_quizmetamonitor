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

namespace local_quizmetamonitor\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Flexible table for quiz score lists — shared by admin, monitor, and teacher views.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_table extends \table_sql {
    /** @var bool Whether to show the category column */
    private bool $showcategory;

    /** @var bool Whether to show the course column */
    private bool $showcourse;

    /**
     * Set up the table columns and headers.
     *
     * @param string $uniqueid Unique id of the table.
     * @param bool $showcategory Whether to show the category column.
     * @param bool $showcourse Whether to show the course column.
     */
    public function __construct(
        string $uniqueid,
        bool $showcategory = true,
        bool $showcourse = true
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
            get_string('opendate', 'local_quizmetamonitor'),
            get_string('closedate', 'local_quizmetamonitor'),
            get_string('score', 'local_quizmetamonitor'),
            get_string('threshold', 'local_quizmetamonitor'),
            get_string('status', 'local_quizmetamonitor'),
            get_string('actions', 'local_quizmetamonitor'),
        ]);

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->sortable(true, 'score_percent', SORT_ASC);
        $this->no_sorting('actions');
        $this->pageable(true);
        $this->collapsible(false);
        $this->set_attribute('class', 'generaltable quizmetamonitor-table');
    }

    // Column renderers.

    /**
     * Render the quizname column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_quizname(\stdClass $row): string {
        $detailurl = new \moodle_url(
            '/local/quizmetamonitor/pages/quiz_detail.php',
            ['quizid' => $row->quizid]
        );
        return \html_writer::link($detailurl, format_string($row->quizname));
    }

    /**
     * Render the coursename column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_coursename(\stdClass $row): string {
        return format_string($row->coursename ?? '');
    }

    /**
     * Render the categoryname column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_categoryname(\stdClass $row): string {
        return format_string($row->categoryname ?? '');
    }

    /**
     * Render the timeopen column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_timeopen(\stdClass $row): string {
        return !empty($row->timeopen) ? userdate($row->timeopen) : '—';
    }

    /**
     * Render the timeclose column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_timeclose(\stdClass $row): string {
        return !empty($row->timeclose) ? userdate($row->timeclose) : '—';
    }

    /**
     * Render the score_percent column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_score_percent(\stdClass $row): string {
        return round($row->score_percent, 1) . '%';
    }

    /**
     * Render the threshold_applied column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
    public function col_threshold_applied(\stdClass $row): string {
        return round($row->threshold_applied, 1) . '%';
    }

    /**
     * Render the is_below_threshold column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
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

    /**
     * Render the actions column.
     *
     * @param \stdClass $row Table row.
     * @return string
     */
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
