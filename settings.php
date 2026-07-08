<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $ADMIN->add(
        'localplugins',
        new admin_category(
            'local_quizmetamonitor_cat',
            get_string('pluginname', 'local_quizmetamonitor')
        )
    );

    $pages = [
        ['admin_overview',  'adminoverview',       'admin_overview.php'],
        ['admin_criteria',  'configurecriteria',   'admin_criteria.php'],
        ['admin_threshold', 'configurethreshold',  'admin_threshold.php'],
        ['admin_monitors',  'assignmonitors',      'admin_monitors.php'],
        ['admin_templates', 'emailtemplates',      'admin_templates.php'],
        ['admin_export',    'exportreport',        'admin_export.php'],
        ['admin_backfill',  'backfill',            'admin_backfill.php'],
    ];

    foreach ($pages as [$name, $strkey, $file]) {
        $ADMIN->add(
            'local_quizmetamonitor_cat',
            new admin_externalpage(
                'local_quizmetamonitor_' . $name,
                get_string($strkey, 'local_quizmetamonitor'),
                new moodle_url('/local/quizmetamonitor/pages/' . $file)
            )
        );
    }
}

$settings = null; // We manage all pages above; suppress default settings page.