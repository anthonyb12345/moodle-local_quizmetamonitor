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

global $CFG;
require_once($CFG->dirroot . '/local/quizmetamonitor/db/upgradelib.php');

/**
 * The teacher's "settings updated" email: sent whenever any security criterion changes,
 * even if the score stays the same, and the template reads correctly in both cases.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notification_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scoring_engine::class)]
final class settings_changed_email_test extends \advanced_testcase {
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

        // Observers email on quiz creation; output is already initialised on a real request.
        $PAGE->get_renderer('core');

        $this->course  = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
    }

    /**
     * Change quiz fields and fire course_module_updated, as saving the settings form does.
     *
     * @param \stdClass $quiz Quiz record.
     * @param array $fields Quiz field => value.
     */
    private function save_quiz(\stdClass $quiz, array $fields): void {
        global $DB;

        foreach ($fields as $field => $value) {
            $DB->set_field('quiz', $field, $value, ['id' => $quiz->id]);
        }
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
    }

    /**
     * Decoded bodies of the captured "settings updated" emails.
     *
     * @param \phpunit_phpmailer_sink $sink Email sink.
     * @return string[] Decoded bodies of the "settings updated" emails captured.
     */
    private function settings_emails($sink): array {
        $ours = array_filter(
            $sink->get_messages(),
            fn($m) => str_starts_with($m->subject, 'Quiz security settings updated:')
        );
        return array_values(array_map(fn($m) => quoted_printable_decode($m->body), $ours));
    }

    /**
     * Swapping criteria with same score sends email listing both changes.
     */
    public function test_swapping_criteria_with_same_score_sends_email_listing_both_changes(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $this->save_quiz($quiz, ['password' => 'secret', 'timeopen' => 0]);
        $before = scoring_engine::compute_score((int) $quiz->id)['score_percent'];

        // Teacher removes the password but adds an open date: score stays the same.
        $sink = $this->redirectEmails();
        $this->save_quiz($quiz, ['password' => '', 'timeopen' => time() + DAYSECS]);

        $this->assertSame($before, scoring_engine::compute_score((int) $quiz->id)['score_percent']);

        $bodies = $this->settings_emails($sink);
        $this->assertCount(1, $bodies);
        $this->assertStringContainsString($before . '% (unchanged)', $bodies[0]);
        $this->assertStringContainsString('<li>Password set: [PASS] → [FAIL]</li>', $bodies[0]);
        $this->assertStringContainsString('<li>Open date set: [FAIL] → [PASS]</li>', $bodies[0]);
    }

    /**
     * Score change shows previous and new score.
     */
    public function test_score_change_shows_previous_and_new_score(): void {
        $quiz   = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $before = scoring_engine::compute_score((int) $quiz->id)['score_percent'];

        $sink = $this->redirectEmails();
        $this->save_quiz($quiz, ['password' => 'secret']);
        $after = scoring_engine::compute_score((int) $quiz->id)['score_percent'];

        $bodies = $this->settings_emails($sink);
        $this->assertCount(1, $bodies);
        $this->assertStringContainsString($before . '% → ' . $after . '%', $bodies[0]);
        $this->assertStringContainsString('<li>Password set: [FAIL] → [PASS]</li>', $bodies[0]);
        $this->assertStringNotContainsString('(unchanged)', $bodies[0]);
    }

    /**
     * Upgrade replaces original template.
     */
    public function test_upgrade_replaces_original_template(): void {
        global $DB;

        // Simulate a site still on the original install text (with CRLF line endings).
        $record = $DB->get_record('local_quizmetamonitor_email_tmpl', ['template_key' => 'teacher_score_changed'], '*', MUST_EXIST);
        $record->subject    = 'Quiz security score changed: {quiz_name}';
        $record->body_html  = "<p>Hello,</p>\r\n<p>The quiz <strong>{quiz_name}</strong> in <strong>{course_name}</strong>"
            . " was recently edited.</p>\r\n<p>Previous score: {previous_score}% &rarr; "
            . "New score: <strong>{score_percent}%</strong>"
            . " (threshold: {threshold_percent}%)</p>\r\n<h3>Criteria breakdown:</h3>\r\n{criteria_breakdown}";
        $record->body_plain = "Hello,\n\nThe quiz \"{quiz_name}\" in \"{course_name}\" was recently edited.\n\n"
            . "Previous score: {previous_score}% -> New score: {score_percent}% (threshold: {threshold_percent}%)\n\n"
            . "Criteria breakdown:\n{criteria_breakdown}";
        $DB->update_record('local_quizmetamonitor_email_tmpl', $record);

        $this->assertTrue(local_quizmetamonitor_update_settings_changed_template());

        $updated = $DB->get_record('local_quizmetamonitor_email_tmpl', ['template_key' => 'teacher_score_changed']);
        $this->assertSame('Quiz security settings updated: {quiz_name}', $updated->subject);
        $this->assertStringContainsString('{criteria_changes}', $updated->body_html);
        $this->assertStringContainsString('{score_change}', $updated->body_plain);
    }

    /**
     * Upgrade keeps customised template.
     */
    public function test_upgrade_keeps_customised_template(): void {
        global $DB;

        $DB->set_field(
            'local_quizmetamonitor_email_tmpl',
            'subject',
            'Our own subject: {quiz_name}',
            ['template_key' => 'teacher_score_changed']
        );

        $this->assertFalse(local_quizmetamonitor_update_settings_changed_template());
        $this->assertSame('Our own subject: {quiz_name}', $DB->get_field(
            'local_quizmetamonitor_email_tmpl',
            'subject',
            ['template_key' => 'teacher_score_changed']
        ));
    }
}
