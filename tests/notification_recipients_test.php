<?php
namespace local_quizmetamonitor;

defined('MOODLE_INTERNAL') || die();

/**
 * Checks that quiz created/edited emails go only to the teacher who made the change.
 *
 * @package    local_quizmetamonitor
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notification_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\observer::class)]
final class notification_recipients_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass Editing teacher. */
    private $teacher1;
    /** @var \stdClass Editing teacher. */
    private $teacher2;
    /** @var \stdClass Non-editing (assistant) teacher. */
    private $assistant1;
    /** @var \stdClass Non-editing (assistant) teacher. */
    private $assistant2;
    /** @var \stdClass */
    private $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->course     = $gen->create_course();
        $this->teacher1   = $gen->create_and_enrol($this->course, 'editingteacher', ['email' => 'teacher1@example.com']);
        $this->teacher2   = $gen->create_and_enrol($this->course, 'editingteacher', ['email' => 'teacher2@example.com']);
        $this->assistant1 = $gen->create_and_enrol($this->course, 'teacher', ['email' => 'assistant1@example.com']);
        $this->assistant2 = $gen->create_and_enrol($this->course, 'teacher', ['email' => 'assistant2@example.com']);
        $this->student    = $gen->create_and_enrol($this->course, 'student', ['email' => 'student@example.com']);
    }

    /**
     * Create a quiz as the given user (fires course_module_created).
     */
    private function create_quiz_as(\stdClass $user): \stdClass {
        $this->setUser($user);
        return $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
    }

    /**
     * Change a scored setting and fire course_module_updated as the given user.
     */
    private function edit_quiz_as(\stdClass $user, \stdClass $quiz): void {
        global $DB;

        $this->setUser($user);
        $DB->set_field('quiz', 'timeopen', time() + DAYSECS, ['id' => $quiz->id]);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
    }

    /**
     * Only this plugin's emails are considered (local_quiznotify also mails on the same events).
     *
     * @return string[] Recipient addresses of the captured quizmetamonitor emails.
     */
    private function recipients($sink): array {
        $ours = array_filter($sink->get_messages(), fn($m) =>
            str_starts_with($m->subject, 'New quiz created:')
            || str_starts_with($m->subject, 'Quiz security score changed:'));
        return array_values(array_map(fn($m) => $m->to, $ours));
    }

    public function test_editing_teacher_creates_quiz_only_they_are_emailed(): void {
        $sink = $this->redirectEmails();
        $quiz = $this->create_quiz_as($this->teacher1);

        $this->assertSame(['teacher1@example.com'], $this->recipients($sink));
        $this->assertSame(
            [(int) $this->teacher1->id],
            array_map('intval', $this->get_logged_userids($quiz->id, notification_manager::TYPE_TEACHER_CREATED))
        );
    }

    public function test_assistant_teacher_creates_quiz_only_they_are_emailed(): void {
        $sink = $this->redirectEmails();
        $this->create_quiz_as($this->assistant1);

        $this->assertSame(['assistant1@example.com'], $this->recipients($sink));
    }

    public function test_assistant_teacher_edits_quiz_only_they_are_emailed(): void {
        $quiz = $this->create_quiz_as($this->teacher1);

        $sink = $this->redirectEmails();
        $this->edit_quiz_as($this->assistant2, $quiz);

        $this->assertSame(['assistant2@example.com'], $this->recipients($sink));
    }

    public function test_edit_without_score_change_sends_nothing(): void {
        $quiz = $this->create_quiz_as($this->teacher1);

        $this->setUser($this->teacher2);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);

        $sink = $this->redirectEmails();
        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $this->assertSame([], $this->recipients($sink));
    }

    public function test_non_teacher_actor_receives_nothing(): void {
        // e.g. a site admin with no teacher role in the course.
        $sink = $this->redirectEmails();
        $this->create_quiz_as(get_admin());

        $this->assertSame([], $this->recipients($sink));
    }

    /**
     * @return int[]
     */
    private function get_logged_userids(int $quizid, string $type): array {
        global $DB;
        return $DB->get_fieldset_select(
            'local_quizmeta_notif_log',
            'userid',
            'quizid = :qid AND notification_type = :type',
            ['qid' => $quizid, 'type' => $type]
        );
    }
}
