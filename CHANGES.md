## [Unreleased]

### Fixed

- Reloading the game no longer changes the step tally: an opening/refresh request (no choice, no typed text) never applies the AI's step change.
- Backup/restore and course import now shift the open and close dates with the course start date.
- Course reset with a new start date now shifts the open and close dates.
- Privacy API: `get_users_in_context` no longer reports AI Escape Room users for other modules' contexts that share an instance id.

## [1.1.4] - 2026-10-03

### Changed

- Declare Moodle 5.3 support.
