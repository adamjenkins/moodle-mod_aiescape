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

$string['abandoned'] = 'Abandoned';
$string['addbutton'] = 'Add button';
$string['aiescape:addinstance'] = 'Add an AI Escape Room';
$string['aiescape:play'] = 'Play an AI Escape Room';
$string['aiescape:view'] = 'View an AI Escape Room';
$string['aiescape:viewownattempts'] = 'View own past attempts';
$string['aiescape:viewreports'] = 'View attempt reports';
$string['aiinfo_badge'] = 'AI';
$string['aiinfo_endpoint'] = 'Endpoint';
$string['aiinfo_heading'] = 'AI configuration';
$string['aiinfo_model'] = 'Model';
$string['aiinfo_multipleproviders'] = 'Multiple AI providers enabled';
$string['aiinfo_noproviders'] = 'No AI providers are currently enabled. Configure providers at Site administration → AI → AI providers.';
$string['aiinfo_provider'] = 'Provider';
$string['allowstudentreview'] = 'Allow students to review their own attempts';
$string['allowstudentreview_help'] = 'If enabled, students can view a read-only replay of their past attempts from the activity page.';
$string['attemptabandoned'] = 'Attempt abandoned.';
$string['attemptcompleted'] = 'Scenario complete!';
$string['attemptcompletedmessage'] = 'Congratulations - you have completed the scenario and earned your grade.';
$string['attemptfinished'] = 'Completed';
$string['attemptnumber'] = 'Attempt {$a}';
$string['attempts'] = 'Attempts';
$string['attemptsettings'] = 'Attempt settings';
$string['attemptstarted'] = 'Started';
$string['backtolist'] = 'Back to student list';
$string['buttonlabel'] = 'Button label';
$string['buttonprompt'] = 'Button prompt';
$string['buttonprompt_help'] = 'The prompt sent to the AI when this button is pressed (e.g. "Summarise your last response in Japanese, then return to English").';
$string['buttonusagelimit'] = 'Usage limit';
$string['buttonusagelimit_help'] = 'The maximum number of times a student may use this button during a single attempt. Leave blank for unlimited use.';
$string['calendareventcloses'] = '{$a} closes';
$string['calendareventopens'] = '{$a} opens';
$string['choicecount_bad'] = '{$a} bad';
$string['choicecount_good'] = '{$a} good';
$string['choicecount_neutral'] = '{$a} neutral';
$string['choicehint_bad'] = 'Bad choice';
$string['choicehint_good'] = 'Good choice';
$string['choicehint_neutral'] = 'Neutral choice';
$string['choiceretrylimit'] = 'Choice format retry limit';
$string['choiceretrylimit_desc'] = 'When the AI fails to return the required choices for a turn, Moodle will re-ask it to produce a complete, correctly-formatted response this many times before giving up. If still unsuccessful, students are offered a single "free turn" option instead of invented choices.';
$string['choicesbad'] = 'Bad choices per turn';
$string['choicesbad_help'] = 'How many "bad" choice buttons to offer each turn (each bad choice subtracts one step). Set to 0 to omit bad choices.';
$string['choicesgood'] = 'Good choices per turn';
$string['choicesgood_help'] = 'How many "good" choice buttons to offer each turn (each good choice adds one step toward the goal). Must be at least 1 in multiple choice or combo mode.';
$string['choicesneutral'] = 'Neutral choices per turn';
$string['choicesneutral_help'] = 'How many "neutral" choice buttons to offer each turn (neutral choices do not change the step tally). Set to 0 to omit neutral choices.';
$string['closebeforeopen'] = 'You have specified a close date before the open date.';
$string['completed'] = 'Completed';
$string['completiondetail:completed'] = 'Complete the scenario';
$string['completionpass'] = 'Student must complete the scenario to earn a grade';
$string['defaultbuttonlabel'] = 'Button {$a} label';
$string['defaultbuttonprompt'] = 'Button {$a} prompt';
$string['defaultbuttonssection'] = 'Default additional buttons';
$string['defaultbuttonssection_desc'] = 'Define up to five preset buttons that teachers can enable per activity. Leave label blank to disable a slot.';
$string['defaultbuttonusagelimit'] = 'Button {$a} default usage limit';
$string['defaultbuttonusagelimit_desc'] = 'Default maximum number of times this preset button may be used per attempt. Leave blank for unlimited. Teachers can override this when enabling the button on an activity.';
$string['deletebutton'] = 'Delete button';
$string['enterfullscreen'] = 'Enter full screen';
$string['error:aifailed'] = 'The AI service did not respond. Please try again.';
$string['error:buttonlabelrequired'] = 'A label is required for each button.';
$string['error:buttonlimitinvalid'] = 'The usage limit must be a positive whole number, or left blank for unlimited.';
$string['error:buttonlimitreached'] = 'This button has already been used the maximum number of times for this attempt.';
$string['error:buttonpromptrequired'] = 'A prompt is required for each button.';
$string['error:choicescountinvalid'] = 'Choice count must be a whole number between 0 and 5.';
$string['error:choicesgoodrequired'] = 'At least one good choice is required in multiple choice or combo mode.';
$string['error:closedon'] = 'This activity closed on {$a}.';
$string['error:goalrequired'] = 'A goal is required.';
$string['error:invalidattempt'] = 'The specified attempt could not be found.';
$string['error:invalidchoice'] = 'The submitted choice was not offered for this turn.';
$string['error:invalidcmid'] = 'Invalid course module ID.';
$string['error:maxattemptsreached'] = 'You have used all of your allowed attempts for this activity.';
$string['error:nopermission'] = 'You do not have permission to perform this action.';
$string['error:notopenyet'] = 'This activity opens on {$a}.';
$string['error:personanamerequired'] = 'A persona name is required when Persona style is selected.';
$string['error:premiserequired'] = 'A premise is required.';
$string['error:stepsinvalid'] = 'Steps to complete must be a whole number between 1 and 100.';
$string['eventattemptabandoned'] = 'Attempt abandoned';
$string['eventattemptcompleted'] = 'Attempt completed';
$string['eventattemptstarted'] = 'Attempt started';
$string['eventcoursemoduleviewed'] = 'AI Escape Room viewed';
$string['exitfullscreen'] = 'Exit full screen';
$string['flaggedattempts'] = 'Flagged attempts';
$string['flaggedreportheading'] = 'Flagged attempts';
$string['flagkeywords'] = 'Flag keywords';
$string['flagkeywords_help'] = 'One keyword or phrase per line. Free-text student responses (free text or combo mode) containing a match (case-insensitive) will be flagged for teacher review in the attempts report. Leave blank to disable.';
$string['freeturnlabel'] = 'Free turn: Roll the dice...';
$string['freeturnmessage'] = 'What next?';
$string['gameclose'] = 'Close the escape room';
$string['gamemode'] = 'Game mode';
$string['gamemode_combo'] = 'Combo (buttons + text)';
$string['gamemode_freetext'] = 'Free text';
$string['gamemode_help'] = 'Controls how students respond to the AI. Multichoice presents three labelled buttons. Freetext provides a text input. Combo shows both.';
$string['gamemode_multichoice'] = 'Multiple choice';
$string['gameopen'] = 'Open the escape room';
$string['gameopenclose'] = 'Open and close dates';
$string['gameopenclose_help'] = 'Students can only start and play attempts between the open and close dates. Any attempt still in progress at the close date is automatically abandoned (with a partial grade if "Award partial score on quit" is enabled). Teachers and managers can preview the activity at any time.';
$string['gamesettings'] = 'Game settings';
$string['gamestyle'] = 'Interaction style';
$string['gamestyle_help'] = 'Controls how the AI presents itself. In Narrative style the AI narrates a story and the student takes actions. In Persona style the AI plays a named character and the student holds a conversation with them — choice buttons become dialogue options the student says to the persona.';
$string['gamestyle_narrative'] = 'Narrative — AI narrates a story, choices are actions';
$string['gamestyle_persona'] = 'Persona — AI plays a named character, choices are dialogue';
$string['goal'] = 'Goal';
$string['goal_help'] = 'Describe the condition that marks the scenario as complete (e.g. "Escape the cave and reach sunlight" or "Convince Luigi to try pineapple on pizza"). The AI uses this to decide when to award completion.';
$string['grade'] = 'Grade';
$string['grademax'] = 'Grade (max: {$a})';
$string['keyword'] = 'Keyword';
$string['lastattempt'] = 'Last attempt';
$string['matchedkeyword'] = 'Matched keyword: {$a}';
$string['maxattempts'] = 'Maximum attempts';
$string['maxattempts_help'] = 'How many times a student may attempt this activity. Choose Unlimited to allow unlimited retries.';
$string['maxattempts_unlimited'] = 'Unlimited';
$string['messageexcerpt'] = 'Message';
$string['moderationsection'] = 'Moderation';
$string['modulename'] = 'AI Escape Room';
$string['modulename_help'] = 'The AI Escape Room activity places students inside an AI-driven story or scenario. Students interact with the AI to reach a specified goal, earning a grade on completion.';
$string['modulenameplural'] = 'AI Escape Rooms';
$string['myattempts'] = 'My attempts';
$string['myattemptsheading'] = 'My past attempts';
$string['newattempt'] = 'Start a new attempt';
$string['noattempts'] = 'No attempts recorded.';
$string['noflagged'] = 'No attempts have been flagged.';
$string['nousers'] = 'No students have attempted this activity yet, and none are enrolled.';
$string['openafterclose'] = 'You have specified an open date after the close date.';
$string['otherbuttonssection'] = 'Additional buttons';
$string['otherbuttonssection_help'] = 'Add optional secondary buttons that send a custom prompt to the AI without affecting the step tally. Useful for help, translation, or simplification features.';
$string['partialscoreonquit'] = 'Award partial score on quit';
$string['partialscoreonquit_help'] = 'If enabled, students who quit mid-attempt will receive a grade proportional to their progress at the time of quitting (steps accumulated ÷ steps required × maximum grade). If disabled, quitting earns no grade.';
$string['personaname'] = 'Persona name';
$string['personaname_help'] = 'The name of the character the AI will play (e.g. "Professor Aldric" or "The Merchant"). Shown to students in the conversation.';
$string['pluginadministration'] = 'AI Escape Room administration';
$string['pluginname'] = 'AI Escape Room';
$string['premise'] = 'Premise';
$string['premise_help'] = 'Describe the story or character the AI will play. This can be narration style (e.g. "You wake up in a dark cave…") or character style (e.g. "You are Luigi, who hates pineapple on pizza…"). The AI uses this as its starting instructions.';
$string['presetbuttonenable'] = 'Include: {$a}';
$string['presetbuttonssection'] = 'Preset buttons';
$string['presetbuttonssection_help'] = 'Buttons configured by the site administrator. Enable any you want to offer to students.';
$string['previewhoverhints'] = 'Show choice type on hover (preview only)';
$string['previewhoverhints_help'] = 'If enabled, hovering over a choice button will reveal whether it is good, neutral, or bad. This only applies to users who can view reports (teachers and managers previewing the activity) — real students never see this. Not applicable in free text mode.';
$string['privacy:metadata:aiescape_attempts'] = 'Records of each student attempt, including status and progress.';
$string['privacy:metadata:aiescape_attempts:status'] = 'The status of the attempt (in progress, completed, abandoned).';
$string['privacy:metadata:aiescape_attempts:stepstally'] = 'The running step tally at the time the data was exported.';
$string['privacy:metadata:aiescape_attempts:timecompleted'] = 'The time the attempt was completed, if applicable.';
$string['privacy:metadata:aiescape_attempts:timecreated'] = 'The time the attempt was started.';
$string['privacy:metadata:aiescape_attempts:userid'] = 'The ID of the student.';
$string['privacy:metadata:aiescape_externalai'] = 'Student messages, along with the activity premise, goal, and conversation history, are sent to the AI provider configured by the site administrator (Site administration → AI → AI providers) so it can generate a response.';
$string['privacy:metadata:aiescape_flags'] = 'Records of messages flagged for teacher review by keyword match.';
$string['privacy:metadata:aiescape_flags:keyword'] = 'The keyword or phrase that matched.';
$string['privacy:metadata:aiescape_flags:timecreated'] = 'The time the message was flagged.';
$string['privacy:metadata:aiescape_messages'] = 'The full conversation history for each attempt.';
$string['privacy:metadata:aiescape_messages:message'] = 'The text of each message sent or received during the attempt.';
$string['privacy:metadata:aiescape_messages:role'] = 'Whether the message was sent by the student (user) or the AI (assistant).';
$string['privacy:metadata:aiescape_messages:timecreated'] = 'The time the message was recorded.';
$string['progresslabel'] = 'Progress: {$a->tally} / {$a->steps} steps';
$string['quitattempt'] = 'Quit attempt';
$string['quitattempt_confirm'] = 'Are you sure you want to quit? This attempt will be abandoned and cannot be resumed.';
$string['report'] = 'Attempts report';
$string['reportheading'] = 'Attempt report: {$a}';
$string['resetattempts'] = 'Delete all AI Escape Room attempts';
$string['resumegame'] = 'Continue';
$string['scenariosettings'] = 'Scenario';
$string['sendmessage'] = 'Send';
$string['showaiproviderinfo'] = 'Show AI configuration to teachers';
$string['showaiproviderinfo_desc'] = 'If enabled, teachers will see the active AI provider name and model on the activity page.';
$string['showchoicecounts'] = 'Show choice counts to students';
$string['showchoicecounts_help'] = 'If enabled, the number of good/neutral/bad choices offered each turn will be displayed to students on the module page. Not applicable in free text mode.';
$string['showgoal'] = 'Display goal on module page';
$string['showgoal_help'] = 'If enabled, the goal will be displayed to students on the module page, similar to how a course page can display an activity\'s description.';
$string['showpremise'] = 'Display premise on module page';
$string['showpremise_help'] = 'If enabled, the premise will be displayed to students on the module page, similar to how a course page can display an activity\'s description.';
$string['showprogress'] = 'Show progress bar';
$string['showprogress_help'] = 'If enabled, students will see a progress bar indicating how many steps they have accumulated towards the goal.';
$string['startgame'] = 'Start Game';
$string['status_abandoned'] = 'Abandoned';
$string['status_completed'] = 'Completed';
$string['status_inprogress'] = 'In progress';
$string['statuslabel'] = 'Status';
$string['steps'] = 'Steps to complete';
$string['steps_help'] = 'The number of positive steps needed to reach the goal. Good choices add one step, neutral choices add none, bad choices subtract one. The AI finishes the story when this tally is reached.';
$string['student'] = 'Student';
$string['taskabandonexpired'] = 'Abandon AI Escape Room attempts past their close date';
$string['timing'] = 'Timing';
$string['viewattempt'] = 'View attempt';
$string['viewattempts'] = 'View my past attempts';
$string['viewflaggedattempts'] = 'View flagged attempts ({$a})';
$string['waitingforai'] = 'Waiting for AI...';
$string['yourtextplaceholder'] = 'Type your response...';
$string['yourturn'] = 'Your turn';
