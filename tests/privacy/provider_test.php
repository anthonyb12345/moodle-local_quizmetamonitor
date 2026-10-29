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

namespace local_quizmetamonitor\privacy;

use context_coursecat;
use context_module;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the local_quizmetamonitor privacy provider.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass */
    private $course;
    /** @var \stdClass Teacher who creates the quiz. */
    private $teacher;
    /** @var \stdClass */
    private $quiz;
    /** @var context_module */
    private $quizcontext;

    /**
     * Set up a course with a quiz created by a teacher (score history + notification log rows).
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();

        // Observers email on quiz creation; output is already initialised on a real request.
        $PAGE->get_renderer('core');

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);
        $sink = $this->redirectEmails();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $sink->close();
        $this->quizcontext = context_module::instance($this->quiz->cmid);
        $this->setUser(null);
    }

    /**
     * Assign $userid as Exam Monitor of the course's category, by $assignedby.
     *
     * @param int $userid Exam Monitor.
     * @param int $assignedby User making the assignment.
     * @return context_coursecat
     */
    private function assign_monitor(int $userid, int $assignedby): context_coursecat {
        global $DB;

        $DB->insert_record('local_quizmeta_exammonitor', [
            'userid' => $userid, 'categoryid' => $this->course->category,
            'assignedby' => $assignedby, 'timecreated' => time(),
        ]);
        return context_coursecat::instance($this->course->category);
    }

    /**
     * Metadata describes all four tables.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('local_quizmetamonitor'))->get_collection();
        $tables = array_map(fn($item) => $item->get_name(), $items);
        sort($tables);
        $this->assertSame([
            'local_quizmeta_config_history', 'local_quizmeta_exammonitor',
            'local_quizmeta_notif_log', 'local_quizmeta_score_history',
        ], $tables);
    }

    /**
     * The quiz creator has data in the quiz context, and is listed as a user there.
     */
    public function test_contexts_and_users_for_quiz_creator(): void {
        $contextids = provider::get_contexts_for_userid($this->teacher->id)->get_contextids();
        $this->assertContains((string) $this->quizcontext->id, array_map('strval', $contextids));

        $userlist = new userlist($this->quizcontext, 'local_quizmetamonitor');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $this->teacher->id, array_map('intval', $userlist->get_userids()));
    }

    /**
     * Export contains the quiz creator's score history and notifications.
     */
    public function test_export_user_data(): void {
        $contextlist = provider::get_contexts_for_userid($this->teacher->id);
        provider::export_user_data(new approved_contextlist(
            $this->teacher,
            'local_quizmetamonitor',
            $contextlist->get_contextids()
        ));

        $writer = writer::with_context($this->quizcontext);
        $this->assertTrue($writer->has_any_data());
        $history = $writer->get_data([get_string('privacy:path:scorehistory', 'local_quizmetamonitor')]);
        $this->assertNotEmpty($history->changes);
        $notifications = $writer->get_data([get_string('privacy:path:notifications', 'local_quizmetamonitor')]);
        $this->assertNotEmpty($notifications->notifications);
    }

    /**
     * Deleting a user's data removes their notifications and anonymises the score history.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $contextlist = provider::get_contexts_for_userid($this->teacher->id);
        provider::delete_data_for_user(new approved_contextlist(
            $this->teacher,
            'local_quizmetamonitor',
            $contextlist->get_contextids()
        ));

        $this->assertFalse($DB->record_exists('local_quizmeta_notif_log', ['userid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists('local_quizmeta_score_history', ['changed_by_userid' => $this->teacher->id]));
        // The scores themselves stay.
        $this->assertTrue($DB->record_exists('local_quizmeta_score_history', ['quizid' => $this->quiz->id]));
    }

    /**
     * Exam Monitor assignments: exported, and deleted for the monitor while the assigner is anonymised.
     */
    public function test_exam_monitor_assignment(): void {
        global $DB;

        $monitor = $this->getDataGenerator()->create_user();
        $admin = get_admin();
        $catcontext = $this->assign_monitor($monitor->id, $admin->id);

        $contextids = provider::get_contexts_for_userid($monitor->id)->get_contextids();
        $this->assertContains((string) $catcontext->id, array_map('strval', $contextids));

        provider::export_user_data(new approved_contextlist($monitor, 'local_quizmetamonitor', [$catcontext->id]));
        $data = writer::with_context($catcontext)->get_data([get_string('privacy:path:exammonitor', 'local_quizmetamonitor')]);
        $this->assertCount(1, $data->assignments);

        // Deleting the admin's data keeps the assignment but forgets who made it.
        provider::delete_data_for_users(new approved_userlist($catcontext, 'local_quizmetamonitor', [$admin->id]));
        $this->assertTrue($DB->record_exists('local_quizmeta_exammonitor', ['userid' => $monitor->id, 'assignedby' => 0]));

        // Deleting the monitor's data removes the assignment.
        provider::delete_data_for_users(new approved_userlist($catcontext, 'local_quizmetamonitor', [$monitor->id]));
        $this->assertFalse($DB->record_exists('local_quizmeta_exammonitor', ['userid' => $monitor->id]));
    }

    /**
     * Site-default configuration changes live in the system context and are anonymised on deletion.
     */
    public function test_config_history_in_system_context(): void {
        global $DB;

        $admin = get_admin();
        \local_quizmetamonitor\config_history::save('threshold', null, (int) $admin->id, 'Raised for exams', ['threshold' => 80]);

        $contextids = provider::get_contexts_for_userid($admin->id)->get_contextids();
        $this->assertContains((string) context_system::instance()->id, array_map('strval', $contextids));

        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assertFalse($DB->record_exists('local_quizmeta_config_history', ['changed_by' => $admin->id]));
        $this->assertTrue($DB->record_exists_select(
            'local_quizmeta_config_history',
            $DB->sql_compare_text('description') . ' = ' . $DB->sql_compare_text(':description'),
            ['description' => 'Raised for exams']
        ));
    }

    /**
     * Deleting everything in the quiz context removes all notifications for that quiz.
     */
    public function test_delete_data_for_all_users_in_quiz_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->quizcontext);
        $this->assertFalse($DB->record_exists('local_quizmeta_notif_log', ['quizid' => $this->quiz->id]));
        $this->assertFalse($DB->record_exists_select(
            'local_quizmeta_score_history',
            'quizid = :quizid AND changed_by_userid > 0',
            ['quizid' => $this->quiz->id]
        ));
    }
}
