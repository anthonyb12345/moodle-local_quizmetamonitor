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
 * Capability definitions for local_quizmetamonitor.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Anthony Bassil <anthonybassil101@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Admin / Manager.
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

    // Exam Monitor.
    'local/quizmetamonitor:viewcategory' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [], // Granted only by explicit assignment.
    ],

    'local/quizmetamonitor:receiveexamalert' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [],
    ],

    // Teacher / Assistant Teacher.
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
