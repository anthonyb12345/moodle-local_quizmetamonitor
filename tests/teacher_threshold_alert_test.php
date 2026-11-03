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

use local_quizmetamonitor\task\check_upcoming_quiz_threshold;

/**
 * Below-threshold alert (quiz opens within 24 hours) to the users who created or edited the quiz.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(check_upcoming_quiz_threshold::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(notification_manager::class)]
final class teacher_threshold_alert_test extends \advanced_testcase {
    /** Subject prefix of the teacher alert. */
    private const SUBJECT = 'ALERT: Your quiz is below the security threshold';

    /** @var \stdClass */
    private $course;
    /** @var \stdClass Editing teacher who creates the quiz. */
    private $teacher;
    /** @var \stdClass Assistant (non-editing) teacher who edits it. */
    private $assistant;
    /** @var \stdClass Another teacher in the course who never touches it. */
    private $otherteacher;

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

        $gen = $this->getDataGenerator();
        $this->course       = $gen->create_course();
        $this->teacher      = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->assistant    = $gen->create_and_enrol($this->course, 'teacher');
        $this->otherteacher = $gen->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Teacher creates a quiz (default settings score well below the 70% threshold).
     *
     * @return \stdClass
     */
    private function create_quiz(): \stdClass {
        $this->setUser($this->teacher);
        return $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'name' => 'Exam']);
    }

    /**
     * $editor saves the quiz settings with an open date $opensin seconds from now.
     *
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $editor User saving the settings.
     * @param int $opensin Seconds from now until the quiz opens.
     */
    private function edit_quiz(\stdClass $quiz, \stdClass $editor, int $opensin): void {
        global $DB;

        $this->setUser($editor);
        $DB->set_field('quiz', 'timeopen', time() + $opensin, ['id' => $quiz->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
    }

    /**
     * Run the 30-minute task and return the recipients of the teacher alert.
     *
     * @return string[]
     */
    private function run_task_and_get_alert_recipients(): array {
        $sink = $this->redirectEmails();
        (new check_upcoming_quiz_threshold())->execute();

        $alerts = array_filter($sink->get_messages(), fn($m) => str_starts_with($m->subject, self::SUBJECT));
        $to = array_values(array_map(fn($m) => $m->to, $alerts));
        sort($to);
        $sink->close();
        return $to;
    }

    /**
     * Creator and editor are alerted other teachers are not.
     */
    public function test_creator_and_editor_are_alerted_other_teachers_are_not(): void {
        $quiz = $this->create_quiz();
        $this->edit_quiz($quiz, $this->assistant, 2 * HOURSECS);

        $expected = [$this->teacher->email, $this->assistant->email];
        sort($expected);
        $this->assertSame($expected, $this->run_task_and_get_alert_recipients());
    }

    /**
     * Alert has criteria table and is sent once.
     */
    public function test_alert_has_criteria_table_and_is_sent_once(): void {
        $quiz = $this->create_quiz();
        $this->edit_quiz($quiz, $this->teacher, 2 * HOURSECS);

        $sink = $this->redirectEmails();
        (new check_upcoming_quiz_threshold())->execute();
        $alerts = array_values(array_filter($sink->get_messages(), fn($m) => str_starts_with($m->subject, self::SUBJECT)));
        $sink->close();

        $this->assertCount(1, $alerts);
        $this->assertSame(self::SUBJECT . ' — Exam', $alerts[0]->subject);
        $body = quoted_printable_decode($alerts[0]->body);
        $this->assertStringContainsString('opens within 24 hours', $body);
        $this->assertStringContainsString('<h3>Criteria breakdown:</h3>', $body);
        $this->assertStringContainsString('Block Concurrent Connections set', $body);

        // The task runs every 30 minutes; the same person is not alerted again for this quiz.
        $this->assertSame([], $this->run_task_and_get_alert_recipients());
    }

    /**
     * Quiz opening later than 24 hours is not alerted.
     */
    public function test_quiz_opening_later_than_24_hours_is_not_alerted(): void {
        $quiz = $this->create_quiz();
        $this->edit_quiz($quiz, $this->teacher, 3 * DAYSECS);

        $this->assertSame([], $this->run_task_and_get_alert_recipients());
    }

    /**
     * Any role that edited is alerted.
     */
    public function test_any_role_that_edited_is_alerted(): void {
        $manager = $this->getDataGenerator()->create_and_enrol($this->course, 'manager');
        $quiz = $this->create_quiz();
        $this->edit_quiz($quiz, $manager, 2 * HOURSECS);

        $expected = [$this->teacher->email, $manager->email];
        sort($expected);
        $this->assertSame($expected, $this->run_task_and_get_alert_recipients());
    }

    /**
     * Suspended editor is not alerted.
     */
    public function test_suspended_editor_is_not_alerted(): void {
        global $DB;

        $quiz = $this->create_quiz();
        $this->edit_quiz($quiz, $this->assistant, 2 * HOURSECS);
        $DB->set_field('user', 'suspended', 1, ['id' => $this->assistant->id]);

        $this->assertSame([$this->teacher->email], $this->run_task_and_get_alert_recipients());
    }
}
