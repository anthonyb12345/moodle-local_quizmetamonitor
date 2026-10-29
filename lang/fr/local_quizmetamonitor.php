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
 * Strings for component 'local_quizmetamonitor', language 'fr'.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Actions';
$string['activefilters'] = 'Filtres actifs';
$string['addmonitor'] = 'Ajouter un surveillant d’examen';
$string['adminoverview'] = 'Vue d’ensemble des métadonnées des quiz';
$string['allcategories'] = 'Toutes les catégories';
$string['allcourses'] = 'Tous les cours';
$string['allstatuses'] = 'Tous les statuts';
$string['alreadyscored'] = 'Déjà évalués';
$string['anytime'] = 'n’importe quelle date';
$string['applyfilters'] = 'Appliquer les filtres';
$string['assign'] = 'Attribuer';
$string['assignmonitors'] = 'Attribuer des surveillants d’examen';
$string['backfill'] = 'Calcul des scores des quiz existants';
$string['backfill_complete'] = 'Calcul terminé. Total : {$a->total} | Réussis : {$a->success} | Échecs : {$a->failed}';
$string['backfill_description'] = 'Cet outil calcule les scores de sécurité de tous les quiz créés avant l’installation du plugin. Aucun e-mail ne sera envoyé. Les scores seront simplement calculés et enregistrés afin d’apparaître dans les tableaux de suivi.';
$string['backfill_error'] = 'Error: {$a}';
$string['backfill_newonly'] = 'Calculer le score de {$a} quiz non évalués';
$string['backfill_nomail'] = 'Aucun e-mail ne sera envoyé pendant cette opération. Seuls les scores de sécurité seront calculés et enregistrés.';
$string['backfill_rescore_all'] = 'Recalculer le score des {$a} quiz (écraser les scores existants)';
$string['backfill_scored'] = 'Scored';
$string['backfill_unknownquiz'] = 'Unknown';
$string['belowthreshold'] = 'EN DESSOUS DU SEUIL';
$string['belowthresholdcount'] = 'Quiz en dessous du seuil';
$string['bodyhtml'] = 'Corps (HTML)';
$string['bodyplain'] = 'Corps (texte brut)';
$string['changedby'] = 'Modifié par';
$string['changehistory'] = 'Historique des modifications';
$string['changereason'] = 'Motif de la modification';
$string['changereason_help'] = 'Cette description est obligatoire et sera enregistrée dans l’historique des modifications à des fins d’audit.';
$string['changereason_placeholder'] = 'Décrivez la raison de cette modification, par exemple : « Le critère de restriction par adresse IP a été supprimé pour la catégorie des cours en ligne, car il n’est pas applicable. »';
$string['choosecategory'] = 'Choisir une catégorie...';
$string['closedate'] = 'Date de fermeture';
$string['configurecriteria'] = 'Configurer les critères de notation';
$string['configurethreshold'] = 'Configurer le seuil d’alerte';
$string['confirmremove'] = 'Êtes-vous sûr de vouloir supprimer cette attribution de surveillant d’examen ?';
$string['criteria_nochange'] = 'No criteria changed (the scoring configuration was updated).';
$string['criteriabreakdown'] = 'Détail des critères';
$string['criterion'] = 'Critère';
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
$string['currentassignments'] = 'Attributions actuelles des surveillants d’examen';
$string['currentcriteria'] = 'Critères d’évaluation actuels';
$string['currentthreshold'] = 'Seuil actuel';
$string['dateassigned'] = 'Date d’attribution';
$string['downloadcsv'] = 'Télécharger le rapport CSV';
$string['editingcategorycriteria'] = 'Vous modifiez les critères de notation de la catégorie : {$a}. Ces paramètres remplacent les paramètres par défaut du site uniquement pour cette catégorie.';
$string['editingdefaultcriteria'] = 'Vous modifiez les critères de notation par défaut du site. Ces paramètres s’appliquent à toutes les catégories qui ne disposent pas de leur propre configuration.';
$string['editquiz'] = 'Modifier les paramètres du quiz';
$string['email_col_criterion'] = 'Criterion';
$string['email_col_points'] = 'Points';
$string['email_col_status'] = 'Status';
$string['email_fail'] = 'FAIL';
$string['email_pass'] = 'PASS';
$string['email_points'] = '{$a->earned} / {$a->possible} pts';
$string['emailhelp'] = 'L’utilisateur doit déjà disposer d’un compte Moodle associé à cette adresse e-mail.';
$string['emailtemplates'] = 'Modèles d’e-mails';
$string['enabled'] = 'Activé';
$string['enteruseremail'] = 'Saisissez l’adresse e-mail de l’utilisateur';
$string['enteruserid'] = 'Saisir l’identifiant utilisateur';
$string['error_alreadyassigned'] = '{$a} est déjà attribué comme surveillant d’examen pour cette catégorie.';
$string['error_datefromafterdate'] = 'La date « Ouverture à partir du » ne peut pas être postérieure à la date « Ouverture jusqu’au ».';
$string['error_descriptionrequired'] = 'Un motif de modification est requis avant l’enregistrement.';
$string['error_emailrequired'] = 'Veuillez saisir une adresse e-mail.';
$string['error_invaliddate'] = 'Une ou plusieurs dates saisies sont invalides. Veuillez utiliser le sélecteur de dates.';
$string['error_selectcategory'] = 'Veuillez sélectionner une catégorie.';
$string['error_usernotfound'] = 'Aucun utilisateur Moodle trouvé avec l’adresse e-mail : {$a}';
$string['examrole_description'] = 'Can view and edit quizzes within their assigned category. Cannot access other course resources.';
$string['examrole_name'] = 'Exam Monitor (Quiz Metadata)';
$string['exportdescription'] = 'L’export CSV comprend toutes les métadonnées des quiz, les résultats des critères de sécurité, les scores et les dates.';
$string['exportfilters'] = 'Filtres d’exportation';
$string['exportpreview'] = 'Aperçu (10 premières lignes)';
$string['exportpreviewcount'] = '{$a} quiz correspondent aux filtres actuels';
$string['exportpreviewlimited'] = 'Affichage de {$a->shown} quiz sur {$a->total}. Téléchargez le rapport CSV pour consulter tous les résultats.';
$string['exportreport'] = 'Exporter le rapport';
$string['fail'] = 'ÉCHEC';
$string['filterbycategory'] = 'Filtrer par catégorie';
$string['filterbycourse'] = 'Filtrer par cours';
$string['filterbystatus'] = 'Filtrer par statut';
$string['filterquizzes'] = 'Filtrer les quiz';
$string['gotoquiz'] = 'Accéder au quiz';
$string['monitor_assigned'] = 'Surveillant d’examen attribué avec succès : {$a}';
$string['monitor_removed'] = 'Attribution du surveillant d’examen supprimée.';
$string['monitorcategory'] = 'Surveillance des quiz par catégorie';
$string['myquizzes'] = 'Mes scores de sécurité des quiz';
$string['newcriteria'] = 'Nouvel état du critère';
$string['newthreshold'] = 'Nouveau seuil';
$string['noassignments'] = 'Aucun surveillant d’examen n’est actuellement attribué.';
$string['nochangehistory'] = 'Aucune modification n’a encore été enregistrée.';
$string['noquizzesmatchfilter'] = 'Aucun quiz ne correspond aux filtres sélectionnés.';
$string['nothresholdoverride'] = 'Aucune configuration spécifique n’est définie pour cette catégorie. Les paramètres par défaut du site ({$a} %) s’appliquent.';
$string['ok'] = 'OK';
$string['opendate'] = 'Date d’ouverture';
$string['pass'] = 'RÉUSSI';
$string['placeholder_help'] = 'Espaces réservés disponibles : {quiz_name}, {course_name}, {category_name}, {score_percent}, {previous_score}, {threshold_percent}, {criteria_breakdown}. Courriel de modification uniquement : {score_change} (ex. « 71.43% → 85.71% » ou « 85.71% (unchanged) »), {criteria_changes} (liste des critères modifiés). Le courriel de quiz restauré (un par quiz) utilise les mêmes espaces réservés que le courriel de quiz créé.';
$string['pluginname'] = 'Surveillance des métadonnées des quiz';
$string['points'] = 'Points';
$string['pointsearned'] = 'Points obtenus';
$string['pointspossible'] = 'Points possibles';
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
$string['quizdetail'] = 'Détails de la sécurité du quiz';
$string['quizmetamonitor:configure'] = 'Configurer les paramètres du module de surveillance des métadonnées des quiz';
$string['quizmetamonitor:receiveexamalert'] = 'Recevoir les alertes de seuil des surveillants d’examen';
$string['quizmetamonitor:receiveteacheralert'] = 'Recevoir les notifications par e-mail destinées aux enseignants';
$string['quizmetamonitor:viewall'] = 'Consulter toutes les métadonnées des quiz du site';
$string['quizmetamonitor:viewcategory'] = 'Consulter les métadonnées des quiz de la catégorie attribuée';
$string['quizmetamonitor:viewown'] = 'Consulter ses propres métadonnées de quiz';
$string['quizname'] = 'Nom du quiz';
$string['quizstartdaterange'] = 'Période de début du quiz';
$string['quizstartdaterange_help'] = 'Filtrer les quiz dont la date d’ouverture se situe dans l’intervalle sélectionné.';
$string['quizzes'] = 'quiz';
$string['quizzesok'] = 'Quiz conformes au seuil';
$string['remove'] = 'Retirer';
$string['resetfilters'] = 'Réinitialiser';
$string['resettodefault'] = 'Réinitialiser aux paramètres par défaut du site';
$string['score'] = 'Score de sécurité';
$string['score_changed_from'] = '{$a->previous}% → {$a->current}%';
$string['score_unchanged'] = '{$a}% (unchanged)';
$string['scorehistory'] = 'Historique des scores (10 dernières modifications)';
$string['seb_choice_client'] = 'Safe Exam Browser - Oui - Utiliser la configuration du client SEB';
$string['seb_choice_manual'] = 'Safe Exam Browser - Oui - Configurer manuellement';
$string['seb_choice_no'] = 'Safe Exam Browser - Non - Utilisation libre des logiciels, site web ou documents';
$string['seb_choice_template'] = 'Safe Exam Browser - Oui - Utiliser un modèle existant';
$string['seb_choice_upload'] = 'Safe Exam Browser - Oui - Déposer ma propre configuration';
$string['seb_choice_yes'] = 'Safe Exam Browser - Oui';
$string['seb_no_notice'] = 'You have chosen <b>NOT to use Safe Exam Browser</b>, which is <b>ONLY applicable when students need unrestricted access </b>(such as the case with open-book exams). Note that, open-book exams can still use SEB: printed materials are always allowed, and specific websites or applications can be permitted through SEB configuration.';
$string['selectcategory'] = 'Attribuer à une catégorie';
$string['selectcategorylabel'] = 'Configuration affichée pour :';
$string['showingquizzes'] = 'Affichage de {$a} quiz';
$string['sitedefault'] = 'Paramètres par défaut du site';
$string['startdatefrom'] = 'À partir du';
$string['startdateto'] = 'Jusqu’au';
$string['status'] = 'Statut';
$string['subject'] = 'Sujet';
$string['system'] = 'Système (cron)';
$string['task_check_threshold'] = 'Vérifier les quiz à venir en dessous du seuil';
$string['task_resync_scores'] = 'Recalculer tous les scores de sécurité des quiz (tâche planifiée nocturne)';
$string['template_exammonitor_alert'] = 'Surveillant d’examen — Alerte de seuil dépassé';
$string['template_teacher_alert'] = 'Enseignant — Alerte de seuil (le quiz ouvre dans les 24 heures)';
$string['template_teacher_created'] = 'Enseignant — Quiz créé';
$string['template_teacher_restored'] = 'Enseignant — Quiz restaurés ou importés';
$string['template_teacher_scorechanged'] = 'Enseignant — Paramètres de sécurité modifiés';
$string['threshold'] = 'Seuil';
$string['thresholdexplain'] = 'Les quiz dont le score de sécurité est inférieur à ce pourcentage déclencheront une alerte destinée aux surveillants d’examen.';
$string['thresholdpercent'] = 'Seuil (%)';
$string['totalquizzes'] = 'Nombre total de quiz suivis';
$string['totalquizzesindb'] = 'Nombre total de quiz dans le système';
$string['unscoredquizzes'] = 'Pas encore évalués';
$string['useremail'] = 'Adresse e-mail de l’utilisateur';
$string['userid'] = 'Identifiant utilisateur';
$string['viewdetails'] = 'Voir les détails';
$string['viewquizdetail'] = 'view this quiz detail';
