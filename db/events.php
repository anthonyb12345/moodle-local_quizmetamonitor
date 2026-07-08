<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback'  => '\local_quizmetamonitor\event\observer::quiz_created',
        'priority'  => 0,
        'internal'  => false,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => '\local_quizmetamonitor\event\observer::quiz_updated',
        'priority'  => 0,
        'internal'  => false,
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => '\local_quizmetamonitor\event\observer::module_deleted',
        'priority'  => 0,
        'internal'  => false,
    ],
];