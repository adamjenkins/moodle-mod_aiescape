# Changelog

All notable changes to `mod_aiescape` are documented in this file.

## Unreleased

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
- Refactored the shared "build prompt → call AI → parse → correct choice counts" logic out of `send_message` into `attempt_manager::run_ai_turn()`, used by both normal turns and button turns.
- Privacy provider (`\mod_aiescape\privacy\provider`) updated to export/delete `aiescape_flags` data alongside attempts and messages.
