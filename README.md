# Quiz Metadata Monitor (local_quizmetamonitor)

## Overview

**Quiz Metadata Monitor** is a Moodle local plugin that scores the security settings of every quiz and emails the teachers who created or edited the quiz, and the Exam Monitors of its course category, when the quiz is not configured securely enough.

The plugin is designed for:

- exam quality assurance
- institutional oversight of assessment security

Each quiz gets a **security score** from a configurable list of criteria. The plugin:

- scores a quiz automatically when it is created, edited, restored or imported,
- emails the teacher who created or edited the quiz its score and criteria breakdown
- alerts the teachers and Exam Monitors when a quiz opens within 24 hours with a score below the threshold.

---

## Key Features

- Automatic security score for every quiz, recalculated on every change
- Configurable criteria and points, site-wide or per course category
- Configurable alert threshold, site-wide or per course category
- Emails only the teacher or assistant teacher who created or edited the quiz
- Email whenever any security criterion changes, even when the score stays the same
- Scores quizzes that arrive by backup restore, course import or course copy (one email per quiz)
- Below-threshold alerts 24 hours before a quiz opens, sent once per quiz
- Exam Monitor role per course category, assigned automatically to the category's courses
- Editable email templates with placeholders
- Reports for administrators, Exam Monitors and teachers, with CSV export
- Full history of score changes and of criteria and threshold changes
- English and French language packs

---

## Intended Use

This plugin is intended for:

- Exam offices and academic coordinators reviewing quiz security
- Exam Monitors responsible for the quizzes of a course category
- Teachers who want feedback on the security of their quiz settings

---

## Security Criteria

Each criterion can be enabled or disabled and given its own points. The default criteria are:

- Open date set
- Close date set
- Allowed attempts limited
- Password set
- Restriction by IP address set
- Block concurrent connections set (from the [Block concurrent sessions](https://moodle.org/plugins/quizaccess_onesession) quiz access rule)
- Safe Exam Browser: always full points; the email shows which option the teacher chose

The score is the percentage of points earned. The default threshold is 70%.

---

## Notifications

| Email | Sent to | When |
|---|---|---|
| New quiz created | The teacher or assistant teacher who created the quiz | A quiz is created |
| Security settings changed | The teacher or assistant teacher who edited the quiz | A quiz's score or any criterion changes |
| Restored quiz | The teacher who restored or imported the quiz | A quiz arrives by backup restore, course import or course copy (one email per quiz) |
| Below threshold (teacher) | Everyone who created, edited or restored the quiz | The quiz opens within 24 hours and its score is below the threshold |
| Below threshold (Exam Monitor) | The Exam Monitors of the quiz's course category | Same as above |

Every email contains the criteria breakdown table. When a teacher chooses not to use Safe Exam Browser, the email adds a reminder after the table.

---

## What Is Explicitly Excluded

The plugin **never**:

- changes quiz settings or blocks a quiz
- reads student attempts, responses or grades
- emails teachers of the course who did not create or edit the quiz

---

## Access Control

Access is enforced using Moodle's capability system:

| Capability | Context | Default |
|---|---|---|
| `local/quizmetamonitor:viewall` | System | Managers |
| `local/quizmetamonitor:configure` | System | Managers |
| `local/quizmetamonitor:viewcategory` | Course category | Exam Monitors (by assignment only) |
| `local/quizmetamonitor:receiveexamalert` | Course category | Exam Monitors (by assignment only) |
| `local/quizmetamonitor:viewown` | Course | Teachers and non-editing teachers |
| `local/quizmetamonitor:receiveteacheralert` | Course | Teachers and non-editing teachers |

Exam Monitors

- Administrators assign Exam Monitors to a course category
- The plugin creates the role **Exam Monitor (Quiz Metadata)** and assigns it in every course of the category
- New courses created in the category get the role assigned automatically

---

## Installation

Requirements:

- Moodle 5.0 or later
- The [Block concurrent sessions](https://moodle.org/plugins/quizaccess_onesession) quiz access rule (`quizaccess_onesession`). Moodle will not install this plugin until it is installed.

1. Install **Block concurrent sessions** if it is not installed yet

2. Place the plugin in: **/local/quizmetamonitor** (**/public/local/quizmetamonitor** on Moodle 5.1 and later), or install the ZIP from **Site administration → Plugins → Install plugins**

3. Log in as administrator

4. Navigate to: **Site administration → Notifications**

5. Complete the installation process

6. Optionally score the quizzes that existed before the installation: **Site administration → Plugins → Local plugins → Quiz Metadata Monitor → Backfill existing quizzes**

---

## Usage

- **Administrators:** **Site administration → Plugins → Local plugins → Quiz Metadata Monitor → Quiz metadata overview** lists every quiz with its score
- **Exam Monitors:** open a course category and choose **Category quiz monitor**
- **Teachers:** open a course and choose **My quiz security scores**

Click a quiz to see its **Quiz security detail**: the criteria breakdown and the history of score changes.

P.S. Users without the matching capability do not see these pages.

---

## Configuration

Available via:

Site administration → Plugins → Local plugins → Quiz Metadata Monitor

Options include:

- **Configure scoring criteria:** enable criteria and set their points, site-wide or per category
- **Configure alert threshold:** the score below which alerts are sent, site-wide or per category
- **Assign Exam Monitors:** users who receive the below-threshold alerts for a course category
- **Email templates:** subject and body of every email, with placeholders such as `{quiz_name}`, `{course_name}`, `{score_percent}`, `{threshold_percent}` and `{criteria_breakdown}`
- **Export report:** CSV export of quiz security metadata
- **Backfill existing quizzes:** score quizzes that have no score yet

---

## Scheduled Tasks

- **Check upcoming quizzes below threshold** (every 30 minutes): sends the below-threshold alerts
- **Resync all quiz security scores** (nightly at 03:00): rescores quizzes created since the plugin was installed, quizzes edited since then, and any quiz without a score

---

## Events and Logging

The plugin listens to these Moodle events:

- `\core\event\course_module_created`, `\core\event\course_module_updated` and `\core\event\course_module_deleted`
- `\core\event\course_restored`
- `\core\event\course_created`

It records:

- every score change, with the user who caused it
- every notification sent, with its recipient
- every change to the criteria or threshold, with the administrator and their reason

This supports audit and traceability requirements.

---

## Privacy

The plugin implements the Moodle Privacy API. It stores which user created, edited or restored a quiz (score history), which notifications were sent to whom, Exam Monitor assignments, and who changed the criteria or threshold. This data can be exported, and deleted or anonymised, through **Site administration → Users → Privacy and policies**. On deletion, notification logs and Exam Monitor assignments are removed. In the score and configuration history, the user is replaced by "system" so the audit trail is kept.

---

## Development Notes

- Implemented as a Moodle local plugin
- Built using Moodle core subsystems and standard services, including:
    - Events API for scoring quizzes when they are created, edited, restored or deleted
    - Backup and restore API for scoring quizzes restored from a single-activity or section backup
    - Scheduled tasks API for the alerts and the nightly resync
    - Access control system for enforcing permissions and roles
    - Database layer for scores, history, templates and settings
    - Moodle email API (`email_to_user`) for notifications
    - Privacy API for exporting and deleting user data
- Reads the settings of the Safe Exam Browser and Block concurrent sessions quiz access rules
- Designed to be fully upgrade-safe, with no modifications to Moodle core code

---

## Acknowledgements

This plugin was developed with the assistance of Claude (Anthropic) as a code generation and review support tool.

All generated code was reviewed, validated, and adapted to comply with Moodle development standards, institutional requirements, and security practices. Final responsibility for the design, implementation, and correctness of the plugin remains with the developer(s) and the institution.

---

## Limitations

- Scores quiz settings only, not the questions
- Quizzes created before the installation are not rescored nightly unless they are backfilled or edited
- A restored quiz is scored but no email is sent when the user who restores it is not a teacher in the course
- The default email templates are in English; they can be edited under **Email templates**

---

## Version

- Version: 1.0.0
- Moodle compatibility: 5.0+

---

## License

GNU GPL v3 or later  
https://www.gnu.org/copyleft/gpl.html

---

## Maintainers

Center for Digital Innovation and Artificial Intelligence, contributors, and collaborators as listed in version.php
