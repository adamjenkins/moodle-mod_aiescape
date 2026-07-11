# AI Escape Room (`mod_aiescape`)

A Moodle activity plugin that creates an AI-driven interactive escape room experience. Students work through a scenario by making choices or typing free-text responses, guided by a large language model via Moodle's `core_ai` subsystem.

## Security

Choice submissions are validated server-side. The server records the exact set of choices the AI offered each turn and rejects any submission that does not match — preventing grade forgery via direct web-service calls and ensuring keyword-flagging cannot be bypassed by routing free text through the choice label field.

## Requirements

- Moodle 5.0 or later (requires `core_ai`)
- An AI provider configured in Site administration → AI → AI providers (e.g. OpenAI, Ollama)

## Installation

1. Copy the plugin directory into `<moodleroot>/public/mod/aiescape/`
2. Visit Site administration → Notifications to run the upgrade
3. Configure an AI provider under Site administration → AI → AI providers

## Features

### Interaction modes

| Mode | Description |
|------|-------------|
| **Multiple choice** | Students pick from AI-generated choice buttons each turn |
| **Free text** | Students type their own responses |
| **Combo** | Both choice buttons and a free-text input are available |

### Game styles

- **Narrative style** — the AI narrates a story; the student takes actions as the protagonist.
- **Persona style** — the AI plays a named character; the student speaks directly to that character using first-person dialogue.

### Configurable choice counts

In multiple choice and combo modes, teachers control exactly how many of each type of choice button the AI offers per turn:

- **Good choices** (1–5) — each adds one step toward the goal; at least one is required
- **Neutral choices** (0–5) — do not change the step tally
- **Bad choices** (0–5) — each subtracts one step

The AI prompt is constructed to request the exact specified counts. If the AI returns the wrong number (or malformed output), Moodle automatically re-asks it for a complete, correctly-formatted turn, up to the **Choice format retry limit** admin setting's number of times. If every retry still fails, students are offered a single safe "Free turn: Roll the dice..." option instead of invented placeholder choices — selecting it simply continues the story and can never cost the student progress, even if the AI evaluates it negatively.

### Additional buttons

Teachers can add extra one-click "action" buttons (e.g. "Hint", "Clue") that send a custom prompt to the AI. The button's instruction is recorded in the conversation history, so it affects the AI's current reply and every turn for the rest of the attempt — without changing the step tally. Each button can have its own usage limit (a maximum number of uses per attempt, or unlimited); once exhausted, the button is greyed out for the rest of that attempt without interrupting the story. Administrators can pre-define up to five default buttons (with their own default usage limit) that teachers enable, and optionally override the limit for, per activity.

### Module page display options

Teachers can optionally surface scenario details directly on the module page (outside of gameplay):

- **Display premise / Display goal** — independent checkboxes, similar to Moodle's standard "Display description on course page" option, shown directly below the Premise and Goal fields in the activity settings
- **Show choice counts to students** — displays the number of good/neutral/bad choices offered each turn as colour-coded pills (multichoice/combo only)

### Moderation: keyword flagging

Teachers can configure a list of keywords or phrases (one per line, case-insensitive). Any free-text student response (free text or combo mode) containing a match is automatically flagged for review in a dedicated **Flagged attempts** report, alongside the matched keyword and a link to the full attempt replay. Flagged messages are also highlighted in the attempt replay view.

### Attempt management

- Configurable maximum attempts per student
- **Continue** button resumes an in-progress attempt
- **Quit attempt** button lets students abandon a run early
- Optional partial scoring on quit (awards grade proportional to steps completed)
- Student review of completed attempts (configurable per activity)
- **Course reset** — the standard Moodle course-reset flow includes an "AI Escape Rooms" section with a "Delete all AI Escape Room attempts" checkbox (ticked by default), which removes all attempt data and resets gradebook entries for the activities in the course

### Grading

- Standard Moodle gradebook integration via `core_grades`
- Completed attempts receive full grade; abandoned attempts can receive partial grade (configurable per activity)
- Grade-based and passing-grade completion tracking work correctly (`completionusegrade`, `completionpassgrade`)
- Grade item `idnumber` is synchronised with the course module's ID number (`cmidnumber`), enabling grade-import by column and outcome mapping

### Fullscreen mode

A fullscreen toggle button in the top-right corner of the game interface lets students expand the activity to fill the browser window. The chat log expands to use the available height. The button is hidden automatically on browsers that do not support the Fullscreen API.

### Teacher preview

Non-editing teachers, editing teachers, and managers can click "Start Game" and play through the activity themselves, exactly like a student. These preview attempts are tracked separately from real student attempts and are excluded from attempt limits, gradebook/completion updates, and the attempts report (including the flagged-messages view) — previewing never affects real student data, and a previewing teacher never shows up as a "student" in the report.

In multiple choice and combo modes, enabling **Show choice type on hover (preview only)** reveals each choice button's good/neutral/bad type via a tooltip and a colour-coded (green/grey/red) hover background, to help teachers sanity-check their premise/goal configuration. This is only visible to users with the `viewreports` capability; real students never see it.

## Activity settings

| Setting | Description |
|---------|-------------|
| Open/Close the escape room | Optional open and close dates (shown on the course page like the quiz module); attempts still open at the close date are automatically abandoned |
| Premise | Describes the scenario or character the AI will portray |
| Goal | The condition that completes the escape room |
| Game style | Narrative (AI narrates) or Persona (AI plays a named character) |
| Persona name | Name of the character — Persona style only |
| Game mode | Multiple choice / Free text / Combo |
| Good choices per turn | How many "good" choice buttons to offer each turn (1–5; multichoice/combo only) |
| Neutral choices per turn | How many "neutral" choice buttons to offer each turn (0–5; multichoice/combo only) |
| Bad choices per turn | How many "bad" choice buttons to offer each turn (0–5; multichoice/combo only) |
| Display premise | Show the premise to students on the module page |
| Display goal | Show the goal to students on the module page |
| Steps | Number of successful moves required to complete |
| Max attempts | How many attempts a student may make |
| Show progress | Display step tally to students during play |
| Allow student review | Let students re-read completed attempts |
| Partial score on quit | Award proportional grade when a student quits early |
| Show choice counts to students | Display the good/neutral/bad choice counts on the module page (multichoice/combo only) |
| Show choice type on hover (preview only) | While previewing, reveal each choice's good/neutral/bad type on hover (multichoice/combo only); never shown to real students |
| Flag keywords | One keyword/phrase per line; flags matching free-text responses for teacher review |
| Additional buttons | Extra one-click prompt buttons added to the game interface, each with its own optional usage limit |

## Admin settings

Found at Site administration → Plugins → Activity modules → AI Escape Room.

- **AI provider info** — read-only table showing the active provider name, type, model, and endpoint
- **Show AI provider info to teachers** — surface the provider badge on the activity page for users with the `viewreports` capability
- **Choice format retry limit** — how many times to re-ask the AI for a correctly-formatted turn before falling back to a single safe "Free turn: Roll the dice..." option (0–5, default 2)
- **Default buttons (slots 1–5)** — pre-define up to five buttons (label, prompt, and default usage limit) that teachers can enable per activity

## Privacy

This plugin stores the following personal data:

- Attempt records (start time, completion time, status, grade)
- Conversation messages (role, content, timestamp)
- Keyword-match flags raised against a student's own messages (matched keyword, timestamp)

All personal data is exportable and deletable via Moodle's Privacy API (`\mod_aiescape\privacy\provider`).

Student messages, together with the activity's premise, goal, and conversation history, are sent to the AI provider configured by the site administrator (Site administration → AI → AI providers) so it can generate a response. This third-party data sharing is disclosed in the plugin's privacy metadata.

## Testing

PHPUnit and Behat coverage live under `tests/`. From the Moodle root, with the plugin installed at `mod/aiescape`:

```sh
php admin/tool/phpunit/cli/init.php          # one-time environment setup
vendor/bin/phpunit --testsuite=mod_aiescape_testsuite
vendor/bin/behat --tags=@mod_aiescape
```

## License

GNU GPL v3 or later — https://www.gnu.org/licenses/gpl-3.0.html
