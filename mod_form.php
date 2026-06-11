<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Activity settings form for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for the AI Escape Room activity.
 */
class mod_aiescape_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 1333), 'maxlength', 1333, 'client');

        $this->standard_intro_elements();

        // Scenario section.
        $mform->addElement('header', 'scenariosection', get_string('scenariosettings', 'mod_aiescape'));
        $mform->setExpanded('scenariosection');

        $mform->addElement('textarea', 'premise', get_string('premise', 'mod_aiescape'), ['rows' => 8, 'cols' => 60]);
        $mform->setType('premise', PARAM_TEXT);
        $mform->addRule('premise', get_string('error:premiserequired', 'mod_aiescape'), 'required', null, 'client');
        $mform->addHelpButton('premise', 'premise', 'mod_aiescape');

        $mform->addElement('textarea', 'goal', get_string('goal', 'mod_aiescape'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('goal', PARAM_TEXT);
        $mform->addRule('goal', get_string('error:goalrequired', 'mod_aiescape'), 'required', null, 'client');
        $mform->addHelpButton('goal', 'goal', 'mod_aiescape');

        // Game settings section.
        $mform->addElement('header', 'gamesection', get_string('gamesettings', 'mod_aiescape'));
        $mform->setExpanded('gamesection');

        $radiostyle = [];
        $radiostyle[] = $mform->createElement(
            'radio',
            'gamestyle',
            '',
            get_string('gamestyle_narrative', 'mod_aiescape'),
            'narrative'
        );
        $radiostyle[] = $mform->createElement(
            'radio',
            'gamestyle',
            '',
            get_string('gamestyle_persona', 'mod_aiescape'),
            'persona'
        );
        $mform->addGroup($radiostyle, 'gamestyle_group', get_string('gamestyle', 'mod_aiescape'), ['<br/>'], false);
        $mform->setDefault('gamestyle', 'narrative');
        $mform->addHelpButton('gamestyle_group', 'gamestyle', 'mod_aiescape');

        $mform->addElement('text', 'personaname', get_string('personaname', 'mod_aiescape'), ['size' => 40]);
        $mform->setType('personaname', PARAM_TEXT);
        $mform->addHelpButton('personaname', 'personaname', 'mod_aiescape');
        $mform->hideIf('personaname', 'gamestyle', 'neq', 'persona');

        $gamemodes = [
            'multichoice' => get_string('gamemode_multichoice', 'mod_aiescape'),
            'freetext'    => get_string('gamemode_freetext', 'mod_aiescape'),
            'combo'       => get_string('gamemode_combo', 'mod_aiescape'),
        ];
        $mform->addElement('select', 'gamemode', get_string('gamemode', 'mod_aiescape'), $gamemodes);
        $mform->setDefault('gamemode', 'multichoice');
        $mform->addHelpButton('gamemode', 'gamemode', 'mod_aiescape');

        $mform->addElement('text', 'choicesgood', get_string('choicesgood', 'mod_aiescape'), ['size' => 3]);
        $mform->setType('choicesgood', PARAM_INT);
        $mform->setDefault('choicesgood', 1);
        $mform->addHelpButton('choicesgood', 'choicesgood', 'mod_aiescape');
        $mform->hideIf('choicesgood', 'gamemode', 'eq', 'freetext');

        $mform->addElement('text', 'choicesneutral', get_string('choicesneutral', 'mod_aiescape'), ['size' => 3]);
        $mform->setType('choicesneutral', PARAM_INT);
        $mform->setDefault('choicesneutral', 1);
        $mform->addHelpButton('choicesneutral', 'choicesneutral', 'mod_aiescape');
        $mform->hideIf('choicesneutral', 'gamemode', 'eq', 'freetext');

        $mform->addElement('text', 'choicesbad', get_string('choicesbad', 'mod_aiescape'), ['size' => 3]);
        $mform->setType('choicesbad', PARAM_INT);
        $mform->setDefault('choicesbad', 1);
        $mform->addHelpButton('choicesbad', 'choicesbad', 'mod_aiescape');
        $mform->hideIf('choicesbad', 'gamemode', 'eq', 'freetext');

        $mform->addElement('text', 'steps', get_string('steps', 'mod_aiescape'), ['size' => 5]);
        $mform->setType('steps', PARAM_INT);
        $mform->setDefault('steps', 10);
        $mform->addHelpButton('steps', 'steps', 'mod_aiescape');

        // Attempt settings section.
        $mform->addElement('header', 'attemptsection', get_string('attemptsettings', 'mod_aiescape'));

        $maxattemptopts = [
            1  => 1,
            2  => 2,
            3  => 3,
            5  => 5,
            10 => 10,
            -1 => get_string('maxattempts_unlimited', 'mod_aiescape'),
        ];
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mod_aiescape'), $maxattemptopts);
        $mform->setDefault('maxattempts', -1);
        $mform->addHelpButton('maxattempts', 'maxattempts', 'mod_aiescape');

        $mform->addElement('selectyesno', 'showprogress', get_string('showprogress', 'mod_aiescape'));
        $mform->setDefault('showprogress', 1);
        $mform->addHelpButton('showprogress', 'showprogress', 'mod_aiescape');

        $mform->addElement('selectyesno', 'allowstudentreview', get_string('allowstudentreview', 'mod_aiescape'));
        $mform->setDefault('allowstudentreview', 0);
        $mform->addHelpButton('allowstudentreview', 'allowstudentreview', 'mod_aiescape');

        $mform->addElement('selectyesno', 'partialscoreonquit', get_string('partialscoreonquit', 'mod_aiescape'));
        $mform->setDefault('partialscoreonquit', 0);
        $mform->addHelpButton('partialscoreonquit', 'partialscoreonquit', 'mod_aiescape');

        // Additional buttons section.
        $mform->addElement('header', 'buttonssection', get_string('otherbuttonssection', 'mod_aiescape'));
        $mform->addHelpButton('buttonssection', 'otherbuttonssection', 'mod_aiescape');

        // Preset buttons (admin-defined).
        $haspresets = false;
        for ($i = 1; $i <= 5; $i++) {
            $presetlabel = trim((string) get_config('mod_aiescape', 'defaultbutton' . $i . 'label'));
            if ($presetlabel === '') {
                continue;
            }
            if (!$haspresets) {
                $mform->addElement(
                    'static',
                    'presetbtnheader',
                    '',
                    '<strong>' . get_string('presetbuttonssection', 'mod_aiescape') . '</strong>'
                );
                $haspresets = true;
            }
            $mform->addElement(
                'advcheckbox',
                'presetbtn' . $i,
                get_string('presetbuttonenable', 'mod_aiescape', s($presetlabel)),
                '',
                [],
                [0, 1]
            );
            $mform->setDefault('presetbtn' . $i, 0);
        }

        $repeatarray = [
            $mform->createElement('text', 'buttonlabel', get_string('buttonlabel', 'mod_aiescape'), ['size' => 30]),
            $mform->createElement(
                'textarea',
                'buttonprompt',
                get_string('buttonprompt', 'mod_aiescape'),
                ['rows' => 2, 'cols' => 50]
            ),
        ];
        $repeateloptions = [
            'buttonlabel'  => ['type' => PARAM_TEXT],
            'buttonprompt' => ['type' => PARAM_TEXT, 'helpbutton' => ['buttonprompt', 'mod_aiescape']],
        ];
        $this->repeat_elements(
            $repeatarray,
            $this->get_button_count(),
            $repeateloptions,
            'buttoncount',
            'addbuttonbtn',
            1,
            get_string('addbutton', 'mod_aiescape'),
            true
        );

        // Grading section.
        $this->standard_grading_coursemodule_elements();

        // Standard module elements (visibility, groups, etc.).
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Returns the number of existing buttons to pre-populate the repeating group.
     *
     * @return int
     */
    private function get_button_count() {
        global $DB;

        if ($this->current->instance) {
            $count = $DB->count_records('aiescape_buttons', ['aiescape' => $this->current->instance]);
            return max(1, $count);
        }
        return 1;
    }

    /**
     * Pre-populate form data when editing an existing instance.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        parent::data_preprocessing($defaultvalues);

        if ($this->current->instance) {
            $buttons = $DB->get_records(
                'aiescape_buttons',
                ['aiescape' => $this->current->instance],
                'sortorder ASC'
            );
            $i = 0;
            foreach ($buttons as $button) {
                if ($button->defaultindex !== null) {
                    // Pre-check the corresponding preset checkbox.
                    $defaultvalues['presetbtn' . (int) $button->defaultindex] = 1;
                } else {
                    $defaultvalues['buttonlabel[' . $i . ']']  = $button->label;
                    $defaultvalues['buttonprompt[' . $i . ']'] = $button->prompt;
                    $i++;
                }
            }
        }
    }

    /**
     * Server-side validation.
     *
     * @param array $data Submitted form data
     * @param array $files Uploaded files
     * @return array Errors keyed by field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (($data['gamestyle'] ?? 'narrative') === 'persona' && empty(trim($data['personaname'] ?? ''))) {
            $errors['personaname'] = get_string('error:personanamerequired', 'mod_aiescape');
        }

        if (empty(trim($data['premise'] ?? ''))) {
            $errors['premise'] = get_string('error:premiserequired', 'mod_aiescape');
        }

        if (empty(trim($data['goal'] ?? ''))) {
            $errors['goal'] = get_string('error:goalrequired', 'mod_aiescape');
        }

        $steps = (int) ($data['steps'] ?? 0);
        if ($steps < 1 || $steps > 100) {
            $errors['steps'] = get_string('error:stepsinvalid', 'mod_aiescape');
        }

        if (($data['gamemode'] ?? 'multichoice') !== 'freetext') {
            $good    = (int) ($data['choicesgood'] ?? 1);
            $neutral = (int) ($data['choicesneutral'] ?? 1);
            $bad     = (int) ($data['choicesbad'] ?? 1);

            if ($good < 1) {
                $errors['choicesgood'] = get_string('error:choicesgoodrequired', 'mod_aiescape');
            } else if ($good > 5) {
                $errors['choicesgood'] = get_string('error:choicescountinvalid', 'mod_aiescape');
            }
            if ($neutral < 0 || $neutral > 5) {
                $errors['choicesneutral'] = get_string('error:choicescountinvalid', 'mod_aiescape');
            }
            if ($bad < 0 || $bad > 5) {
                $errors['choicesbad'] = get_string('error:choicescountinvalid', 'mod_aiescape');
            }
        }

        return $errors;
    }
}
