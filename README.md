# Quiz Metadata Monitor (local_quizmetamonitor)

Quiz Metadata Monitor scores the security settings of every quiz and tells the right people
when a quiz is not configured securely enough.

Each quiz gets a **security score** from a configurable list of criteria:

- open date set
- close date set
- allowed attempts limited
- password set
- restriction by IP address set
- block concurrent connections set (needs the
  [Block concurrent sessions](https://moodle.org/plugins/quizaccess_onesession) quiz access rule)
- Safe Exam Browser: always full points; the email shows which option the teacher chose

## Notifications

| Email | Sent to | When |
|---|---|---|
| New quiz created | The teacher or assistant teacher who created the quiz | A quiz is created |
| Security settings changed | The teacher or assistant teacher who edited the quiz | A quiz's score or any criterion changes |
| Restored quiz | The teacher who restored or imported the quiz | A quiz arrives by backup restore, course import or course copy (one email per quiz) |
| Below threshold (teacher) | Everyone who created, edited or restored the quiz | The quiz opens within 24 hours and its score is below the threshold |
| Below threshold (Exam Monitor) | The Exam Monitors of the quiz's course category | Same as above |

Every email template can be edited under *Site administration > Plugins > Local plugins >
Quiz Metadata Monitor > Email templates*.

## Requirements

- Moodle 5.0 or later.
- Optional: the *Block concurrent sessions* quiz access rule (`quizaccess_onesession`) for the
  "block concurrent connections" criterion. Without it, that criterion is scored as not met.

## Installation

1. Copy this folder to `local/quizmetamonitor` in your Moodle code (`public/local/quizmetamonitor`
   on Moodle 5.1 and later), or install the ZIP from *Site administration > Plugins > Install plugins*.
2. Visit *Site administration > Notifications* to complete the installation.
3. Optionally score quizzes that existed before the installation: *Site administration > Plugins >
   Local plugins > Quiz Metadata Monitor > Backfill existing quizzes*.

## Configuration

All pages are under *Site administration > Plugins > Local plugins > Quiz Metadata Monitor*:

- **Configure scoring criteria**: enable criteria and set their points, site-wide or per category.
- **Configure alert threshold**: the score below which alerts are sent, site-wide or per category.
- **Assign Exam Monitors**: users who receive the below-threshold alerts for a course category.
- **Email templates**: subject and body of every email, with placeholders.
- **Export report**: CSV export of quiz security metadata.

Teachers see the scores of their quizzes in the course navigation (*My quiz security scores*).

## Scheduled tasks

- *Check upcoming quizzes below threshold* (every 30 minutes): sends the below-threshold alerts.
- *Resync all quiz security scores* (nightly): rescores quizzes created since the plugin was installed,
  quizzes edited since then, and any quiz without a score.

## Privacy

The plugin stores which user created, edited or restored a quiz (score history), which notifications
were sent to whom, Exam Monitor assignments, and who changed the criteria or threshold. It implements
the Moodle Privacy API for export and deletion of this data.

## License

2026 Anthony Bassil <anthonybassil101@gmail.com>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the License,
or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see
<https://www.gnu.org/licenses/>.
