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
 * Language strings for mod_aiescape.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Core module strings.
$string['modulename'] = 'AI Escape Room';
$string['modulenameplural'] = 'AI Escape Rooms';
$string['pluginadministration'] = 'AI Escape Room administration';
$string['pluginname'] = 'AI Escape Room';
$string['modulename_help'] = 'The AI Escape Room activity places students inside an AI-driven story or scenario. Students interact with the AI to reach a specified goal, earning a grade on completion.';

// Settings form — scenario section.
$string['scenariosettings'] = 'Scenario';
$string['premise'] = 'Premise';
$string['premise_help'] = 'Describe the story or character the AI will play. This can be narration style (e.g. "You wake up in a dark cave…") or character style (e.g. "You are Luigi, who hates pineapple on pizza…"). The AI uses this as its starting instructions.';
$string['goal'] = 'Goal';
$string['goal_help'] = 'Describe the condition that marks the scenario as complete (e.g. "Escape the cave and reach sunlight" or "Convince Luigi to try pineapple on pizza"). The AI uses this to decide when to award completion.';

// Settings form — game section.
$string['gamesettings'] = 'Game settings';
$string['gamestyle'] = 'Interaction style';
$string['gamestyle_help'] = 'Controls how the AI presents itself. In Narrative style the AI narrates a story and the student takes actions. In Persona style the AI plays a named character and the student holds a conversation with them — choice buttons become dialogue options the student says to the persona.';
$string['gamestyle_narrative'] = 'Narrative — AI narrates a story, choices are actions';
$string['gamestyle_persona'] = 'Persona — AI plays a named character, choices are dialogue';
$string['personaname'] = 'Persona name';
$string['personaname_help'] = 'The name of the character the AI will play (e.g. "Professor Aldric" or "The Merchant"). Shown to students in the conversation.';
$string['error:personanamerequired'] = 'A persona name is required when Persona style is selected.';
$string['gamemode'] = 'Game mode';
$string['gamemode_help'] = 'Controls how students respond to the AI. Multichoice presents three labelled buttons. Freetext provides a text input. Combo shows both.';
$string['gamemode_multichoice'] = 'Multiple choice';
$string['gamemode_freetext'] = 'Free text';
$string['gamemode_combo'] = 'Combo (buttons + text)';
$string['steps'] = 'Steps to complete';
$string['steps_help'] = 'The number of positive steps needed to reach the goal. Good choices add one step, neutral choices add none, bad choices subtract one. The AI finishes the story when this tally is reached.';

// Settings form — attempt section.
$string['attemptsettings'] = 'Attempt settings';
$string['maxattempts'] = 'Maximum attempts';
$string['maxattempts_help'] = 'How many times a student may attempt this activity. Choose Unlimited to allow unlimited retries.';
$string['maxattempts_unlimited'] = 'Unlimited';
$string['showprogress'] = 'Show progress bar';
$string['showprogress_help'] = 'If enabled, students will see a progress bar indicating how many steps they have accumulated towards the goal.';
$string['allowstudentreview'] = 'Allow students to review their own attempts';
$string['allowstudentreview_help'] = 'If enabled, students can view a read-only replay of their past attempts from the activity page.';
$string['partialscoreonquit'] = 'Award partial score on quit';
$string['partialscoreonquit_help'] = 'If enabled, students who quit mid-attempt will receive a grade proportional to their progress at the time of quitting (steps accumulated ÷ steps required × maximum grade). If disabled, quitting earns no grade.';

// Settings form — other buttons section.
$string['otherbuttonssection'] = 'Additional buttons';
$string['otherbuttonssection_help'] = 'Add optional secondary buttons that send a custom prompt to the AI without affecting the step tally. Useful for help, translation, or simplification features.';
$string['buttonlabel'] = 'Button label';
$string['buttonprompt'] = 'Button prompt';
$string['buttonprompt_help'] = 'The prompt sent to the AI when this button is pressed (e.g. "Summarise your last response in Japanese, then return to English").';
$string['addbutton'] = 'Add button';
$string['deletebutton'] = 'Delete button';
$string['presetbuttonssection'] = 'Preset buttons';
$string['presetbuttonssection_help'] = 'Buttons configured by the site administrator. Enable any you want to offer to students.';
$string['presetbuttonenable'] = 'Include: {$a}';

// Validation errors.
$string['error:premiserequired'] = 'A premise is required.';
$string['error:goalrequired'] = 'A goal is required.';
$string['error:stepsinvalid'] = 'Steps to complete must be a whole number between 1 and 100.';
$string['error:buttonlabelrequired'] = 'A label is required for each button.';
$string['error:buttonpromptrequired'] = 'A prompt is required for each button.';
$string['error:maxattemptsreached'] = 'You have used all of your allowed attempts for this activity.';
$string['error:nopermission'] = 'You do not have permission to perform this action.';
$string['error:invalidattempt'] = 'The specified attempt could not be found.';
$string['error:aifailed'] = 'The AI service did not respond. Please try again.';
$string['error:invalidcmid'] = 'Invalid course module ID.';

// Admin settings.
$string['aiinfo_heading'] = 'AI configuration';
$string['aiinfo_noproviders'] = 'No AI providers are currently enabled. Configure providers at Site administration → AI → AI providers.';
$string['aiinfo_provider'] = 'Provider';
$string['aiinfo_model'] = 'Model';
$string['aiinfo_endpoint'] = 'Endpoint';
$string['showaiproviderinfo'] = 'Show AI configuration to teachers';
$string['showaiproviderinfo_desc'] = 'If enabled, teachers will see the active AI provider name and model on the activity page.';
$string['defaultbuttonssection'] = 'Default additional buttons';
$string['defaultbuttonssection_desc'] = 'Define up to five preset buttons that teachers can enable per activity. Leave label blank to disable a slot.';
$string['defaultbuttonlabel'] = 'Button {$a} label';
$string['defaultbuttonprompt'] = 'Button {$a} prompt';

// Game UI strings.
$string['startgame'] = 'Start Game';
$string['resumegame'] = 'Continue';
$string['sendmessage'] = 'Send';
$string['yourturn'] = 'Your turn';
$string['waitingforai'] = 'Waiting for AI...';
$string['progresslabel'] = 'Progress: {$a->tally} / {$a->steps} steps';
$string['attemptcompleted'] = 'Scenario complete!';
$string['attemptcompletedmessage'] = 'Congratulations - you have completed the scenario and earned your grade.';
$string['yourtextplaceholder'] = 'Type your response...';
$string['newattempt'] = 'Start a new attempt';
$string['quitattempt'] = 'Quit attempt';
$string['quitattempt_confirm'] = 'Are you sure you want to quit? This attempt will be abandoned and cannot be resumed.';
$string['attemptabandoned'] = 'Attempt abandoned.';
$string['viewattempts'] = 'View my past attempts';

// Report page.
$string['report'] = 'Attempts report';
$string['reportheading'] = 'Attempt report: {$a}';
$string['nousers'] = 'No students have attempted this activity yet, and none are enrolled.';
$string['noattempts'] = 'No attempts recorded.';
$string['student'] = 'Student';
$string['attempts'] = 'Attempts';
$string['completed'] = 'Completed';
$string['grade'] = 'Grade';
$string['lastattempt'] = 'Last attempt';
$string['viewattempt'] = 'View attempt';
$string['attemptnumber'] = 'Attempt {$a}';
$string['attemptstarted'] = 'Started';
$string['attemptfinished'] = 'Completed';
$string['statuslabel'] = 'Status';
$string['status_inprogress'] = 'In progress';
$string['status_completed'] = 'Completed';
$string['status_abandoned'] = 'Abandoned';
$string['backtolist'] = 'Back to student list';
$string['myattempts'] = 'My attempts';
$string['myattemptsheading'] = 'My past attempts';

// Completion.
$string['completiondetail:completed'] = 'Complete the scenario';
$string['completionpass'] = 'Student must complete the scenario to earn a grade';

// Privacy.
$string['privacy:metadata:aiescape_attempts'] = 'Records of each student attempt, including status and progress.';
$string['privacy:metadata:aiescape_attempts:userid'] = 'The ID of the student.';
$string['privacy:metadata:aiescape_attempts:status'] = 'The status of the attempt (in progress, completed, abandoned).';
$string['privacy:metadata:aiescape_attempts:stepstally'] = 'The running step tally at the time the data was exported.';
$string['privacy:metadata:aiescape_attempts:timecreated'] = 'The time the attempt was started.';
$string['privacy:metadata:aiescape_attempts:timecompleted'] = 'The time the attempt was completed, if applicable.';
$string['privacy:metadata:aiescape_messages'] = 'The full conversation history for each attempt.';
$string['privacy:metadata:aiescape_messages:message'] = 'The text of each message sent or received during the attempt.';
$string['privacy:metadata:aiescape_messages:role'] = 'Whether the message was sent by the student (user) or the AI (assistant).';
$string['privacy:metadata:aiescape_messages:timecreated'] = 'The time the message was recorded.';

// Events.
$string['eventcoursemoduleviewed'] = 'AI Escape Room viewed';
$string['eventattemptstarted'] = 'Attempt started';
$string['eventattemptcompleted'] = 'Attempt completed';
$string['eventattemptabandoned'] = 'Attempt abandoned';

// Capabilities.
$string['aiescape:addinstance'] = 'Add an AI Escape Room';
$string['aiescape:view'] = 'View an AI Escape Room';
$string['aiescape:play'] = 'Play an AI Escape Room';
$string['aiescape:viewreports'] = 'View attempt reports';
$string['aiescape:viewownattempts'] = 'View own past attempts';
