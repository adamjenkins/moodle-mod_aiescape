## [1.1.5] - 2026-10-04

### Fixed

- Reloading the game no longer changes the step tally: an opening or refresh request (no choice and no typed text) never applies the AI's step change, so only a choice or a typed answer scores.
- Backup/restore and course import now shift the open and close dates with the course start date, so a restored activity no longer stays closed to students.
- Course reset with a new start date now shifts the open and close dates.
- Privacy API: `get_users_in_context` no longer reports AI Escape Room users for other modules' contexts that share an instance id.

### Changed

- Automated tests now run against the released Moodle 5.3 (MOODLE_503_STABLE) instead of Moodle's development branch.
- The release workflow that publishes to the camp plugin registry now uses the maintained shared workflow.
