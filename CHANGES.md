## [1.1.0] - 2026-07-11

### Added

- **Open and close dates** (Timing section in the activity settings), mimicking mod_quiz. The dates are surfaced through Moodle's activity-dates API, so "Opens:/Closes:" lines on the course page and activity page render identically to the quiz module. Students cannot start attempts, send messages, or trigger additional buttons outside the window; teachers/managers can preview at any time. Attempts still in progress at the close date are automatically abandoned — lazily when next accessed, and by a new scheduled task (`\mod_aiescape\task\abandon_expired_attempts`) — awarding a partial grade when "Award partial score on quit" is enabled. The new `timeopen`/`timeclose` fields are included in backup/restore, and a database upgrade step adds them to existing installations.
