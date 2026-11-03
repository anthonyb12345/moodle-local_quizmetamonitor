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

/**
 * Strings for component 'local_quizmetamonitor', language 'en'.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Actions';
$string['activefilters'] = 'Active filters';
$string['addmonitor'] = 'Add Exam Monitor';
$string['adminoverview'] = 'Quiz metadata overview';
$string['allcategories'] = 'All categories';
$string['allcourses'] = 'All courses';
$string['allstatuses'] = 'All statuses';
$string['alreadyscored'] = 'Already have a score';
$string['anytime'] = 'any date';
$string['applyfilters'] = 'Apply filters';
$string['assign'] = 'Assign';
$string['assignmonitors'] = 'Assign Exam Monitors';
$string['backfill'] = 'Backfill existing quizzes';
$string['backfill_complete'] = 'Backfill complete. Total: {$a->total} | Success: {$a->success} | Failed: {$a->failed}';
$string['backfill_description'] = 'This tool scores all existing quizzes that were created before the plugin was installed. No emails will be sent — scores will simply be computed and stored so they appear in the monitoring tables.';
$string['backfill_error'] = 'Error: {$a}';
$string['backfill_newonly'] = 'Score {$a} unscored quizzes';
$string['backfill_nomail'] = 'No emails will be sent during backfill. This only computes and stores security scores.';
$string['backfill_rescore_all'] = 'Rescore all {$a} quizzes (overwrite existing scores)';
$string['backfill_scored'] = 'Scored';
$string['backfill_unknownquiz'] = 'Unknown';
$string['belowthreshold'] = 'BELOW THRESHOLD';
$string['belowthresholdcount'] = 'Quizzes below threshold';
$string['bodyhtml'] = 'Body (HTML)';
$string['bodyplain'] = 'Body (plain text)';
$string['changedby'] = 'Changed by';
$string['changehistory'] = 'Change history';
$string['changereason'] = 'Reason for change';
$string['changereason_help'] = 'This description is required and will be stored in the change history for audit purposes.';
$string['changereason_placeholder'] = 'Describe why you are making this change, e.g. "Removed IP restriction criterion for online courses category as it is not applicable."';
$string['choosecategory'] = 'Choose a category...';
$string['closedate'] = 'Close date';
$string['configurecriteria'] = 'Configure scoring criteria';
$string['configurethreshold'] = 'Configure alert threshold';
$string['confirmremove'] = 'Are you sure you want to remove this Exam Monitor assignment?';
$string['criteria_nochange'] = 'No criteria changed (the scoring configuration was updated).';
$string['criteriabreakdown'] = 'Criteria breakdown';
$string['criterion'] = 'Criterion';
$string['csv_no'] = 'No';
$string['csv_nolimit'] = 'No limit';
$string['csv_none'] = 'None';
$string['csv_notapplicable'] = 'N/A';
$string['csv_seconds'] = '{$a}s';
$string['csv_unlimited'] = 'Unlimited';
$string['csv_yes'] = 'Yes';
$string['csvheader_allowedattempts'] = 'Allowed Attempts';
$string['csvheader_belowthreshold'] = 'Below Threshold';
$string['csvheader_category'] = 'Category';
$string['csvheader_closedate'] = 'Close Date';
$string['csvheader_coursename'] = 'Course Name';
$string['csvheader_courseshortname'] = 'Course Short Name';
$string['csvheader_critattempts'] = 'Criterion: Attempts Limited';
$string['csvheader_critblockconcurrent'] = 'Criterion: Block Concurrent';
$string['csvheader_critclosedate'] = 'Criterion: Close Date Set';
$string['csvheader_critip'] = 'Criterion: IP Restriction Set';
$string['csvheader_critopendate'] = 'Criterion: Open Date Set';
$string['csvheader_critpassword'] = 'Criterion: Password Set';
$string['csvheader_critseb'] = 'Criterion: SEB Enabled';
$string['csvheader_iprestriction'] = 'IP Restriction';
$string['csvheader_lastcomputed'] = 'Last Computed';
$string['csvheader_maxgrade'] = 'Max Grade';
$string['csvheader_opendate'] = 'Open Date';
$string['csvheader_passwordset'] = 'Password Set';
$string['csvheader_quizname'] = 'Quiz Name';
$string['csvheader_score'] = 'Security Score (%)';
$string['csvheader_shuffleanswers'] = 'Shuffle Answers';
$string['csvheader_threshold'] = 'Threshold (%)';
$string['csvheader_timelimit'] = 'Time Limit';
$string['currentassignments'] = 'Current Exam Monitor assignments';
$string['currentcriteria'] = 'Current scoring criteria';
$string['currentthreshold'] = 'Current threshold';
$string['dateassigned'] = 'Date assigned';
$string['downloadcsv'] = 'Download CSV report';
$string['editingcategorycriteria'] = 'You are editing criteria for the category: {$a}. This overrides the site default for this category only.';
$string['editingdefaultcriteria'] = 'You are editing the site-wide default scoring criteria. This applies to all categories that do not have their own override.';
$string['editquiz'] = 'Edit quiz settings';
$string['email_col_criterion'] = 'Criterion';
$string['email_col_points'] = 'Points';
$string['email_col_status'] = 'Status';
$string['email_fail'] = 'FAIL';
$string['email_pass'] = 'PASS';
$string['email_points'] = '{$a->earned} / {$a->possible} pts';
$string['emailhelp'] = 'The user must already have a Moodle account with this email.';
$string['emailtemplates'] = 'Email templates';
$string['enabled'] = 'Enabled';
$string['enteruseremail'] = 'Enter the user\'s email address';
$string['enteruserid'] = 'Enter user ID';
$string['error_alreadyassigned'] = '{$a} is already assigned as Exam Monitor for this category.';
$string['error_datefromafterdate'] = '"Opens From" date cannot be after "Opens Until" date.';
$string['error_descriptionrequired'] = 'A reason for the change is required before saving.';
$string['error_emailrequired'] = 'Please enter an email address.';
$string['error_invaliddate'] = 'One or more dates entered are not valid. Please use the date picker.';
$string['error_selectcategory'] = 'Please select a category.';
$string['error_usernotfound'] = 'No Moodle user found with email address: {$a}';
$string['examrole_description'] = 'Can view and edit quizzes within their assigned category. Cannot access other course resources.';
$string['examrole_name'] = 'Exam Monitor (Quiz Metadata)';
$string['exportdescription'] = 'The CSV export includes all quiz metadata fields, security criteria results, scores, and dates.';
$string['exportfilters'] = 'Export filters';
$string['exportpreview'] = 'Preview (first 10 rows)';
$string['exportpreviewcount'] = '{$a} quiz(zes) match the current filters';
$string['exportpreviewlimited'] = 'Showing {$a->shown} of {$a->total} quizzes. Download the CSV to see all results.';
$string['exportreport'] = 'Export report';
$string['fail'] = 'FAIL';
$string['filterbycategory'] = 'Filter by category';
$string['filterbycourse'] = 'Filter by course';
$string['filterbystatus'] = 'Filter by status';
$string['filterquizzes'] = 'Filter quizzes';
$string['gotoquiz'] = 'Go to quiz';
$string['monitor_assigned'] = 'Exam Monitor assigned successfully: {$a}';
$string['monitor_removed'] = 'Exam Monitor assignment removed.';
$string['monitorcategory'] = 'Category quiz monitor';
$string['myquizzes'] = 'My quiz security scores';
$string['newcriteria'] = 'New criteria state';
$string['newthreshold'] = 'New threshold';
$string['noassignments'] = 'No Exam Monitors are currently assigned.';
$string['nochangehistory'] = 'No changes have been recorded yet.';
$string['noquizzesmatchfilter'] = 'No quizzes match the selected filters.';
$string['nothresholdoverride'] = 'No override set for this category. Site default ({$a}%) applies.';
$string['ok'] = 'OK';
$string['opendate'] = 'Open date';
$string['pass'] = 'PASS';
$string['placeholder_help'] = 'Available placeholders: {quiz_name}, {course_name}, {category_name}, {score_percent}, {previous_score}, {threshold_percent}, {criteria_breakdown}. Settings-changed email only: {score_change} (e.g. "71.43% → 85.71%" or "85.71% (unchanged)"), {criteria_changes} (list of the criteria that changed). The restored quiz email (one per quiz) uses the same placeholders as the quiz created email.';
$string['pluginname'] = 'Quiz Metadata Monitor';
$string['points'] = 'Points';
$string['pointsearned'] = 'Points earned';
$string['pointspossible'] = 'Points possible';
$string['privacy:metadata:local_quizmetamonitor_config_history'] = 'History of changes to the scoring criteria and alert threshold.';
$string['privacy:metadata:local_quizmetamonitor_config_history:categoryid'] = 'The ID of the course category the change applies to (empty for the site default).';
$string['privacy:metadata:local_quizmetamonitor_config_history:change_type'] = 'Whether the scoring criteria or the threshold was changed.';
$string['privacy:metadata:local_quizmetamonitor_config_history:changed_by'] = 'The ID of the user who made the change.';
$string['privacy:metadata:local_quizmetamonitor_config_history:description'] = 'The reason given for the change.';
$string['privacy:metadata:local_quizmetamonitor_config_history:timecreated'] = 'The time the change was made.';
$string['privacy:metadata:local_quizmetamonitor_exammonitor'] = 'Exam Monitor assignments to course categories.';
$string['privacy:metadata:local_quizmetamonitor_exammonitor:assignedby'] = 'The ID of the user who made the assignment.';
$string['privacy:metadata:local_quizmetamonitor_exammonitor:categoryid'] = 'The ID of the course category.';
$string['privacy:metadata:local_quizmetamonitor_exammonitor:timecreated'] = 'The time the assignment was made.';
$string['privacy:metadata:local_quizmetamonitor_exammonitor:userid'] = 'The ID of the user assigned as Exam Monitor.';
$string['privacy:metadata:local_quizmetamonitor_notif_log'] = 'Log of the notification emails sent by the plugin.';
$string['privacy:metadata:local_quizmetamonitor_notif_log:notification_type'] = 'The type of notification sent.';
$string['privacy:metadata:local_quizmetamonitor_notif_log:quizid'] = 'The ID of the quiz the notification was about.';
$string['privacy:metadata:local_quizmetamonitor_notif_log:score_percent'] = 'The security score of the quiz when the notification was sent.';
$string['privacy:metadata:local_quizmetamonitor_notif_log:timesent'] = 'The time the notification was sent.';
$string['privacy:metadata:local_quizmetamonitor_notif_log:userid'] = 'The ID of the user the notification was sent to.';
$string['privacy:metadata:local_quizmetamonitor_score_history'] = 'History of quiz security scores, recording who created, edited or restored each quiz.';
$string['privacy:metadata:local_quizmetamonitor_score_history:changed_by_userid'] = 'The ID of the user who created, edited or restored the quiz.';
$string['privacy:metadata:local_quizmetamonitor_score_history:quizid'] = 'The ID of the quiz.';
$string['privacy:metadata:local_quizmetamonitor_score_history:score_percent'] = 'The security score of the quiz at that time.';
$string['privacy:metadata:local_quizmetamonitor_score_history:timecreated'] = 'The time the score was recorded.';
$string['privacy:path:confighistory'] = 'Configuration changes';
$string['privacy:path:exammonitor'] = 'Exam Monitor assignments';
$string['privacy:path:notifications'] = 'Notifications';
$string['privacy:path:scorehistory'] = 'Score history';
$string['quizdetail'] = 'Quiz security detail';
$string['quizmetamonitor:configure'] = 'Configure Quiz Metadata Monitor settings';
$string['quizmetamonitor:receiveexamalert'] = 'Receive exam monitor threshold alerts';
$string['quizmetamonitor:receiveteacheralert'] = 'Receive teacher notification emails';
$string['quizmetamonitor:viewall'] = 'View all quiz metadata site-wide';
$string['quizmetamonitor:viewcategory'] = 'View quiz metadata for assigned category';
$string['quizmetamonitor:viewown'] = 'View own quiz metadata';
$string['quizname'] = 'Quiz name';
$string['quizstartdaterange'] = 'Quiz start date range';
$string['quizstartdaterange_help'] = 'Filter quizzes whose start (open) date falls between these two dates.';
$string['quizzes'] = 'quizzes';
$string['quizzesok'] = 'Quizzes OK';
$string['remove'] = 'Remove';
$string['resetfilters'] = 'Reset';
$string['resettodefault'] = 'Reset to site default';
$string['score'] = 'Security score';
$string['score_changed_from'] = '{$a->previous}% → {$a->current}%';
$string['score_unchanged'] = '{$a}% (unchanged)';
$string['scorehistory'] = 'Score History (last 10)';
$string['seb_choice_client'] = 'Safe Exam Browser - Yes - Use SEB client config';
$string['seb_choice_manual'] = 'Safe Exam Browser - Yes - Configure manually';
$string['seb_choice_no'] = 'Safe Exam Browser - No - Unrestricted use of software, websites, or documents';
$string['seb_choice_template'] = 'Safe Exam Browser - Yes - Use an existing template';
$string['seb_choice_upload'] = 'Safe Exam Browser - Yes - Upload my own config';
$string['seb_choice_yes'] = 'Safe Exam Browser - Yes';
$string['seb_no_notice'] = 'You have chosen <b>NOT to use Safe Exam Browser</b>, which is <b>ONLY applicable when students need unrestricted access </b>(such as the case with open-book exams). Note that, open-book exams can still use SEB: printed materials are always allowed, and specific websites or applications can be permitted through SEB configuration.';
$string['selectcategory'] = 'Assign to category';
$string['selectcategorylabel'] = 'Viewing configuration for:';
$string['showingquizzes'] = 'Showing {$a} quiz(zes)';
$string['sitedefault'] = 'Site default';
$string['startdatefrom'] = 'From';
$string['startdateto'] = 'To';
$string['status'] = 'Status';
$string['subject'] = 'Subject';
$string['system'] = 'System (cron)';
$string['task_check_threshold'] = 'Check upcoming quizzes below threshold';
$string['task_resync_scores'] = 'Resync all quiz security scores (nightly)';
$string['template_exammonitor_alert'] = 'Exam Monitor — Below threshold alert';
$string['template_teacher_alert'] = 'Teacher — Below threshold alert (quiz opens within 24 hours)';
$string['template_teacher_created'] = 'Teacher — Quiz created';
$string['template_teacher_restored'] = 'Teacher — Quizzes restored or imported';
$string['template_teacher_scorechanged'] = 'Teacher — Security settings changed';
$string['threshold'] = 'Threshold';
$string['thresholdexplain'] = 'Quizzes scoring below this percentage will trigger Exam Monitor alerts.';
$string['thresholdpercent'] = 'Threshold (%)';
$string['totalquizzes'] = 'Total quizzes tracked';
$string['totalquizzesindb'] = 'Total quizzes in system';
$string['unscoredquizzes'] = 'Not yet scored';
$string['useremail'] = 'User email address';
$string['userid'] = 'User ID';
$string['viewdetails'] = 'View details';
$string['viewquizdetail'] = 'view this quiz detail';
