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

use quizaccess_seb\settings_provider;

/**
 * The Safe Exam Browser criterion never deducts points: "No" is a valid choice
 * (open book / software allowed), so both options get full points and the
 * breakdown label shows which one was picked.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scoring_engine::class)]
final class seb_criterion_test extends \advanced_testcase {
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
     * Create a quiz in the test course with the given Safe Exam Browser option.
     *
     * @param int $requireseb settings_provider::USE_SEB_* value.
     * @return \stdClass
     */
    private function create_quiz(int $requireseb): \stdClass {
        return $this->getDataGenerator()->create_module('quiz', [
            'course'                     => $this->course->id,
            'seb_requiresafeexambrowser' => $requireseb,
        ]);
    }

    /**
     * Score a quiz and return its Safe Exam Browser breakdown item.
     *
     * @param \stdClass $quiz Quiz record.
     * @return array
     */
    private function seb_item(\stdClass $quiz): array {
        foreach (scoring_engine::compute_score((int) $quiz->id)['breakdown'] as $item) {
            if ($item['key'] === 'seb_enabled') {
                return $item;
            }
        }
        $this->fail('seb_enabled missing from breakdown');
    }

    /**
     * Seb no gets full points and no label.
     */
    public function test_seb_no_gets_full_points_and_no_label(): void {
        $item = $this->seb_item($this->create_quiz(settings_provider::USE_SEB_NO));

        $this->assertSame('Safe Exam Browser - No - Unrestricted use of software, websites, or documents', $item['label']);
        $this->assertSame('no', $item['choice']);
        $this->assertTrue($item['met']);
        $this->assertSame($item['points_possible'], $item['points_earned']);
    }

    /**
     * Seb yes gets full points and yes label.
     */
    public function test_seb_yes_gets_full_points_and_yes_label(): void {
        $item = $this->seb_item($this->create_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY));

        $this->assertSame('Safe Exam Browser - Yes - Configure manually', $item['label']);
        $this->assertSame('manual', $item['choice']);
        $this->assertTrue($item['met']);
        $this->assertSame($item['points_possible'], $item['points_earned']);
    }

    /**
     * Each seb yes option has its own label.
     */
    public function test_each_seb_yes_option_has_its_own_label(): void {
        global $DB;

        $expected = [
            settings_provider::USE_SEB_CONFIG_MANUALLY => ['manual', 'Safe Exam Browser - Yes - Configure manually'],
            settings_provider::USE_SEB_TEMPLATE        => ['template', 'Safe Exam Browser - Yes - Use an existing template'],
            settings_provider::USE_SEB_UPLOAD_CONFIG   => ['upload', 'Safe Exam Browser - Yes - Upload my own config'],
            settings_provider::USE_SEB_CLIENT_CONFIG   => ['client', 'Safe Exam Browser - Yes - Use SEB client config'],
        ];

        $quiz = $this->create_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY);
        foreach ($expected as $mode => [$choice, $label]) {
            // Template/upload need extra files to save through the form, so set the stored mode directly.
            $DB->set_field('quizaccess_seb_quizsettings', 'requiresafeexambrowser', $mode, ['quizid' => $quiz->id]);
            $item = $this->seb_item($quiz);

            $this->assertSame($choice, $item['choice'], "mode $mode");
            $this->assertSame($label, scoring_engine::display_label($item), "mode $mode");
            $this->assertSame($item['points_possible'], $item['points_earned'], "mode $mode");
        }
    }

    /**
     * Switching between yes options emails both options.
     */
    public function test_switching_between_yes_options_emails_both_options(): void {
        global $DB;

        $quiz = $this->create_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY);

        $sink = $this->redirectEmails();
        $DB->set_field(
            'quizaccess_seb_quizsettings',
            'requiresafeexambrowser',
            settings_provider::USE_SEB_CLIENT_CONFIG,
            ['quizid' => $quiz->id]
        );
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $ours = array_values(array_filter(
            $sink->get_messages(),
            fn($m) => str_starts_with($m->subject, 'Quiz security settings updated:')
        ));
        $this->assertCount(1, $ours);
        $this->assertStringContainsString(
            '<li>[Safe Exam Browser - Yes - Configure manually] → [Safe Exam Browser - Yes - Use SEB client config]</li>',
            quoted_printable_decode($ours[0]->body)
        );
    }

    /**
     * Scores stored with old yes choice still display.
     */
    public function test_scores_stored_with_old_yes_choice_still_display(): void {
        $item = ['key' => 'seb_enabled', 'label' => 'Safe Exam Browser - Yes', 'choice' => 'yes'];

        $this->assertSame('Safe Exam Browser - Yes', scoring_engine::display_label($item));
    }

    /**
     * Seb choice does not change score.
     */
    public function test_seb_choice_does_not_change_score(): void {
        $without = scoring_engine::compute_score((int) $this->create_quiz(settings_provider::USE_SEB_NO)->id);
        $with    = scoring_engine::compute_score((int) $this->create_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY)->id);

        $this->assertSame($with['score_percent'], $without['score_percent']);
    }

    /**
     * Switching seb on edit emails editor even though score is same.
     */
    public function test_switching_seb_on_edit_emails_editor_even_though_score_is_same(): void {
        global $DB;

        $quiz   = $this->create_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY);
        $before = scoring_engine::compute_score((int) $quiz->id)['score_percent'];

        // Teacher switches SEB to "No" and saves the quiz settings.
        $sink = $this->redirectEmails();
        $DB->set_field(
            'quizaccess_seb_quizsettings',
            'requiresafeexambrowser',
            settings_provider::USE_SEB_NO,
            ['quizid' => $quiz->id]
        );
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $this->assertSame($before, scoring_engine::compute_score((int) $quiz->id)['score_percent']);

        $ours = array_values(array_filter(
            $sink->get_messages(),
            fn($m) => str_starts_with($m->subject, 'Quiz security settings updated:')
        ));
        $this->assertCount(1, $ours);
        $this->assertSame($this->teacher->email, $ours[0]->to);
        $this->assertStringContainsString(
            'Safe Exam Browser - No - Unrestricted use of software, websites, or documents',
            quoted_printable_decode($ours[0]->body)
        );
    }

    /**
     * Label is resolved from choice at display time.
     */
    public function test_label_is_resolved_from_choice_at_display_time(): void {
        // The stored label is whatever language was active when scored; display uses the choice.
        $item = ['key' => 'seb_enabled', 'label' => 'Safe Exam Browser - Non, ...', 'choice' => 'no'];

        $this->assertSame(
            'Safe Exam Browser - No - Unrestricted use of software, websites, or documents',
            scoring_engine::display_label($item)
        );

        // Other criteria keep their stored (admin-configured) label.
        $this->assertSame('Open date set', scoring_engine::display_label(['key' => 'open_date_set', 'label' => 'Open date set']));
    }

    /**
     * Label follows reader language.
     */
    public function test_label_follows_reader_language(): void {
        global $CFG;

        // PHPUnit has its own dataroot without the site's language packs. Register a minimal
        // French pack there (removed again by the dataroot reset); this plugin's own French
        // strings come from local/quizmetamonitor/lang/fr.
        if (!get_string_manager()->translation_exists('fr')) {
            make_writable_directory($CFG->dataroot . '/lang/fr');
            file_put_contents(
                $CFG->dataroot . '/lang/fr/langconfig.php',
                "<?php\n\$string['thislanguage'] = 'Français';\n\$string['parentlanguage'] = '';\n"
            );
            get_string_manager()->reset_caches(); // The installed-languages list is cached.
        }
        $this->assertTrue(get_string_manager()->translation_exists('fr'));

        $yes = ['key' => 'seb_enabled', 'label' => 'Safe Exam Browser - Yes', 'choice' => 'yes'];
        $no  = ['key' => 'seb_enabled', 'label' => 'Safe Exam Browser - No', 'choice' => 'no'];
        $live = [
            'manual' => 'Safe Exam Browser - Oui - Configurer manuellement',
            'upload' => 'Safe Exam Browser - Oui - Déposer ma propre configuration',
            'client' => 'Safe Exam Browser - Oui - Utiliser la configuration du client SEB',
        ];

        $previous = force_current_language('fr');
        $yeslabel = scoring_engine::display_label($yes);
        $nolabel  = scoring_engine::display_label($no);
        $livelabels = [];
        foreach (array_keys($live) as $choice) {
            $livelabels[$choice] = scoring_engine::display_label(['key' => 'seb_enabled', 'label' => '', 'choice' => $choice]);
        }
        force_current_language($previous);

        $this->assertSame($live, $livelabels);
        $this->assertSame('Safe Exam Browser - Oui', $yeslabel);
        $this->assertSame('Safe Exam Browser - Non - Utilisation libre des logiciels, site web ou documents', $nolabel);
    }

    /**
     * Decoded bodies of the teacher "New quiz created" emails captured by $sink.
     *
     * @param \phpunit_phpmailer_sink $sink Email sink.
     * @return string[]
     */
    private function created_email_bodies($sink): array {
        $ours = array_filter($sink->get_messages(), fn($m) => str_starts_with($m->subject, 'New quiz created:'));
        return array_values(array_map(fn($m) => quoted_printable_decode($m->body), $ours));
    }

    /**
     * Seb no adds notice after table.
     */
    public function test_seb_no_adds_notice_after_table(): void {
        $sink = $this->redirectEmails();
        $this->create_quiz(settings_provider::USE_SEB_NO);

        $bodies = $this->created_email_bodies($sink);
        $this->assertCount(1, $bodies);
        // HTML part: bold kept, right after the criteria table.
        $this->assertMatchesRegularExpression(
            '~</table><p>You have chosen <b>NOT to use Safe Exam Browser</b>, which is <b>ONLY applicable~',
            $bodies[0]
        );
        // Plain-text part: same sentence without tags.
        $this->assertStringContainsString(
            'You have chosen NOT to use Safe Exam Browser, which is ONLY applicable',
            str_replace(["\r\n", "\n"], ' ', $bodies[0])
        );
    }

    /**
     * Seb yes has no notice.
     */
    public function test_seb_yes_has_no_notice(): void {
        $sink = $this->redirectEmails();
        $this->create_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY);

        $bodies = $this->created_email_bodies($sink);
        $this->assertCount(1, $bodies);
        $this->assertStringNotContainsString('NOT to use Safe Exam Browser', $bodies[0]);
    }

    /**
     * Exam monitor alert has no notice.
     */
    public function test_exam_monitor_alert_has_no_notice(): void {
        global $DB;

        $quiz    = $this->create_quiz(settings_provider::USE_SEB_NO);
        $result  = scoring_engine::compute_score((int) $quiz->id);
        $monitor = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_quizmetamonitor_exammonitor', [
            'userid' => $monitor->id, 'categoryid' => $result['categoryid'],
            'assignedby' => get_admin()->id, 'timecreated' => time(),
        ]);

        $sink = $this->redirectEmails();
        notification_manager::notify_exam_monitors_alert(
            $result,
            $DB->get_record('quiz', ['id' => $quiz->id]),
            $this->course,
            'Category'
        );

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame($monitor->email, $messages[0]->to);
        $this->assertStringNotContainsString('NOT to use Safe Exam Browser', quoted_printable_decode($messages[0]->body));
    }

    /**
     * Email table shows chosen option.
     */
    public function test_email_table_shows_chosen_option(): void {
        $sink = $this->redirectEmails();
        $this->create_quiz(settings_provider::USE_SEB_NO);

        $ours = array_values(array_filter(
            $sink->get_messages(),
            fn($m) => str_starts_with($m->subject, 'New quiz created:')
        ));
        $this->assertCount(1, $ours);

        $body = quoted_printable_decode($ours[0]->body);
        $this->assertMatchesRegularExpression(
            '~Safe Exam Browser - No - Unrestricted use of software, websites, or documents</td>\s*<td[^>]*><span[^>]*>PASS<~',
            $body
        );
        $this->assertStringNotContainsString('Safe Exam Browser enabled', $body);
    }
}
