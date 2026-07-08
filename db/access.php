<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // ---- Admin / Manager -------------------------------------------- //
    'local/quizmetamonitor:viewall' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/quizmetamonitor:configure' => [
        'riskbitmask' => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // ---- Exam Monitor ----------------------------------------------- //
    'local/quizmetamonitor:viewcategory' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [],        // Granted only by explicit assignment.
    ],

    'local/quizmetamonitor:receiveexamalert' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [],
    ],

    // ---- Teacher / Assistant Teacher --------------------------------- //
    'local/quizmetamonitor:viewown' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
        ],
    ],

    'local/quizmetamonitor:receiveteacheralert' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
        ],
    ],
];