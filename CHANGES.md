## [1.0.4] - 2026-06-26

### Fixed

- **Additional button labels longer than 20 characters caused database errors or silent data truncation.** The `aiescape_messages.choicetype` column was VARCHAR(20), but `aiescape_buttons.label` allows up to 255 characters. When a button with a long label was pressed, the stored `choicetype` value was silently truncated, which also broke the per-button usage-limit tracking in `attempt_manager::usage_remaining()` (it compares `choicetype` against the button's label). The column has been widened to VARCHAR(255) and a database upgrade step applies the change to existing installations.
- **Teacher preview attempts were counted as real completions.** `custom_completion::get_state()` did not filter out preview attempts (`ispreview = 1`) when checking whether a user had completed the scenario, so a teacher previewing the activity would inadvertently trigger completion for themselves. The query now explicitly requires `ispreview = 0`.
- **Grade item `idnumber` was not synchronised with the course-module `cmidnumber`.** `aiescape_grade_item_update()` was not passing the `cmidnumber` to `grade_update()`, so setting an ID number on the course module (used for outcome mapping and grade import by column) had no effect on the associated grade item. The function now follows the pattern used by the quiz module.
- **Course reset used nested SQL subqueries for deletions in `aiescape_reset_userdata()`.** Replaced with `get_fieldset_select()` + `get_in_or_equal()` to match Moodle's preferred data-manipulation API.
- **Lang string key `choiceretrylimit` was out of alphabetical order** in `lang/en/aiescape.php`. Moved to the correct position between `choicehint_neutral` and `choicesbad`.
