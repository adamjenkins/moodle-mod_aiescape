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
 * AMD module: AI Escape Room game controller.
 *
 * @module     mod_aiescape/game
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/templates', 'core/str'],
function(Ajax, Notification, Templates, Str) {

    /** @type {number} Course module id */
    var cmid = 0;
    /** @type {number} Current attempt id */
    var attemptId = 0;
    /** @type {string} Game mode */
    var gamemode = 'multichoice';
    /** @type {number} Steps needed */
    var steps = 10;
    /** @type {boolean} Show progress bar */
    var showProgress = false;
    /** @type {boolean} Whether input is currently disabled */
    var busy = false;
    /** @type {boolean} Whether to reveal each choice's good/neutral/bad type on hover (preview only) */
    var choiceHints = false;

    /**
     * Wrapper around core/str get_string.
     *
     * @param {string} key
     * @param {string} component
     * @param {*} data
     * @returns {Promise<string>}
     */
    var getString = function(key, component, data) {
        return Str.get_string(key, component, data);
    };

    /**
     * Calls start_attempt and bootstraps the UI with any existing history.
     *
     * Loading state is managed per-path so the spinner stays visible during
     * any subsequent sendMessage call (e.g. fetching the opening narrative).
     */
    var startAttempt = function() {
        setLoading(true);
        callService('mod_aiescape_start_attempt', {cmid: cmid})
        .then(function(result) {
            attemptId    = result.attemptid;
            gamemode     = result.gamemode;
            steps        = result.steps;
            showProgress = result.showprogress;

            var renderAll = Promise.resolve();
            result.messages.forEach(function(msg) {
                renderAll = renderAll.then(function() {
                    return renderMessage(msg.role, msg.message);
                });
            });

            return renderAll.then(function() {
                renderButtons(result.buttons);

                if (result.completed) {
                    hideQuitButton();
                    setLoading(false);
                    return showCompletion(result.canrestart !== false);
                }

                if (result.messages.length === 0) {
                    busy = false;
                    // sendMessage owns loading from here — don't call setLoading(false).
                    return sendMessage('', '', '');
                }

                busy = false;
                var lastMsg = result.messages[result.messages.length - 1];
                if (lastMsg && lastMsg.role === 'assistant' &&
                        (gamemode === 'multichoice' || gamemode === 'combo')) {
                    // sendMessage owns loading from here.
                    return sendMessage('', '', '');
                }

                enableInput();
                updateProgress(result.tally, steps);
                setLoading(false);
                return null;
            });
        })
        .catch(function(e) {
            Notification.exception(e);
            setLoading(false);
        });
    };

    /**
     * Sends a student message or choice to the AI.
     *
     * Returns a Promise so callers (e.g. startAttempt) can properly chain and
     * wait before they clean up their own loading state.
     *
     * @param {string} message     Free-text message (may be empty)
     * @param {string} choicetype  'good', 'neutral', 'bad', or ''
     * @param {string} choicelabel The label of the selected choice
     * @returns {Promise}
     */
    var sendMessage = function(message, choicetype, choicelabel) {
        if (busy) {
            return Promise.resolve();
        }
        setLoading(true);
        disableInput();

        var renderUser = Promise.resolve();
        if (message.trim() || choicelabel) {
            renderUser = renderMessage('user', choicelabel || message);
        }

        return renderUser
        .then(function() {
            return callService('mod_aiescape_send_message', {
                cmid: cmid,
                attemptid: attemptId,
                message: message.trim(),
                choicetype: choicetype,
                choicelabel: choicelabel,
            });
        })
        .then(function(result) {
            return renderMessage('assistant', result.narrative)
            .then(function() {
                updateProgress(result.tally, result.steps);

                if (result.completed) {
                    hideQuitButton();
                    return showCompletion(result.canrestart !== false);
                }

                if ((gamemode === 'multichoice' || gamemode === 'combo') && result.choices.length) {
                    renderChoices(result.choices);
                }
                enableInput();
                return null;
            });
        })
        .catch(function(e) {
            Notification.exception(e);
            enableInput();
        })
        .then(function() {
            setLoading(false);
        });
    };

    /**
     * Fires a secondary button prompt. The button's instruction is persisted
     * server-side so it affects this and all subsequent AI turns; this issues
     * exactly one AI call and renders its narrative (and any new choices).
     *
     * Buttons that have reached their usage limit are disabled client-side
     * (see renderButtons/this function's success handler) so this should not
     * normally be reachable once exhausted; the server still rejects it
     * defensively, but that failure must never leave the story's own choice
     * buttons disabled.
     *
     * @param {number} buttonId
     */
    var triggerButton = function(buttonId) {
        if (busy) {
            return;
        }
        var triggeredBtn = document.querySelector('#aiescape-buttons button[data-buttonid="' + buttonId + '"]');
        setLoading(true);
        disableInput();

        callService('mod_aiescape_trigger_button', {
            cmid: cmid,
            attemptid: attemptId,
            buttonid: buttonId,
        })
        .then(function(result) {
            if (triggeredBtn) {
                triggeredBtn.dataset.remaining = (result.remaining === -1) ? '' : String(result.remaining);
            }
            return renderMessage('assistant', result.narrative)
            .then(function() {
                if ((gamemode === 'multichoice' || gamemode === 'combo') && result.choices.length) {
                    renderChoices(result.choices);
                }
                setLoading(false);
                enableInput();
                return null;
            });
        })
        .catch(function(e) {
            Notification.exception(e);
            setLoading(false);
            enableInput();
        });
    };

    /**
     * Renders a message bubble in the chat log.
     *
     * @param {string} role    'user' or 'assistant'
     * @param {string} message Message text
     * @returns {Promise}
     */
    var renderMessage = function(role, message) {
        var context = {role: role, message: message, isuser: role === 'user'};
        return Templates.render('mod_aiescape/message', context)
        .then(function(html) {
            var chatlog = document.getElementById('aiescape-chatlog');
            chatlog.insertAdjacentHTML('beforeend', html);
            chatlog.scrollTop = chatlog.scrollHeight;
        });
    };

    /**
     * Renders (or replaces) the three primary choice buttons in random order.
     *
     * @param {Array<{label: string, type: string}>} choices
     */
    var renderChoices = function(choices) {
        var container = document.getElementById('aiescape-choices');
        container.innerHTML = '';
        container.classList.remove('d-none');

        // Fisher-Yates shuffle (non-destructive copy).
        var shuffled = choices.slice();
        for (var i = shuffled.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = shuffled[i]; shuffled[i] = shuffled[j]; shuffled[j] = tmp;
        }

        shuffled.forEach(function(choice) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-primary';
            if (choiceHints) {
                btn.classList.add('aiescape-choice-hint-' + choice.type);
                getString('choicehint_' + choice.type, 'mod_aiescape').then(function(label) {
                    btn.title = label;
                    return label;
                }).catch(Notification.exception);
            }
            btn.textContent = choice.label;
            btn.addEventListener('click', function() {
                container.innerHTML = '';
                container.classList.add('d-none');
                sendMessage('', choice.type, choice.label);
            });
            container.appendChild(btn);
        });
    };

    /**
     * Renders the secondary action buttons.
     *
     * @param {Array<{id: number, label: string, remaining: number}>} buttons
     *        remaining is -1 for unlimited, or the number of uses left.
     */
    var renderButtons = function(buttons) {
        var container = document.getElementById('aiescape-buttons');
        container.innerHTML = '';

        buttons.forEach(function(button) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary btn-sm';
            btn.textContent = button.label;
            btn.dataset.buttonid = button.id;
            btn.dataset.remaining = (button.remaining === -1) ? '' : String(button.remaining);
            btn.disabled = (button.remaining === 0);
            btn.addEventListener('click', function() {
                triggerButton(button.id);
            });
            container.appendChild(btn);
        });
    };

    /**
     * Updates the progress bar if it is enabled.
     *
     * @param {number} tally
     * @param {number} total
     */
    var updateProgress = function(tally, total) {
        if (!showProgress) {
            return;
        }
        var bar   = document.getElementById('aiescape-progress-bar');
        var label = document.getElementById('aiescape-progress-label');
        if (!bar) {
            return;
        }
        var pct = total > 0 ? Math.min(100, Math.round((tally / total) * 100)) : 0;
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', tally);

        getString('progresslabel', 'mod_aiescape', {tally: tally, steps: total})
        .then(function(str) {
            if (label) {
                label.textContent = str;
            }
            return str;
        })
        .catch(Notification.exception);
    };

    /**
     * Shows the completion banner and disables all input.
     *
     * @param {boolean} canRestart Whether the user has remaining attempts
     * @returns {Promise}
     */
    var showCompletion = function(canRestart) {
        if (canRestart === undefined) {
            canRestart = true;
        }
        disableInput();
        hideQuitButton();
        var choiceContainer = document.getElementById('aiescape-choices');
        if (choiceContainer) {
            choiceContainer.innerHTML = '';
            choiceContainer.classList.add('d-none');
        }

        return Promise.all([
            getString('attemptcompleted', 'mod_aiescape'),
            getString('attemptcompletedmessage', 'mod_aiescape'),
            getString('newattempt', 'mod_aiescape'),
        ])
        .then(function(strings) {
            var title          = strings[0];
            var message        = strings[1];
            var newattemptlabel = strings[2];
            var newattempturl  = canRestart
                ? new URL(window.location.href).pathname + '?id=' + cmid
                : '';
            return Templates.render('mod_aiescape/completion', {
                title: title,
                message: message,
                newattempturl: newattempturl,
                newattemptlabel: newattemptlabel,
            });
        })
        .then(function(html) {
            var completionDiv = document.getElementById('aiescape-completion');
            completionDiv.innerHTML = html;
            completionDiv.classList.remove('d-none');
            completionDiv.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        });
    };

    /**
     * Toggles the game container in and out of fullscreen mode.
     */
    var toggleFullscreen = function() {
        var game = document.getElementById('aiescape-game');
        if (!document.fullscreenElement) {
            game.requestFullscreen().catch(function() {});
        } else {
            document.exitFullscreen();
        }
    };

    /**
     * Updates the fullscreen button icons, aria-label, and title to reflect current state.
     */
    var updateFullscreenButton = function() {
        var btn = document.getElementById('aiescape-fullscreen-btn');
        if (!btn) {
            return;
        }
        var isFs = !!document.fullscreenElement;
        var label = isFs ? btn.dataset.exitlabel : btn.dataset.enterlabel;
        btn.setAttribute('aria-label', label);
        btn.setAttribute('title', label);
        var enterIcon = btn.querySelector('.aiescape-icon-enter');
        var exitIcon  = btn.querySelector('.aiescape-icon-exit');
        if (enterIcon) {
            enterIcon.classList.toggle('d-none', isFs);
        }
        if (exitIcon) {
            exitIcon.classList.toggle('d-none', !isFs);
        }
    };

    /** Hides the quit button (called after completion or abandonment). */
    var hideQuitButton = function() {
        var btn = document.getElementById('aiescape-quit-btn');
        if (btn) {
            btn.closest('.text-end').classList.add('d-none');
        }
    };

    /**
     * Abandons the current attempt after user confirmation.
     */
    var quitAttempt = function() {
        if (busy) {
            return;
        }
        var btn = document.getElementById('aiescape-quit-btn');
        var confirmMsgPromise = (btn && btn.dataset.confirm)
            ? Promise.resolve(btn.dataset.confirm)
            : getString('quitattempt_confirm', 'mod_aiescape');

        confirmMsgPromise.then(function(confirmMsg) {
            Notification.confirm(
                '',
                confirmMsg,
                '',
                '',
                doQuitAttempt
            );
        }).catch(Notification.exception);
    };

    /**
     * Performs the actual quit-attempt service call once the user has confirmed.
     */
    var doQuitAttempt = function() {
        setLoading(true);
        disableInput();
        hideQuitButton();

        callService('mod_aiescape_quit_attempt', {cmid: cmid, attemptid: attemptId})
        .then(function(result) {
            return Str.get_string('attemptabandoned', 'mod_aiescape')
            .then(function(str) {
                var completionDiv = document.getElementById('aiescape-completion');
                completionDiv.innerHTML = '';

                var alert = document.createElement('div');
                alert.className = 'alert alert-warning';
                alert.textContent = str;
                completionDiv.appendChild(alert);

                if (result.canrestart) {
                    return Str.get_string('newattempt', 'mod_aiescape').then(function(label) {
                        var link = document.createElement('a');
                        link.href = new URL(window.location.href).pathname + '?id=' + cmid;
                        link.className = 'btn btn-primary mt-2';
                        link.textContent = label;
                        completionDiv.appendChild(link);
                    });
                }
                return null;
            });
        })
        .then(function() {
            var completionDiv = document.getElementById('aiescape-completion');
            completionDiv.classList.remove('d-none');
            completionDiv.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        })
        .catch(Notification.exception)
        .then(function() {
            setLoading(false);
        });
    };

    /**
     * Shows the loading spinner and marks the UI as busy.
     * @param {boolean} state True to show loading, false to hide.
     */
    var setLoading = function(state) {
        busy = state;
        var el = document.getElementById('aiescape-loading');
        if (el) {
            el.classList.toggle('d-none', !state);
        }
    };

    /** Disables all interactive inputs. */
    var disableInput = function() {
        var freetext = document.getElementById('aiescape-freetext');
        var sendbtn  = document.getElementById('aiescape-send-btn');
        if (freetext) {
            freetext.disabled = true;
        }
        if (sendbtn) {
            sendbtn.disabled = true;
        }
        document.querySelectorAll('#aiescape-choices button, #aiescape-buttons button').forEach(function(btn) {
            btn.disabled = true;
        });
    };

    /** Re-enables interactive inputs and wires the send button. */
    var enableInput = function() {
        var freetext = document.getElementById('aiescape-freetext');
        var sendbtn  = document.getElementById('aiescape-send-btn');

        if (freetext) {
            freetext.disabled = false;
            freetext.focus();
            if (sendbtn && !sendbtn.dataset.wired) {
                sendbtn.dataset.wired = '1';
                sendbtn.disabled = false;
                sendbtn.addEventListener('click', function() {
                    var text = freetext.value.trim();
                    if (!text) {
                        return;
                    }
                    freetext.value = '';
                    sendMessage(text, '', '');
                });
                freetext.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendbtn.click();
                    }
                });
            } else if (sendbtn) {
                sendbtn.disabled = false;
            }
        }

        // Choice buttons are always re-enabled; additional buttons stay disabled once exhausted.
        document.querySelectorAll('#aiescape-choices button').forEach(function(btn) {
            btn.disabled = false;
        });
        document.querySelectorAll('#aiescape-buttons button').forEach(function(btn) {
            btn.disabled = (btn.dataset.remaining === '0');
        });
    };

    /**
     * Calls a Moodle web service and returns the result.
     *
     * @param {string} methodname
     * @param {Object} args
     * @returns {Promise<Object>}
     */
    var callService = function(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    };

    return {
        /**
         * Initialises the game module.
         *
         * @param {number} cmidParam Course module id
         * @param {boolean} showChoiceHints Whether to reveal choice types on hover (preview only)
         */
        init: function(cmidParam, showChoiceHints) {
            cmid = cmidParam;
            choiceHints = !!showChoiceHints;

            var startBtn = document.getElementById('aiescape-start-btn');
            if (startBtn) {
                startBtn.addEventListener('click', function() {
                    document.getElementById('aiescape-start-screen').classList.add('d-none');
                    document.getElementById('aiescape-game').classList.remove('d-none');
                    startAttempt();
                });
            } else {
                startAttempt();
            }

            var quitBtn = document.getElementById('aiescape-quit-btn');
            if (quitBtn) {
                quitBtn.addEventListener('click', quitAttempt);
            }

            var fsBtn = document.getElementById('aiescape-fullscreen-btn');
            if (fsBtn) {
                if (!document.fullscreenEnabled) {
                    fsBtn.classList.add('d-none');
                } else {
                    fsBtn.addEventListener('click', toggleFullscreen);
                    document.addEventListener('fullscreenchange', updateFullscreenButton);
                }
            }
        },
    };
});
