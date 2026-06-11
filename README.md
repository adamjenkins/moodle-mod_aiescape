# AI Escape Room (`mod_aiescape`)

A Moodle activity plugin that creates an AI-driven interactive escape room experience. Students work through a scenario by making choices or typing free-text responses, guided by a large language model via Moodle's `core_ai` subsystem.

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

The AI prompt is constructed to request the exact specified counts. If the AI returns the wrong number, a single correction prompt is sent automatically before falling back to defaults.

### Additional buttons

Teachers can add extra one-click "action" buttons (e.g. "Hint", "Clue") that trigger a custom AI prompt without affecting the conversation history or step tally. Administrators can pre-define up to five default buttons that teachers enable or disable per activity.

### Attempt management

- Configurable maximum attempts per student
- **Continue** button resumes an in-progress attempt
- **Quit attempt** button lets students abandon a run early
- Optional partial scoring on quit (awards grade proportional to steps completed)
- Student review of completed attempts (configurable per activity)

### Grading

- Standard Moodle gradebook integration
- Completed attempts receive full grade
- Abandoned attempts can receive partial grade (configurable per activity)

### Fullscreen mode

A fullscreen toggle button in the top-right corner of the game interface lets students expand the activity to fill the browser window. The chat log expands to use the available height. The button is hidden automatically on browsers that do not support the Fullscreen API.

## Activity settings

| Setting | Description |
|---------|-------------|
| Premise | Describes the scenario or character the AI will portray |
| Goal | The condition that completes the escape room |
| Game style | Narrative (AI narrates) or Persona (AI plays a named character) |
| Persona name | Name of the character — Persona style only |
| Game mode | Multiple choice / Free text / Combo |
| Good choices per turn | How many "good" choice buttons to offer each turn (1–5; multichoice/combo only) |
| Neutral choices per turn | How many "neutral" choice buttons to offer each turn (0–5; multichoice/combo only) |
| Bad choices per turn | How many "bad" choice buttons to offer each turn (0–5; multichoice/combo only) |
| Steps | Number of successful moves required to complete |
| Max attempts | How many attempts a student may make |
| Show progress | Display step tally to students during play |
| Allow student review | Let students re-read completed attempts |
| Partial score on quit | Award proportional grade when a student quits early |
| Additional buttons | Extra one-click prompt buttons added to the game interface |

## Admin settings

Found at Site administration → Plugins → Activity modules → AI Escape Room.

- **AI provider info** — read-only table showing the active provider name, type, model, and endpoint
- **Show AI provider info to teachers** — surface the provider badge on the activity page for users with the `viewreports` capability
- **Default buttons (slots 1–5)** — pre-define up to five buttons that teachers can enable per activity

## Privacy

This plugin stores the following personal data:

- Attempt records (start time, completion time, status, grade)
- Conversation messages (role, content, timestamp)

All personal data is exportable and deletable via Moodle's Privacy API (`\mod_aiescape\privacy\provider`).

## License

GNU GPL v3 or later — https://www.gnu.org/licenses/gpl-3.0.html
