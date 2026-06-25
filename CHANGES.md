# mod_aiescape 1.0.3

## Changed

- **Replaced invented placeholder choices with a retry-then-safe-fallback flow.** When the AI fails to return the correctly-formatted choices for a turn, Moodle now re-asks it for a complete, correctly-formatted turn (not just a choices patch) up to a configurable number of times, instead of silently substituting canned "Proceed carefully…" / "Wait and observe…" / "Rush ahead blindly…" placeholder choices. If every retry still fails, students are offered a single safe "Free turn: Roll the dice..." option that simply continues the story — selecting it can never reduce the student's progress, even if the AI evaluates the response negatively.

## Added

- New admin setting **Choice format retry limit** (Site administration → Plugins → Activity modules → AI Escape Room) controlling how many times to re-ask the AI before falling back to the free-turn option (0–5, default 2).
- A GitHub Actions workflow (`.github/workflows/moodle-release.yml`) that automatically publishes tagged releases to the Moodle Plugins directory.
- A dedicated `CHANGES.md` file (separate from the changelog) holding just the latest release's notes, as recognised by the Moodle Plugins directory's release-notes importer.
