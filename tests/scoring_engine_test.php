<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the block_concurrent_set criterion, backed by quizaccess_onesession
 * ("Block concurrent sessions" quiz access rule).
 *
 * The site's quizaccess_onesession is customised to always enforce the rule: every
 * saved quiz gets it enabled regardless of the submitted form value. Quizzes are
 * created and edited through the normal course module APIs so the access rule saves
 * its own setting and the plugin observers score the quiz.
 *
 * @package    local_quizmetamonitor
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scoring_engine::class)]
final class scoring_engine_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();

        if (!\core_component::get_component_directory('quizaccess_onesession')) {
            $this->markTestSkipped('quizaccess_onesession is not installed.');
        }

        // Observers email on quiz creation; output is already initialised on a real request.
        $PAGE->get_renderer('core');

        $this->course  = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
    }

    private function create_quiz(bool $onesession): \stdClass {
        return $this->getDataGenerator()->create_module('quiz', [
            'course'            => $this->course->id,
            'onesessionenabled' => $onesession ? 1 : 0,
        ]);
    }

    /**
     * Save the quiz settings form the way mod/quiz does, submitting "Block concurrent sessions".
     */
    private function edit_quiz(\stdClass $quiz, bool $onesession): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        [$cm, $context, $module, $data] = get_moduleinfo_data(
            get_coursemodule_from_id('quiz', $quiz->cmid, 0, false, MUST_EXIST),
            $this->course
        );
        $data->onesessionenabled = $onesession ? 1 : 0;
        $data->quizpassword      = $data->password ?? '';

        update_moduleinfo($cm, $data, $this->course);
    }

    /**
     * Read block_concurrent_set from the score the observers stored for the quiz.
     */
    private function stored_block_concurrent_met(int $quizid): bool {
        global $DB;

        $snapshot = $DB->get_field('local_quizmeta_quiz_score', 'criteria_snapshot', ['quizid' => $quizid], MUST_EXIST);
        foreach (json_decode($snapshot, true) as $item) {
            if ($item['key'] === 'block_concurrent_set') {
                return (bool) $item['met'];
            }
        }
        $this->fail('block_concurrent_set missing from stored breakdown');
    }

    public function test_quiz_created_has_block_concurrent_scored(): void {
        global $DB;

        $quiz = $this->create_quiz(true);

        $this->assertTrue($DB->record_exists('quizaccess_onesession', ['quizid' => $quiz->id, 'enabled' => 1]));
        $this->assertTrue($this->stored_block_concurrent_met((int) $quiz->id));
    }

    public function test_quiz_created_with_it_off_is_still_enforced_and_scored(): void {
        global $DB;

        $quiz = $this->create_quiz(false);

        $this->assertTrue($DB->record_exists('quizaccess_onesession', ['quizid' => $quiz->id, 'enabled' => 1]));
        $this->assertTrue($this->stored_block_concurrent_met((int) $quiz->id));
    }

    public function test_edit_cannot_switch_it_off(): void {
        $quiz = $this->create_quiz(true);

        $this->edit_quiz($quiz, false);

        $this->assertTrue($this->stored_block_concurrent_met((int) $quiz->id));
    }

    public function test_creation_email_shows_block_concurrent_pass(): void {
        $sink = $this->redirectEmails();
        $this->create_quiz(true);

        $ours = array_values(array_filter($sink->get_messages(),
            fn($m) => str_starts_with($m->subject, 'New quiz created:')));
        $this->assertCount(1, $ours);
        $this->assertSame($this->teacher->email, $ours[0]->to);
        $this->assertMatchesRegularExpression(
            '~Block Concurrent Connections set</td>\s*<td[^>]*><span[^>]*>PASS<~',
            quoted_printable_decode($ours[0]->body)
        );
    }

    public function test_quiz_without_onesession_row_is_not_scored(): void {
        global $DB;

        // e.g. a quiz created before quizaccess_onesession was installed.
        $quiz = $this->create_quiz(true);
        $DB->delete_records('quizaccess_onesession', ['quizid' => $quiz->id]);

        $result = scoring_engine::compute_score((int) $quiz->id);
        $item   = array_values(array_filter($result['breakdown'], fn($i) => $i['key'] === 'block_concurrent_set'))[0];

        $this->assertFalse($item['met']);
    }
}
