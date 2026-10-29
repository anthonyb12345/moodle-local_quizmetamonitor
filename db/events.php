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
 * Event observers for local_quizmetamonitor.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    // Restore/import/course copy fires only course_restored (no course_module_created),
    // so restored quizzes are scored here.
    [
        'eventname' => '\core\event\course_restored',
        'callback'  => '\local_quizmetamonitor\event\observer::course_restored',
        'priority'  => 0,
        'internal'  => false,
    ],
    // New: when a course is created, assign monitors who cover its category.
    [
        'eventname' => '\core\event\course_created',
        'callback'  => '\local_quizmetamonitor\event\observer::course_created',
        'priority'  => 0,
        'internal'  => false,
    ],
];
