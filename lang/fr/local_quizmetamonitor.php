<?php
defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'Surveillance des métadonnées des quiz';

// Navigation / page titles.
$string['adminoverview']      = 'Vue d’ensemble des métadonnées des quiz';
$string['configurecriteria']  = 'Configurer les critères de notation';
$string['configurethreshold'] = 'Configurer le seuil d’alerte';
$string['assignmonitors']     = 'Attribuer des surveillants d’examen';
$string['emailtemplates']     = 'Modèles d’e-mails';
$string['exportreport']       = 'Exporter le rapport';
$string['monitorcategory']    = 'Surveillance des quiz par catégorie';
$string['myquizzes']          = 'Mes scores de sécurité des quiz';
$string['quizdetail']         = 'Détails de la sécurité du quiz';

// Table columns.
$string['quizname']            = 'Nom du quiz';
$string['score']               = 'Score de sécurité';
$string['threshold']           = 'Seuil';
$string['status']              = 'Statut';
$string['actions']             = 'Actions';
$string['opendate']            = 'Date d’ouverture';
$string['closedate']           = 'Date de fermeture';
$string['pointspossible']      = 'Points possibles';
$string['pointsearned']        = 'Points obtenus';
$string['criterion']           = 'Critère';
$string['criteriabreakdown']   = 'Détail des critères';
$string['scorehistory']        = 'Historique des scores (10 dernières modifications)';
$string['changedby']           = 'Modifié par';

// Status labels.
$string['belowthreshold'] = 'EN DESSOUS DU SEUIL';
$string['ok']             = 'OK';
$string['pass']           = 'RÉUSSI';
$string['fail']           = 'ÉCHEC';
$string['seb_choice_yes']      = 'Safe Exam Browser - Oui';
$string['seb_choice_no']       = 'Safe Exam Browser - Non - Utilisation libre des logiciels, site web ou documents';
$string['seb_choice_manual']   = 'Safe Exam Browser - Oui - Configurer manuellement';
$string['seb_choice_template'] = 'Safe Exam Browser - Oui - Utiliser un modèle existant';
$string['seb_choice_upload']   = 'Safe Exam Browser - Oui - Déposer ma propre configuration';
$string['seb_choice_client']   = 'Safe Exam Browser - Oui - Utiliser la configuration du client SEB';
// Kept in English: it is appended inside the (English) teacher email.
$string['seb_no_notice']       = 'You have chosen <b>NOT to use Safe Exam Browser</b>, which is '
    . '<b>ONLY applicable when students need unrestricted access </b>(such as the case with open-book exams). '
    . 'Note that, open-book exams can still use SEB: printed materials are always allowed, and specific '
    . 'websites or applications can be permitted through SEB configuration.';

// Buttons / links.
$string['viewdetails']    = 'Voir les détails';
$string['downloadcsv']    = 'Télécharger le rapport CSV';
$string['assign']         = 'Attribuer';
$string['remove']         = 'Retirer';
$string['resettodefault'] = 'Réinitialiser aux paramètres par défaut du site';
$string['gotoquiz']       = 'Accéder au quiz';
$string['editquiz']       = 'Modifier les paramètres du quiz';

// Form labels.
$string['sitedefault']          = 'Paramètres par défaut du site';
$string['thresholdpercent']     = 'Seuil (%)';
$string['points']               = 'Points';
$string['enabled']              = 'Activé';
$string['subject']              = 'Sujet';
$string['bodyhtml']             = 'Corps (HTML)';
$string['bodyplain']            = 'Corps (texte brut)';
$string['allcourses']           = 'Tous les cours';
$string['userid']               = 'Identifiant utilisateur';
$string['enteruserid']          = 'Saisir l’identifiant utilisateur';
$string['dateassigned']         = 'Date d’attribution';
$string['noassignments']        = 'Aucun surveillant d’examen n’est actuellement attribué.';
$string['nothresholdoverride']  = 'Aucune configuration spécifique n’est définie pour cette catégorie. Les paramètres par défaut du site ({$a} %) s’appliquent.';

// Email templates.
$string['template_teacher_created']      = 'Enseignant — Quiz créé';
$string['template_teacher_scorechanged'] = 'Enseignant — Paramètres de sécurité modifiés';
$string['template_exammonitor_alert']    = 'Surveillant d’examen — Alerte de seuil dépassé';
$string['placeholder_help'] =
    'Espaces réservés disponibles : {quiz_name}, {course_name}, {category_name}, '
    . '{score_percent}, {previous_score}, {threshold_percent}, {criteria_breakdown}. '
    . 'Courriel de modification uniquement : {score_change} (ex. « 71.43% → 85.71% » ou « 85.71% (unchanged) »), '
    . '{criteria_changes} (liste des critères modifiés). '
    . 'Le courriel de quiz restauré (un par quiz) utilise les mêmes espaces réservés que le courriel de quiz créé.';
$string['template_teacher_restored'] = 'Enseignant — Quiz restaurés ou importés';
$string['template_teacher_alert']    = 'Enseignant — Alerte de seuil (le quiz ouvre dans les 24 heures)';
// Kept in English: these fill placeholders inside the (English) email template.
$string['score_unchanged']    = '{$a}% (unchanged)';
$string['score_changed_from'] = '{$a->previous}% → {$a->current}%';
$string['criteria_nochange']  = 'No criteria changed (the scoring configuration was updated).';

// Tasks.
$string['task_check_threshold'] = 'Vérifier les quiz à venir en dessous du seuil';
$string['task_resync_scores']   = 'Recalculer tous les scores de sécurité des quiz (tâche planifiée nocturne)';

// Misc.
$string['system'] = 'Système (cron)';

// Capability strings.
$string['quizmetamonitor:viewall']              = 'Consulter toutes les métadonnées des quiz du site';
$string['quizmetamonitor:configure']            = 'Configurer les paramètres du module de surveillance des métadonnées des quiz';
$string['quizmetamonitor:viewcategory']         = 'Consulter les métadonnées des quiz de la catégorie attribuée';
$string['quizmetamonitor:receiveexamalert']     = 'Recevoir les alertes de seuil des surveillants d’examen';
$string['quizmetamonitor:viewown']              = 'Consulter ses propres métadonnées de quiz';
$string['quizmetamonitor:receiveteacheralert']  = 'Recevoir les notifications par e-mail destinées aux enseignants';

// Monitor assignment.
$string['addmonitor']              = 'Ajouter un surveillant d’examen';
$string['useremail']               = 'Adresse e-mail de l’utilisateur';
$string['enteruseremail']          = 'Saisissez l’adresse e-mail de l’utilisateur';
$string['emailhelp']               = 'L’utilisateur doit déjà disposer d’un compte Moodle associé à cette adresse e-mail.';
$string['selectcategory']          = 'Attribuer à une catégorie';
$string['choosecategory']          = 'Choisir une catégorie...';
$string['currentassignments']      = 'Attributions actuelles des surveillants d’examen';
$string['confirmremove']           = 'Êtes-vous sûr de vouloir supprimer cette attribution de surveillant d’examen ?';
$string['monitor_assigned']        = 'Surveillant d’examen attribué avec succès : {$a}';
$string['monitor_removed']         = 'Attribution du surveillant d’examen supprimée.';
$string['error_emailrequired']     = 'Veuillez saisir une adresse e-mail.';
$string['error_usernotfound']      = 'Aucun utilisateur Moodle trouvé avec l’adresse e-mail : {$a}';
$string['error_selectcategory']    = 'Veuillez sélectionner une catégorie.';
$string['error_alreadyassigned']   = '{$a} est déjà attribué comme surveillant d’examen pour cette catégorie.';

// Export.
$string['exportdescription']      = 'L’export CSV comprend toutes les métadonnées des quiz, les résultats des critères de sécurité, les scores et les dates.';
$string['totalquizzes']           = 'Nombre total de quiz suivis';
$string['belowthresholdcount']    = 'Quiz en dessous du seuil';

// Backfill.
$string['backfill']               = 'Calcul des scores des quiz existants';
$string['backfill_description']   = 'Cet outil calcule les scores de sécurité de tous les quiz créés avant l’installation du plugin. Aucun e-mail ne sera envoyé. Les scores seront simplement calculés et enregistrés afin d’apparaître dans les tableaux de suivi.';
$string['backfill_nomail']        = 'Aucun e-mail ne sera envoyé pendant cette opération. Seuls les scores de sécurité seront calculés et enregistrés.';
$string['backfill_newonly']       = 'Calculer le score de {$a} quiz non évalués';
$string['backfill_rescore_all']   = 'Recalculer le score des {$a} quiz (écraser les scores existants)';
$string['backfill_complete']      = 'Calcul terminé. Total : {$a->total} | Réussis : {$a->success} | Échecs : {$a->failed}';
$string['totalquizzesindb']       = 'Nombre total de quiz dans le système';
$string['alreadyscored']          = 'Déjà évalués';
$string['unscoredquizzes']        = 'Pas encore évalués';

// Admin overview filters.
$string['filterquizzes']         = 'Filtrer les quiz';
$string['filterbycategory']      = 'Filtrer par catégorie';
$string['filterbycourse']        = 'Filtrer par cours';
$string['filterbystatus']        = 'Filtrer par statut';
$string['allcategories']         = 'Toutes les catégories';
$string['allstatuses']           = 'Tous les statuts';
$string['applyfilters']          = 'Appliquer les filtres';
$string['resetfilters']          = 'Réinitialiser';
$string['activefilters']         = 'Filtres actifs';
$string['showingquizzes']        = 'Affichage de {$a} quiz';
$string['quizzesok']             = 'Quiz conformes au seuil';

// Config history.
$string['changehistory']              = 'Historique des modifications';
$string['changereason']               = 'Motif de la modification';
$string['changereason_placeholder']   = 'Décrivez la raison de cette modification, par exemple : « Le critère de restriction par adresse IP a été supprimé pour la catégorie des cours en ligne, car il n’est pas applicable. »';
$string['changereason_help']          = 'Cette description est obligatoire et sera enregistrée dans l’historique des modifications à des fins d’audit.';
$string['nochangehistory']            = 'Aucune modification n’a encore été enregistrée.';
$string['newcriteria']                = 'Nouvel état du critère';
$string['newthreshold']               = 'Nouveau seuil';
$string['currentcriteria']            = 'Critères d’évaluation actuels';
$string['currentthreshold']           = 'Seuil actuel';
$string['editingdefaultcriteria']     = 'Vous modifiez les critères de notation par défaut du site. Ces paramètres s’appliquent à toutes les catégories qui ne disposent pas de leur propre configuration.';
$string['editingcategorycriteria']    = 'Vous modifiez les critères de notation de la catégorie : {$a}. Ces paramètres remplacent les paramètres par défaut du site uniquement pour cette catégorie.';
$string['selectcategorylabel']        = 'Configuration affichée pour :';
$string['thresholdexplain']           = 'Les quiz dont le score de sécurité est inférieur à ce pourcentage déclencheront une alerte destinée aux surveillants d’examen.';
$string['error_descriptionrequired']  = 'Un motif de modification est requis avant l’enregistrement.';

// Export filters.
$string['exportfilters']           = 'Filtres d’exportation';
$string['exportpreviewcount']      = '{$a} quiz correspondent aux filtres actuels';
$string['exportpreview']           = 'Aperçu (10 premières lignes)';
$string['exportpreviewlimited']    = 'Affichage de {$a->shown} quiz sur {$a->total}. Téléchargez le rapport CSV pour consulter tous les résultats.';
$string['noquizzesmatchfilter']    = 'Aucun quiz ne correspond aux filtres sélectionnés.';
$string['quizzes']                 = 'quiz';
$string['error_invaliddate']       = 'Une ou plusieurs dates saisies sont invalides. Veuillez utiliser le sélecteur de dates.';
$string['error_datefromafterdate'] = 'La date « Ouverture à partir du » ne peut pas être postérieure à la date « Ouverture jusqu’au ».';

$string['quizstartdaterange']      = 'Période de début du quiz';
$string['quizstartdaterange_help'] = 'Filtrer les quiz dont la date d’ouverture se situe dans l’intervalle sélectionné.';
$string['startdatefrom']           = 'À partir du';
$string['startdateto']             = 'Jusqu’au';
$string['anytime']                 = 'n’importe quelle date';