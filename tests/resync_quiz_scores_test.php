<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

use local_quizmetamonitor\task\resync_quiz_scores;

/**
 * Checks the nightly resync skips quizzes created before the plugin was installed.
 *
 * @package    local_quizmetamonitor
 */
#[\PHPUnit\Framework\Attributes\CoversClass(resync_quiz_scores::class)]
final class resync_quiz_scores_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var int */
    private $trackfrom;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();

        // Observers email on quiz creation; output is already initialised on a real request.
        $PAGE->get_renderer('core');

        $this->course    = $this->getDataGenerator()->create_course();
        $this->trackfrom = time() - HOURSECS;
        set_config('trackfrom', $this->trackfrom, 'local_quizmetamonitor');
    }

    /**
     * A quiz that existed before the plugin and nobody has touched since
     * (scored only by system/backfill, userid 0).
     */
    private function create_old_untouched_quiz(): \stdClass {
        global $DB;

        $this->setUser(null);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $DB->set_field('quiz', 'timecreated', $this->trackfrom - YEARSECS, ['id' => $quiz->id]);
        return $quiz;
    }

    /**
     * The resync writes one history row per quiz it rescores.
     */
    private function resync_touched(\stdClass $quiz): bool {
        global $DB;

        $before = $DB->count_records('local_quizmeta_score_history', ['quizid' => $quiz->id]);
        (new resync_quiz_scores())->execute();
        $after  = $DB->count_records('local_quizmeta_score_history', ['quizid' => $quiz->id]);

        return $after > $before;
    }

    public function test_trackfrom_falls_back_to_install_time(): void {
        global $DB;

        // Existing installs have no 'trackfrom' config; the seeded criteria rows date the install.
        unset_config('trackfrom', 'local_quizmetamonitor');
        $installed = (int) $DB->get_field_sql('SELECT MIN(timecreated) FROM {local_quizmeta_crit_default}');

        $this->assertGreaterThan(0, $installed);
        $this->assertSame($installed, resync_quiz_scores::get_trackfrom());
    }

    public function test_quiz_created_after_install_is_resynced(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        $this->assertTrue($this->resync_touched($quiz));
    }

    public function test_quiz_older_than_install_is_skipped(): void {
        $quiz = $this->create_old_untouched_quiz();

        $this->assertFalse($this->resync_touched($quiz));
    }

    public function test_older_quiz_edited_by_teacher_is_resynced(): void {
        // e.g. a restored course copy keeps the old timecreated but a teacher has edited it.
        $quiz    = $this->create_old_untouched_quiz();
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $this->assertTrue($this->resync_touched($quiz));
    }

    public function test_older_quiz_without_any_score_is_resynced(): void {
        global $DB;

        // e.g. restored from an old backup and missed by the observers: old timecreated, no score.
        $quiz = $this->create_old_untouched_quiz();
        $DB->delete_records('local_quizmeta_quiz_score', ['quizid' => $quiz->id]);

        (new resync_quiz_scores())->execute();

        $this->assertTrue($DB->record_exists('local_quizmeta_quiz_score', ['quizid' => $quiz->id]));
    }

    public function test_trackfrom_zero_resyncs_all_quizzes(): void {
        $quiz = $this->create_old_untouched_quiz();
        set_config('trackfrom', 0, 'local_quizmetamonitor');

        $this->assertTrue($this->resync_touched($quiz));
    }
}
