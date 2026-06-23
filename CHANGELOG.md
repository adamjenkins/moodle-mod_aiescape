# Changelog

All notable changes to `mod_aiescape` are documented in this file.

## [1.0.1] - 2026-06-23

### Fixed

- **Backup/restore was silently dropping data.** `choicesgood`, `choicesneutral`, `choicesbad`, `showpremise`, `showgoal`, `showchoicecounts`, `previewhoverhints`, `flagkeywords`, the button `usagelimit`, and the attempt `ispreview` flag were missing from the backup structure, and the entire `aiescape_flags` table had no backup/restore element at all. Restoring or duplicating a course could throw a database error or silently revert these fields to their defaults. All fields and the flags table now round-trip correctly, and a new test suite guards against regressing this.
- Replaced the legacy `aiescape_get_completion_state()` completion callback with a `\mod_aiescape\completion\custom_completion` class implementing the modern Hooks-era completion API (required for activities targeting Moodle 5.0+). The "Complete the scenario" rule now appears correctly wherever Moodle surfaces custom completion conditions.
- The AI provider endpoint shown in **Site administration → Plugins → Activity modules → AI Escape Room** no longer displays embedded credentials (e.g. basic-auth tokens in a self-hosted endpoint URL) in plain text.
- The activity page no longer presents one arbitrarily-chosen AI provider as "the" active provider when multiple providers are enabled site-wide; it now shows a generic notice instead of potentially misleading information.
- The AMD module bundle (`amd/build/game.min.js`) was out of sync with its source after recent edits; rebuilt via `grunt amd`.

### Added

- A baseline PHPUnit suite (`tests/response_parser_test.php`, `attempt_manager_test.php`, `custom_completion_test.php`, `backup_restore_test.php`, plus a data generator) and a Behat feature (`tests/behat/view_activity.feature`) covering the previously untested core logic, completion behaviour, and backup/restore round-trip.
- Privacy metadata now discloses that student messages and conversation history are sent to the configured `core_ai` provider for processing (third-party data sharing).
- Client-side numeric validation on the good/neutral/bad choice count fields in the activity settings form.
- A code comment documenting why activity completion is intentionally derived only from the numeric step tally, never from the AI's self-reported `completed` flag, to prevent a future prompt-injection regression.
- CI: added PHP 8.4 to the test matrix, cached Composer dependencies between runs, and reduced the matrix for pull requests (latest Moodle branch only) while keeping the full cross-product on pushes.

### Changed

- CI: removed `continue-on-error` from the Grunt and Mustache Lint steps so they actually gate merges instead of running advisory-only.
- `window.confirm()` replaced with `core/notification`'s confirm dialog for the quit-attempt prompt, and a busy-state guard added to prevent double-submission.
- The hardcoded "AI" badge text on the module page now uses a language string.

## [1.0.0] - 2026-06-18

### Added

- **Teacher preview** — non-editing teachers, editing teachers, and managers can now click "Start Game" and play through the activity themselves. These preview attempts are tracked separately (`ispreview`) and are excluded from attempt limits, gradebook/completion updates, and the attempts report/flagged-messages views, so previewing never affects real student data or shows up as a "student" in the report.
- **Show choice type on hover (preview only)** — an optional setting (multichoice/combo only) that, while previewing, reveals whether each choice button is good/neutral/bad via a tooltip and a green/grey/red hover background. Only visible to users with the view-reports capability; real students never see it.

### Fixed

- The activity description no longer renders twice on the module page. Moodle's core activity header already displays it once inside `$OUTPUT->header()`; the plugin's duplicate manual `echo` has been removed.
- Additional (secondary) buttons previously triggered **two** AI calls per click and never persisted their effect, so they couldn't actually influence the story. A button click now persists its instruction into the conversation history and issues exactly **one** AI call, so it affects the current reply and every subsequent turn for the rest of the attempt.
- An exhausted additional button (usage limit reached) no longer surfaces a blocking error that left the story's own choice buttons stuck disabled. The button is now greyed out client-side once exhausted, and the story continues normally.

### Added

- **Display premise** / **Display goal** — independent checkboxes (similar to Moodle's standard "Display description on course page" option) shown directly under the Premise and Goal fields, letting teachers surface scenario details to students on the module page.
- **Show choice counts to students** — an optional, colour-coded summary of the good/neutral/bad choice counts offered each turn, shown on the module page (multichoice/combo only).
- **Per-button usage limits** — each additional button (preset or custom) can have its own maximum number of uses per attempt, typed directly into the activity settings form. Defaults to unlimited. Administrators can set a default limit per preset button.
- **Keyword flagging** — teachers can configure a list of keywords/phrases; matching free-text student responses (free text or combo mode) are flagged for review in a new **Flagged attempts** report, and highlighted in the attempt replay view.

### Changed

- Database schema: added `aiescape.showpremise`, `aiescape.showgoal`, `aiescape.showchoicecounts`, `aiescape.flagkeywords`, `aiescape_buttons.usagelimit`, and the new `aiescape_flags` table.
- Database schema: added `aiescape.previewhoverhints` and `aiescape_attempts.ispreview`.
- `mod/aiescape:play` is now granted to the `teacher`, `editingteacher`, and `manager` archetypes (previously `student` only), enabling teacher preview.
- Refactored the shared "build prompt → call AI → parse → correct choice counts" logic out of `send_message` into `attempt_manager::run_ai_turn()`, used by both normal turns and button turns.
- Privacy provider (`\mod_aiescape\privacy\provider`) updated to export/delete `aiescape_flags` data alongside attempts and messages.
