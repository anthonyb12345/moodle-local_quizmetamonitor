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
 * Version information for local_quizmetamonitor.
 *
 * @package    local_quizmetamonitor
 * @copyright  2026 Center for Digital Innovation and Artificial Intelligence <moodle.cinia@usj.edu.lb>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_quizmetamonitor';
$plugin->version   = 2026102900;
$plugin->requires  = 2025041400; // Moodle 5.0.
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';

// Center for Digital Innovation and Artificial Intelligence (CINIA).
$plugin->copyright = '2026 Center for Digital Innovation and Artificial Intelligence (CINIA) | https://usj.edu.lb/cinia/';

// Governance and Development Leadership.
$plugin->collaborators = [
    'Anthony Bassil', // Email: anthony.bassil5@usj.edu.lb. Position: AI Engineer.
    'Mario Gharib', // Email: mario.gharib@usj.edu.lb. Position: Team leader (architecture and implementation).
];
