<?php
defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'Quiz Metadata Monitor';

// Navigation / page titles.
$string['adminoverview']      = 'Quiz Metadata Overview';
$string['configurecriteria']  = 'Configure Scoring Criteria';
$string['configurethreshold'] = 'Configure Alert Threshold';
$string['assignmonitors']     = 'Assign Exam Monitors';
$string['emailtemplates']     = 'Email Templates';
$string['exportreport']       = 'Export Report';
$string['monitorcategory']    = 'Category Quiz Monitor';
$string['myquizzes']          = 'My Quiz Security Scores';
$string['quizdetail']         = 'Quiz Security Detail';

// Table columns.
$string['quizname']       = 'Quiz Name';
$string['score']          = 'Security Score';
$string['threshold']      = 'Threshold';
$string['status']         = 'Status';
$string['actions']        = 'Actions';
$string['opendate']       = 'Open Date';
$string['closedate']      = 'Close Date';
$string['pointspossible'] = 'Points Possible';
$string['pointsearned']   = 'Points Earned';
$string['criterion']      = 'Criterion';
$string['criteriabreakdown'] = 'Criteria Breakdown';
$string['scorehistory']   = 'Score History (last 10)';
$string['changedby']      = 'Changed By';

// Status labels.
$string['belowthreshold'] = 'BELOW THRESHOLD';
$string['ok']             = 'OK';
$string['pass']           = 'PASS';
$string['fail']           = 'FAIL';

// Buttons / links.
$string['viewdetails']    = 'View Details';
$string['downloadcsv']    = 'Download CSV Report';
$string['assign']         = 'Assign';
$string['remove']         = 'Remove';
$string['resettodefault'] = 'Reset to Site Default';
$string['gotoquiz']       = 'Go to Quiz';
$string['editquiz']       = 'Edit Quiz Settings';

// Form labels.
$string['sitedefault']       = 'Site Default';
$string['thresholdpercent']  = 'Threshold (%)';
$string['points']            = 'Points';
$string['enabled']           = 'Enabled';
$string['subject']           = 'Subject';
$string['bodyhtml']          = 'Body (HTML)';
$string['bodyplain']         = 'Body (Plain Text)';
$string['allcourses']        = 'All Courses';
$string['userid']            = 'User ID';
$string['enteruserid']       = 'Enter user ID';
$string['dateassigned']      = 'Date Assigned';
$string['noassignments']     = 'No Exam Monitors are currently assigned.';
$string['nothresholdoverride'] = 'No override set for this category. Site default ({$a}%) applies.';

// Email templates.
$string['template_teacher_created']    = 'Teacher — Quiz Created';
$string['template_teacher_scorechanged'] = 'Teacher — Score Changed';
$string['template_exammonitor_alert']  = 'Exam Monitor — Below Threshold Alert';
$string['placeholder_help']            =
    'Available placeholders: {quiz_name}, {course_name}, {category_name}, '
    . '{score_percent}, {previous_score}, {threshold_percent}, {criteria_breakdown}';

// Tasks.
$string['task_check_threshold'] = 'Check upcoming quizzes below threshold';
$string['task_resync_scores']   = 'Resync all quiz security scores (nightly)';

// Misc.
$string['system']     = 'System (cron)';

// Capability strings.
$string['quizmetamonitor:viewall']           = 'View all quiz metadata site-wide';
$string['quizmetamonitor:configure']         = 'Configure Quiz Metadata Monitor settings';
$string['quizmetamonitor:viewcategory']      = 'View quiz metadata for assigned category';
$string['quizmetamonitor:receiveexamalert']  = 'Receive exam monitor threshold alerts';
$string['quizmetamonitor:viewown']           = 'View own quiz metadata';
$string['quizmetamonitor:receiveteacheralert'] = 'Receive teacher notification emails';

// Fix 1 — monitor assignment by email.
$string['addmonitor']          = 'Add Exam Monitor';
$string['useremail']           = 'User Email Address';
$string['enteruseremail']      = 'Enter the user\'s email address';
$string['emailhelp']           = 'The user must already have a Moodle account with this email.';
$string['selectcategory']      = 'Assign to Category';
$string['choosecategory']      = 'Choose a category...';
$string['currentassignments']  = 'Current Exam Monitor Assignments';
$string['confirmremove']       = 'Are you sure you want to remove this Exam Monitor assignment?';
$string['monitor_assigned']    = 'Exam Monitor assigned successfully: {$a}';
$string['monitor_removed']     = 'Exam Monitor assignment removed.';
$string['error_emailrequired'] = 'Please enter an email address.';
$string['error_usernotfound']  = 'No Moodle user found with email address: {$a}';
$string['error_selectcategory'] = 'Please select a category.';
$string['error_alreadyassigned'] = '{$a} is already assigned as Exam Monitor for this category.';

// Fix 2 — export.
$string['exportdescription']      = 'The CSV export includes all quiz metadata fields, security criteria results, scores, and dates.';
$string['totalquizzes']           = 'Total quizzes tracked';
$string['belowthresholdcount']    = 'Quizzes below threshold';

// Fix 3 — edit button (already existed, no new strings needed).

// Fix 4 — backfill.
$string['backfill']               = 'Backfill Existing Quizzes';
$string['backfill_description']   = 'This tool scores all existing quizzes that were created before the plugin was installed. No emails will be sent — scores will simply be computed and stored so they appear in the monitoring tables.';
$string['backfill_nomail']        = 'No emails will be sent during backfill. This only computes and stores security scores.';
$string['backfill_newonly']       = 'Score {$a} unscored quizzes';
$string['backfill_rescore_all']   = 'Rescore all {$a} quizzes (overwrite existing scores)';
$string['backfill_complete']      = 'Backfill complete. Total: {$a->total} | Success: {$a->success} | Failed: {$a->failed}';
$string['totalquizzesindb']       = 'Total quizzes in system';
$string['alreadyscored']          = 'Already have a score';
$string['unscoredquizzes']        = 'Not yet scored';

// -------------------------------------------------------------------- //
// Admin overview filters                                                //
// -------------------------------------------------------------------- //
$string['filterquizzes']      = 'Filter Quizzes';
$string['filterbycategory']   = 'Filter by Category';
$string['filterbycourse']     = 'Filter by Course';
$string['filterbystatus']     = 'Filter by Status';
$string['allcategories']      = 'All Categories';
$string['allstatuses']        = 'All Statuses';
$string['applyfilters']       = 'Apply Filters';
$string['resetfilters']       = 'Reset';
$string['activefilters']      = 'Active filters';
$string['showingquizzes']     = 'Showing {$a} quiz(zes)';
$string['quizzesok']          = 'Quizzes OK';

// -------------------------------------------------------------------- //
// Config history                                                        //
// -------------------------------------------------------------------- //
$string['changehistory']             = 'Change History';
$string['changereason']              = 'Reason for Change';
$string['changereason_placeholder']  = 'Describe why you are making this change, e.g. "Removed IP restriction criterion for online courses category as it is not applicable."';
$string['changereason_help']         = 'This description is required and will be stored in the change history for audit purposes.';
$string['nochangehistory']           = 'No changes have been recorded yet.';
$string['newcriteria']               = 'New Criteria State';
$string['newthreshold']              = 'New Threshold';
$string['currentcriteria']           = 'Current Scoring Criteria';
$string['currentthreshold']          = 'Current Threshold';
$string['editingdefaultcriteria']    = 'You are editing the site-wide default scoring criteria. This applies to all categories that do not have their own override.';
$string['editingcategorycriteria']   = 'You are editing criteria for the category: {$a}. This overrides the site default for this category only.';
$string['selectcategorylabel']       = 'Viewing configuration for:';
$string['thresholdexplain']          = 'Quizzes scoring below this percentage will trigger Exam Monitor alerts.';
$string['error_descriptionrequired'] = 'A reason for the change is required before saving.';

// -------------------------------------------------------------------- //
// Export filters                                                        //
// -------------------------------------------------------------------- //
$string['exportfilters']          = 'Export Filters';
$string['exportpreviewcount']     = '{$a} quiz(zes) match the current filters';
$string['exportpreview']          = 'Preview (first 10 rows)';
$string['exportpreviewlimited']   = 'Showing {$a->shown} of {$a->total} quizzes. Download the CSV to see all results.';
$string['noquizzesmatchfilter']   = 'No quizzes match the selected filters.';
$string['quizzes']                = 'quizzes';
$string['error_invaliddate']      = 'One or more dates entered are not valid. Please use the date picker.';
$string['error_datefromafterdate'] = '"Opens From" date cannot be after "Opens Until" date.';

$string['quizstartdaterange']      = 'Quiz Start Date Range';
$string['quizstartdaterange_help'] = 'Filter quizzes whose start (open) date falls between these two dates.';
$string['startdatefrom']           = 'From';
$string['startdateto']             = 'To';
$string['anytime']                 = 'any date';