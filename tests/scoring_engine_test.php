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

namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the block_concurrent_set criterion, backed by quizaccess_onesession
 * ("Block concurrent sessions" quiz access rule).
 *
 * Quizzes are created through the normal course module APIs so the access rule saves its
 * own setting and the plugin observers score the quiz. The tests only rely on what this
 * plugin reads (a quizaccess_onesession row with enabled = 1), so they pass with the
 * standard access rule as well as with sites that customise it to be always on.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scoring_engine::class)]
final class scoring_engine_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;

    /**
     * Set up the course, users and environment used by the tests.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        // The plugin's observers are external, so on PostgreSQL they only run once the test's
        // transaction commits; reset the slow way so they run as on a real site.
        $this->preventResetByRollback();

        if (!\core_component::get_component_directory('quizaccess_onesession')) {
            $this->markTestSkipped('quizaccess_onesession is not installed.');
        }

        // Observers email on quiz creation; output is already initialised on a real request.
        $PAGE->get_renderer('core');

        $this->course  = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
    }

    /**
     * Create a quiz in the test course, setting "Block concurrent sessions".
     *
     * @param bool $onesession Submitted value of the setting.
     * @return \stdClass
     */
    private function create_quiz(bool $onesession): \stdClass {
        return $this->getDataGenerator()->create_module('quiz', [
            'course'            => $this->course->id,
            'onesessionenabled' => $onesession ? 1 : 0,
        ]);
    }

    /**
     * Store the "Block concurrent sessions" setting directly and fire course_module_updated,
     * as saving the quiz settings form does.
     *
     * @param \stdClass $quiz Quiz record.
     * @param bool $enabled Whether the setting is on.
     */
    private function save_setting(\stdClass $quiz, bool $enabled): void {
        global $DB;

        $DB->delete_records('quizaccess_onesession', ['quizid' => $quiz->id]);
        if ($enabled) {
            $DB->insert_record('quizaccess_onesession', ['quizid' => $quiz->id, 'enabled' => 1]);
        }
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
    }

    /**
     * Read block_concurrent_set from the score the observers stored for the quiz.
     *
     * @param int $quizid Quiz ID.
     * @return bool
     */
    private function stored_block_concurrent_met(int $quizid): bool {
        global $DB;

        $snapshot = $DB->get_field('local_quizmetamonitor_quiz_score', 'criteria_snapshot', ['quizid' => $quizid], MUST_EXIST);
        foreach (json_decode($snapshot, true) as $item) {
            if ($item['key'] === 'block_concurrent_set') {
                return (bool) $item['met'];
            }
        }
        $this->fail('block_concurrent_set missing from stored breakdown');
    }

    /**
     * Quiz created has block concurrent scored.
     */
    public function test_quiz_created_has_block_concurrent_scored(): void {
        global $DB;

        $quiz = $this->create_quiz(true);

        $this->assertTrue($DB->record_exists('quizaccess_onesession', ['quizid' => $quiz->id, 'enabled' => 1]));
        $this->assertTrue($this->stored_block_concurrent_met((int) $quiz->id));
    }

    /**
     * A disabled setting is scored as not met.
     */
    public function test_disabled_setting_is_not_scored(): void {
        $quiz = $this->create_quiz(true);

        $this->save_setting($quiz, false);

        $this->assertFalse($this->stored_block_concurrent_met((int) $quiz->id));
    }

    /**
     * Switching the setting on rescores the quiz and emails the change to the editor.
     */
    public function test_switching_it_on_is_scored_and_emailed(): void {
        $quiz = $this->create_quiz(true);
        $this->save_setting($quiz, false);

        $sink = $this->redirectEmails();
        $this->save_setting($quiz, true);

        $this->assertTrue($this->stored_block_concurrent_met((int) $quiz->id));
        $ours = array_values(array_filter(
            $sink->get_messages(),
            fn($m) => str_starts_with($m->subject, 'Quiz security settings updated:')
        ));
        $this->assertCount(1, $ours);
        $this->assertStringContainsString(
            'Block Concurrent Connections set: [FAIL] → [PASS]',
            quoted_printable_decode($ours[0]->body)
        );
    }

    /**
     * Creation email shows block concurrent pass.
     */
    public function test_creation_email_shows_block_concurrent_pass(): void {
        $sink = $this->redirectEmails();
        $this->create_quiz(true);

        $ours = array_values(array_filter(
            $sink->get_messages(),
            fn($m) => str_starts_with($m->subject, 'New quiz created:')
        ));
        $this->assertCount(1, $ours);
        $this->assertSame($this->teacher->email, $ours[0]->to);
        $this->assertMatchesRegularExpression(
            '~Block Concurrent Connections set</td>\s*<td[^>]*><span[^>]*>PASS<~',
            quoted_printable_decode($ours[0]->body)
        );
    }

    /**
     * Quiz without onesession row is not scored.
     */
    public function test_quiz_without_onesession_row_is_not_scored(): void {
        global $DB;

        // E.g. a quiz created before quizaccess_onesession was installed.
        $quiz = $this->create_quiz(true);
        $DB->delete_records('quizaccess_onesession', ['quizid' => $quiz->id]);

        $result = scoring_engine::compute_score((int) $quiz->id);
        $item   = array_values(array_filter($result['breakdown'], fn($i) => $i['key'] === 'block_concurrent_set'))[0];

        $this->assertFalse($item['met']);
    }
}
