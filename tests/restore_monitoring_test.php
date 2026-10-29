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
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/quizmetamonitor/db/upgradelib.php');

/**
 * Restoring/importing quizzes fires only course_restored (no course_module_created).
 * The plugin must still score those quizzes and send the restoring teacher one summary email.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(event\observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(notification_manager::class)]
final class restore_monitoring_test extends \advanced_testcase {
    /** @var \stdClass Source course. */
    private $source;
    /** @var \stdClass Target course. */
    private $target;
    /** @var \stdClass Editing teacher in both courses. */
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

        $gen = $this->getDataGenerator();
        $this->source  = $gen->create_course(['fullname' => 'Source course']);
        $this->target  = $gen->create_course(['fullname' => 'Target course']);
        $this->teacher = $gen->create_user();
        $gen->enrol_user($this->teacher->id, $this->source->id, 'editingteacher');
        $gen->enrol_user($this->teacher->id, $this->target->id, 'editingteacher');
    }

    /**
     * Import the source course's content into the target course as $userid (Course > Import).
     *
     * @param int $userid User ID.
     */
    private function import_source_into_target(int $userid): void {
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->source->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $rc = new \restore_controller(
            $backupid,
            $this->target->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid,
            \backup::TARGET_CURRENT_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * Back up one activity or section to an .mbz file, then restore that file into the target
     * course as $userid: what a teacher does when bringing a quiz over from another Moodle server.
     *
     * @param string $type backup::TYPE_1ACTIVITY or backup::TYPE_1SECTION
     * @param int $id course module id or section id
     * @param int $userid User ID.
     */
    private function backup_file_and_restore_into_target(string $type, int $id, int $userid): void {
        global $CFG;

        $bc = new \backup_controller(
            $type,
            $id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        // Unpack the .mbz the way the Restore page does after an upload.
        $folder = 'quizmetamonitor_' . random_string(8);
        $file->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            $CFG->tempdir . '/backup/' . $folder
        );

        $rc = new \restore_controller(
            $folder,
            $this->target->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid,
            \backup::TARGET_CURRENT_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * Captured emails whose subject starts with a prefix.
     *
     * @param \phpunit_phpmailer_sink $sink Email sink.
     * @param string $prefix Subject prefix.
     * @return \stdClass[] Captured emails whose subject starts with $prefix.
     */
    private function emails_starting_with($sink, string $prefix): array {
        return array_values(array_filter($sink->get_messages(), fn($m) => str_starts_with($m->subject, $prefix)));
    }

    /**
     * Import scores restored quizzes and emails each quiz.
     */
    public function test_import_scores_restored_quizzes_and_emails_each_quiz(): void {
        global $DB;

        $this->setUser($this->teacher);
        $gen = $this->getDataGenerator();
        $gen->create_module('quiz', ['course' => $this->source->id, 'name' => 'Midterm exam']);
        $gen->create_module('quiz', ['course' => $this->source->id, 'name' => 'Final exam']);
        // A quiz already in the target course (scored on creation) must not be listed again.
        $existing = $gen->create_module('quiz', ['course' => $this->target->id, 'name' => 'Existing quiz']);

        $sink = $this->redirectEmails();
        $this->import_source_into_target((int) $this->teacher->id);

        // Every quiz in the target course now has a score, attributed to the teacher.
        $targetquizzes = $DB->get_records('quiz', ['course' => $this->target->id]);
        $this->assertCount(3, $targetquizzes);
        foreach ($targetquizzes as $quiz) {
            $this->assertTrue($DB->record_exists('local_quizmetamonitor_quiz_score', ['quizid' => $quiz->id]), $quiz->name);
        }
        $restored = array_filter($targetquizzes, fn($q) => $q->id != $existing->id);
        foreach ($restored as $quiz) {
            $this->assertTrue($DB->record_exists(
                'local_quizmetamonitor_score_history',
                ['quizid' => $quiz->id, 'changed_by_userid' => $this->teacher->id]
            ));
        }

        // One email per restored quiz (not for the quiz that was already there).
        $emails = $this->emails_starting_with($sink, 'Restored quiz:');
        $this->assertCount(2, $emails);
        $subjects = array_map(fn($m) => $m->subject, $emails);
        sort($subjects);
        $this->assertSame(['Restored quiz: Final exam', 'Restored quiz: Midterm exam'], $subjects);
        foreach ($emails as $email) {
            $this->assert_restored_email_has_table($email);
        }

        // The restore itself does not produce "created" emails.
        $this->assertCount(0, $this->emails_starting_with($sink, 'New quiz created:'));
    }

    /**
     * Single quiz backup restored is scored and emailed.
     */
    public function test_single_quiz_backup_restored_is_scored_and_emailed(): void {
        global $DB;

        $this->setUser($this->teacher);
        $onesession = (bool) \core_component::get_component_directory('quizaccess_onesession');
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->source->id,
            'name' => 'Quiz from server A',
            'onesessionenabled' => 1,
        ]);

        $sink = $this->redirectEmails();
        $this->backup_file_and_restore_into_target(\backup::TYPE_1ACTIVITY, (int) $quiz->cmid, (int) $this->teacher->id);

        $restored = $DB->get_record('quiz', ['course' => $this->target->id], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_quizmetamonitor_quiz_score', ['quizid' => $restored->id]));
        $this->assertTrue($DB->record_exists(
            'local_quizmetamonitor_score_history',
            ['quizid' => $restored->id, 'changed_by_userid' => $this->teacher->id]
        ));

        $emails = $this->emails_starting_with($sink, 'Restored quiz:');
        $this->assertCount(1, $emails);
        $this->assertSame('Restored quiz: Quiz from server A', $emails[0]->subject);
        $this->assert_restored_email_has_table($emails[0]);
        $this->assertStringContainsString(
            'has been restored or imported into the course',
            quoted_printable_decode($emails[0]->body)
        );
        // The Block concurrent setting travels in the backup, so it is already set when scored.
        if ($onesession) {
            $this->assertTrue($DB->record_exists('quizaccess_onesession', ['quizid' => $restored->id, 'enabled' => 1]));
            $this->assertMatchesRegularExpression(
                '~Block Concurrent Connections set</td>\s*<td[^>]*><span[^>]*>PASS<~',
                quoted_printable_decode($emails[0]->body)
            );
        }
        $this->assertCount(0, $this->emails_starting_with($sink, 'New quiz created:'));
    }

    /**
     * Section backup restored sends one email per quiz.
     */
    public function test_section_backup_restored_sends_one_email_per_quiz(): void {
        global $DB;

        $this->setUser($this->teacher);
        $gen = $this->getDataGenerator();
        $gen->create_module('quiz', ['course' => $this->source->id, 'section' => 1, 'name' => 'Section quiz 1']);
        $gen->create_module('quiz', ['course' => $this->source->id, 'section' => 1, 'name' => 'Section quiz 2']);
        $sectionid = $DB->get_field('course_sections', 'id', ['course' => $this->source->id, 'section' => 1], MUST_EXIST);

        $sink = $this->redirectEmails();
        $this->backup_file_and_restore_into_target(\backup::TYPE_1SECTION, (int) $sectionid, (int) $this->teacher->id);

        foreach ($DB->get_records('quiz', ['course' => $this->target->id]) as $quiz) {
            $this->assertTrue($DB->record_exists('local_quizmetamonitor_quiz_score', ['quizid' => $quiz->id]), $quiz->name);
        }
        $emails = $this->emails_starting_with($sink, 'Restored quiz:');
        $this->assertCount(2, $emails);
        foreach ($emails as $email) {
            $this->assert_restored_email_has_table($email);
        }
    }

    /**
     * Duplicate sends only the created email.
     */
    public function test_duplicate_sends_only_the_created_email(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->setUser($this->teacher);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->target->id, 'name' => 'Original']);
        $cm   = get_fast_modinfo($this->target)->get_cm($quiz->cmid);

        $sink = $this->redirectEmails();
        $newcm = duplicate_module($this->target, $cm);

        $this->assertTrue($DB->record_exists('local_quizmetamonitor_quiz_score', ['quizid' => $newcm->instance]));
        $this->assertCount(1, $this->emails_starting_with($sink, 'New quiz created:'));
        $this->assertCount(0, $this->emails_starting_with($sink, 'Restored quiz:'));
    }

    /**
     * Restore by non teacher scores but sends no email.
     */
    public function test_restore_by_non_teacher_scores_but_sends_no_email(): void {
        global $DB;

        // Simulate quizzes that arrived through a restore (no score yet).
        $this->setUser($this->teacher);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->target->id]);
        $DB->delete_records('local_quizmetamonitor_quiz_score', ['quizid' => $quiz->id]);

        // An admin (no teacher role in the course) performs the restore.
        $this->setAdminUser();
        $sink = $this->redirectEmails();
        \core\event\course_restored::create([
            'objectid' => $this->target->id,
            'userid'   => get_admin()->id,
            'context'  => \context_course::instance($this->target->id),
            'other'    => [
                'type'      => \backup::TYPE_1COURSE,
                'target'    => \backup::TARGET_EXISTING_ADDING,
                'mode'      => \backup::MODE_GENERAL,
                'operation' => \backup::OPERATION_RESTORE,
                'samesite'  => true,
            ],
        ])->trigger();

        $this->assertTrue($DB->record_exists('local_quizmetamonitor_quiz_score', ['quizid' => $quiz->id]));
        $this->assertCount(0, $this->emails_starting_with($sink, 'Restored quiz:'));
    }

    /**
     * Upgrade switches summary template to per quiz.
     */
    public function test_upgrade_switches_summary_template_to_per_quiz(): void {
        global $DB;

        // Simulate a site that got the 2024010107 summary template.
        $record = $DB->get_record(
            'local_quizmetamonitor_email_tmpl',
            ['template_key' => 'teacher_quizzes_restored'],
            '*',
            MUST_EXIST
        );
        $record->subject    = 'Restored quizzes to review: {course_name}';
        $record->body_html  = "<p>Hello,</p>\n<p>{quiz_count} quiz(zes) were restored or imported into the course "
            . "<strong>{course_name}</strong>. Restored quizzes keep the settings from the backup (dates, password, "
            . "Safe Exam Browser, etc.), so please review them.</p>\n{quiz_list}\n"
            . "<p>Please review the quiz settings to ensure compliance.</p>";
        $record->body_plain = "Hello,\n\n{quiz_count} quiz(zes) were restored or imported into the course \"{course_name}\". "
            . "Restored quizzes keep the settings from the backup (dates, password, Safe Exam Browser, etc.), "
            . "so please review them.\n\n{quiz_list}\n\nPlease review the quiz settings to ensure compliance.";
        $DB->update_record('local_quizmetamonitor_email_tmpl', $record);

        $this->assertTrue(local_quizmetamonitor_update_restored_template());

        $updated = $DB->get_record('local_quizmetamonitor_email_tmpl', ['template_key' => 'teacher_quizzes_restored']);
        $this->assertSame('Restored quiz: {quiz_name}', $updated->subject);
        $this->assertStringContainsString('{criteria_breakdown}', $updated->body_html);
        $this->assertStringNotContainsString('{quiz_list}', $updated->body_plain);

        // An admin-customised template is left alone.
        $DB->set_field(
            'local_quizmetamonitor_email_tmpl',
            'subject',
            'Our own: {quiz_name}',
            ['template_key' => 'teacher_quizzes_restored']
        );
        $this->assertFalse(local_quizmetamonitor_update_restored_template());
        $this->assertSame('Our own: {quiz_name}', $DB->get_field(
            'local_quizmetamonitor_email_tmpl',
            'subject',
            ['template_key' => 'teacher_quizzes_restored']
        ));
    }

    /**
     * The restored-quiz email goes to the teacher and has the same criteria table as "quiz created".
     *
     * @param \stdClass $email Captured email.
     */
    private function assert_restored_email_has_table(\stdClass $email): void {
        $this->assertSame($this->teacher->email, $email->to);
        $body = quoted_printable_decode($email->body);
        $this->assertStringContainsString('Security score:', $body);
        $this->assertStringContainsString('<h3>Criteria breakdown:</h3>', $body);
        $this->assertStringContainsString('<th style="border:1px solid #333333;padding:6px">Criterion</th>', $body);
        $this->assertStringContainsString('Safe Exam Browser - ', $body);
    }

    /**
     * Restored template is added once.
     */
    public function test_restored_template_is_added_once(): void {
        global $DB;

        $this->assertTrue($DB->record_exists('local_quizmetamonitor_email_tmpl', ['template_key' => 'teacher_quizzes_restored']));

        $DB->delete_records('local_quizmetamonitor_email_tmpl', ['template_key' => 'teacher_quizzes_restored']);
        local_quizmetamonitor_add_restored_template();
        local_quizmetamonitor_add_restored_template();

        $this->assertSame(
            1,
            $DB->count_records('local_quizmetamonitor_email_tmpl', ['template_key' => 'teacher_quizzes_restored'])
        );
    }
}
