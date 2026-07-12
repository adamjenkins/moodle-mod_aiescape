## [1.1.1] - 2026-07-11

### Security

- **Choice classifications no longer reach the browser** (self grade-inflation hardening, MDL Shield finding): `send_message` and `trigger_button` now return only each choice's label plus an `isfreeturn` marker. Choices are shuffled server-side, since the parser's good-first grouping would otherwise reveal the classification by array position alone. The client submits only the selected label, and the server resolves the good/neutral/bad step delta from the offered set it stored for that turn; choice sets with duplicate labels are rejected and re-requested from the AI so a label always identifies exactly one choice. The preview hover-hints feature still receives the types, but only for users with `mod/aiescape:viewreports` on activities where it is enabled.
- **Multiple-choice mode now rejects free-typed text** sent directly to the web service, closing an unintended path to AI-evaluated (prompt-injectable) scoring in choice-only activities.

### Fixed

- **Scale grades are rejected in the activity settings** with a clear validation message instead of silently creating a broken gradebook column with a negative maximum (the grading code supports point grades only).
- **Links in intro/premise/goal are decoded again on restore**: `define_decode_contents()` returned an empty array, so `view.php` self-links encoded during backup were left as `$@AIESCAPEVIEWBYID*…@$` tokens after restore.

### Changed

- The teacher report fetches all attempts in one query instead of one query per student.
- Documented that free-text/combo AI scoring is advisory and best suited to formative use (README, game mode help text).

## [1.1.0] - 2026-07-11

### Added

- **Open and close dates** (Timing section in the activity settings), mimicking mod_quiz. The dates are surfaced through Moodle's activity-dates API, so "Opens:/Closes:" lines on the course page and activity page render identically to the quiz module. Students cannot start attempts, send messages, or trigger additional buttons outside the window; teachers/managers can preview at any time. Attempts still in progress at the close date are automatically abandoned — lazily when next accessed, and by a new scheduled task (`\mod_aiescape\task\abandon_expired_attempts`) — awarding a partial grade when "Award partial score on quit" is enabled. The new `timeopen`/`timeclose` fields are included in backup/restore, and a database upgrade step adds them to existing installations.
- **Calendar events** for the open and close dates, matching mod_quiz: "… opens" / "… closes" events appear in the calendar and the timeline block (with a "Start Game" action for students who can still play), dragging an event in the calendar updates the activity dates within a validated range, and `aiescape_refresh_events()` supports course restore and the "Refresh calendar events" tool.
- **Progress images** (ported from mod_quizquest): teachers can upload multiple images that display alongside the conversation; with N images (ordered by file name), each subsequent image appears as the student completes an equal share of the required steps. Images are served through the standard pluginfile API and included in backup/restore.
