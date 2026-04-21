const lobbyApp = window.GEO_CHALLENGE || {};
const lobbyAuth = window.GEO_PLAYER_AUTH.requireSession(lobbyApp.sessionCode, lobbyApp.urls.home);

if (!lobbyAuth) {
    throw new Error('Missing player session.');
}

const LOBBY_FALLBACK_POLL_MS = 1000;

let lobbyIsHost = false;
let lobbyNavigatedToGame = false;
let lobbyFallbackTimer = null;
let lobbySessionStatus = 'waiting';
let lobbyQuizMode = 'built_in';
let lobbyCustomQuestionCount = 0;
let lobbyCustomQuestions = [];
let lobbyBuilderLoaded = false;
let lobbyBuilderRequestPending = false;

const sessionCode = lobbyApp.sessionCode || '';
const playerId = Number(lobbyAuth.playerId || 0);
const statusUrl = window.GEO_PLAYER_AUTH.withPlayerToken(lobbyApp.statusUrl, lobbyAuth.playerToken);
const streamUrl = window.GEO_PLAYER_AUTH.withPlayerToken(lobbyApp.streamUrl, lobbyAuth.playerToken);
const customQuizStateUrl = window.GEO_PLAYER_AUTH.withPlayerToken(lobbyApp.customQuizStateUrl, lobbyAuth.playerToken);

const $hostControls = $('#host-controls');
const $waitingMsg = $('#waiting-msg');
const $statusMsg = $('#status-msg');
const $startBtn = $('#start-btn');
const $copyBtn = $('#copy-btn');
const $playerList = $('#player-list');
const $playerCount = $('#player-count');
const $customBuilder = $('#custom-builder');
const $customQuizList = $('#custom-quiz-list');
const $customQuizEmpty = $('#custom-quiz-empty');
const $customQuizSummary = $('#custom-quiz-summary');
const $quizModeHelp = $('#quiz-mode-help');
const $modeBuiltIn = $('#mode-built-in');
const $modeCustom = $('#mode-custom');
const $customQuestionForm = $('#custom-question-form');
const $customQuestionId = $('#custom-question-id');
const $customTopicLabel = $('#custom-topic-label');
const $customQuestionText = $('#custom-question-text');
const $customCorrectIndex = $('#custom-correct-index');
const $customImage = $('#custom-image');
const $removeImageWrap = $('#remove-image-wrap');
const $removeImage = $('#remove-image');
const $saveCustomQuestionBtn = $('#save-custom-question-btn');
const $cancelCustomEditBtn = $('#cancel-custom-edit-btn');

function lobbyEscapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function setLobbyStatus(message, type = 'warning') {
    $statusMsg
        .removeClass('d-none alert-warning alert-danger alert-info alert-success')
        .addClass(`alert-${type}`)
        .text(message || '');

    if (!message) {
        $statusMsg.addClass('d-none');
    }
}

function setHostState(nextIsHost) {
    lobbyIsHost = Boolean(nextIsHost);
    $hostControls.toggleClass('hidden', !lobbyIsHost);
    $waitingMsg.toggleClass('hidden', lobbyIsHost);
    if (!lobbyIsHost) {
        $customBuilder.addClass('hidden');
    }
}

function updateModeButtons() {
    $modeBuiltIn.toggleClass('active', lobbyQuizMode === 'built_in');
    $modeCustom.toggleClass('active', lobbyQuizMode === 'custom');
}

function updateCustomQuizSummary() {
    updateModeButtons();

    if (lobbyQuizMode === 'custom') {
        $quizModeHelp.text('This room will play only the questions you add below, in the order you save them.');
        if (lobbyCustomQuestionCount > 0) {
            $customQuizSummary.text(
                `${lobbyCustomQuestionCount} custom question${lobbyCustomQuestionCount === 1 ? '' : 's'} ready. Starting this room will use exactly this many questions.`
            );
        } else {
            $customQuizSummary.text('Add at least one custom question before starting custom mode.');
        }
    } else {
        $quizModeHelp.text('Use the built-in geography bank or switch this room to a host-made custom quiz.');
        $customQuizSummary.text('Built-in mode uses the question count chosen when the room was created.');
    }

    $customBuilder.toggleClass('hidden', !(lobbyIsHost && lobbySessionStatus === 'waiting' && lobbyQuizMode === 'custom'));
    refreshStartButton();
}

function refreshStartButton() {
    if (!lobbyIsHost) {
        return;
    }

    const isCustomBlocked = lobbyQuizMode === 'custom' && lobbyCustomQuestionCount <= 0;
    const baseText = lobbyQuizMode === 'custom' ? 'Start Custom Quiz' : 'Start Game';

    $startBtn.text(baseText);
    $startBtn.prop('disabled', isCustomBlocked);
}

function resetCustomQuestionForm() {
    $customQuestionForm.trigger('reset');
    $customQuestionId.val('');
    $removeImageWrap.addClass('hidden');
    $removeImage.prop('checked', false);
    $saveCustomQuestionBtn.text('Save Question').prop('disabled', false);
    $cancelCustomEditBtn.addClass('hidden');
}

function populateCustomQuestionForm(question) {
    $customQuestionId.val(question.id);
    $customTopicLabel.val(question.topic_label || '');
    $customQuestionText.val(question.question_text || '');
    (question.options || []).forEach((option, index) => {
        $(`#custom-option-${index}`).val(option || '');
    });
    $customCorrectIndex.val(String(Number(question.correct_index || 0)));
    $customImage.val('');
    $removeImage.prop('checked', false);
    $removeImageWrap.toggleClass('hidden', !question.has_image);
    $saveCustomQuestionBtn.text('Update Question').prop('disabled', false);
    $cancelCustomEditBtn.removeClass('hidden');

    const formTop = $customQuestionForm.offset();
    if (formTop && typeof formTop.top === 'number') {
        window.scrollTo({ top: Math.max(formTop.top - 80, 0), behavior: 'smooth' });
    }
}

function renderPlayers(players) {
    $playerCount.text(players.length);

    const markup = players.map((player) => {
        const isYou = Number(player.id) === playerId;
        return `
            <li class="list-group-item player-item">
                <span class="player-avatar">${lobbyEscapeHtml(player.name.charAt(0).toUpperCase())}</span>
                <span class="player-name">${lobbyEscapeHtml(player.name)}</span>
                ${player.is_host ? '<span class="you-badge host-badge">Host</span>' : ''}
                ${isYou ? '<span class="you-badge">You</span>' : ''}
            </li>
        `;
    }).join('');

    $playerList.html(markup);
}

function renderCustomQuestions(questions) {
    if (!Array.isArray(questions) || questions.length === 0) {
        $customQuizList.empty();
        $customQuizEmpty.removeClass('hidden');
        return;
    }

    $customQuizEmpty.addClass('hidden');
    const markup = questions.map((question, index) => {
        const options = Array.isArray(question.options) ? question.options : [];
        const answersMarkup = options.map((option, optionIndex) => `
            <li class="${optionIndex === Number(question.correct_index) ? 'correct' : ''}">
                ${lobbyEscapeHtml(option)}
                ${optionIndex === Number(question.correct_index) ? ' <strong>(Correct)</strong>' : ''}
            </li>
        `).join('');

        return `
            <article class="custom-quiz-card" data-question-id="${Number(question.id)}">
                <div class="custom-quiz-card-head">
                    <div class="d-flex gap-3 flex-grow-1">
                        <span class="custom-quiz-order">${index + 1}</span>
                        <div class="flex-grow-1">
                            <div class="custom-quiz-meta">
                                <span class="custom-quiz-topic">${lobbyEscapeHtml(question.topic_label || 'Custom Quiz')}</span>
                                ${question.has_image ? '<span class="custom-quiz-image-chip">Image attached</span>' : ''}
                            </div>
                            <div class="custom-quiz-text">${lobbyEscapeHtml(question.question_text || '')}</div>
                            <ol class="custom-quiz-answers">
                                ${answersMarkup}
                            </ol>
                        </div>
                    </div>
                    <div class="custom-quiz-actions">
                        <button type="button" class="btn btn-outline-light" data-action="up">Up</button>
                        <button type="button" class="btn btn-outline-light" data-action="down">Down</button>
                        <button type="button" class="btn btn-outline-light" data-action="edit">Edit</button>
                        <button type="button" class="btn btn-outline-light" data-action="delete">Delete</button>
                    </div>
                </div>
            </article>
        `;
    }).join('');

    $customQuizList.html(markup);
}

function applyBuilderState(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    lobbyBuilderLoaded = true;
    lobbyQuizMode = data.quiz_mode || lobbyQuizMode;
    lobbyCustomQuestions = Array.isArray(data.custom_questions) ? data.custom_questions.slice() : [];
    lobbyCustomQuestionCount = Number(data.custom_question_count || lobbyCustomQuestions.length || 0);

    renderCustomQuestions(lobbyCustomQuestions);
    updateCustomQuizSummary();
}

function fetchLobbyStatus() {
    return $.ajax({
        url: statusUrl,
        method: 'GET',
        dataType: 'json',
        cache: false,
        headers: {
            'Cache-Control': 'no-cache',
            Pragma: 'no-cache',
        },
    });
}

function fetchCustomQuizState() {
    if (!lobbyIsHost || lobbyBuilderRequestPending) {
        return $.Deferred().resolve().promise();
    }

    lobbyBuilderRequestPending = true;
    return $.ajax({
        url: customQuizStateUrl,
        method: 'GET',
        dataType: 'json',
        cache: false,
        headers: {
            'Cache-Control': 'no-cache',
            Pragma: 'no-cache',
        },
    }).done((data) => {
        applyBuilderState(data);
    }).always(() => {
        lobbyBuilderRequestPending = false;
    });
}

function handleLobbyAuthFailure(xhr) {
    return window.GEO_PLAYER_AUTH.handleAuthFailure(xhr, sessionCode, lobbyApp.urls.home);
}

function goToGame() {
    if (lobbyNavigatedToGame) {
        return;
    }

    lobbyNavigatedToGame = true;
    window.clearInterval(lobbyFallbackTimer);
    lobbyStream.close();
    window.location.href = lobbyApp.gameUrl;
}

function maybeRefreshBuilderFromLobbyState(data) {
    const nextMode = data && data.quiz_mode ? data.quiz_mode : 'built_in';
    const nextCount = Number(data && data.custom_question_count ? data.custom_question_count : 0);
    const modeChanged = nextMode !== lobbyQuizMode;
    const countChanged = nextCount !== lobbyCustomQuestionCount;

    lobbyQuizMode = nextMode;
    lobbyCustomQuestionCount = nextCount;
    updateCustomQuizSummary();

    if (lobbyIsHost && lobbySessionStatus === 'waiting' && (!lobbyBuilderLoaded || modeChanged || countChanged)) {
        fetchCustomQuizState().fail((xhr) => {
            handleLobbyAuthFailure(xhr);
        });
    }
}

function applyLobbyState(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    lobbySessionStatus = data.status || 'waiting';
    setHostState(data.viewer_is_host);
    renderPlayers(data.players || []);
    maybeRefreshBuilderFromLobbyState(data);
    setLobbyStatus('');
}

function startLobbyFallbackPolling() {
    window.clearInterval(lobbyFallbackTimer);
    lobbyFallbackTimer = window.setInterval(() => {
        if (lobbyNavigatedToGame) {
            return;
        }

        fetchLobbyStatus().done((data) => {
            if (data.status === 'in_progress') {
                goToGame();
            } else if (data.status === 'finished') {
                window.clearInterval(lobbyFallbackTimer);
                window.location.href = lobbyApp.resultsUrl;
            } else {
                applyLobbyState(data);
            }
        }).fail((xhr) => {
            handleLobbyAuthFailure(xhr);
        });
    }, LOBBY_FALLBACK_POLL_MS);
}

function postQuizMode(nextMode) {
    $modeBuiltIn.prop('disabled', true);
    $modeCustom.prop('disabled', true);

    $.ajax({
        url: lobbyApp.quizModeUrl,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            session_code: sessionCode,
            player_token: lobbyAuth.playerToken,
            quiz_mode: nextMode,
        }),
    }).done((data) => {
        applyBuilderState(data);
        setLobbyStatus(nextMode === 'custom' ? 'Custom quiz mode is active for this room.' : 'Built-in geography mode is active for this room.', 'success');
    }).fail((xhr) => {
        if (handleLobbyAuthFailure(xhr)) {
            return;
        }

        const message = xhr.responseJSON && xhr.responseJSON.error
            ? xhr.responseJSON.error
            : 'Could not change the quiz mode.';
        setLobbyStatus(message, 'danger');
    }).always(() => {
        $modeBuiltIn.prop('disabled', false);
        $modeCustom.prop('disabled', false);
    });
}

function moveCustomQuestion(questionId, direction) {
    $.ajax({
        url: lobbyApp.customQuestionMoveUrl,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            session_code: sessionCode,
            player_token: lobbyAuth.playerToken,
            question_id: Number(questionId),
            direction,
        }),
    }).done((data) => {
        applyBuilderState(data);
    }).fail((xhr) => {
        if (handleLobbyAuthFailure(xhr)) {
            return;
        }

        const message = xhr.responseJSON && xhr.responseJSON.error
            ? xhr.responseJSON.error
            : 'Could not reorder that custom question.';
        setLobbyStatus(message, 'danger');
    });
}

function deleteCustomQuestion(questionId) {
    $.ajax({
        url: lobbyApp.customQuestionDeleteUrl,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            session_code: sessionCode,
            player_token: lobbyAuth.playerToken,
            question_id: Number(questionId),
        }),
    }).done((data) => {
        applyBuilderState(data);
        resetCustomQuestionForm();
        setLobbyStatus('Custom question removed.', 'success');
    }).fail((xhr) => {
        if (handleLobbyAuthFailure(xhr)) {
            return;
        }

        const message = xhr.responseJSON && xhr.responseJSON.error
            ? xhr.responseJSON.error
            : 'Could not delete that custom question.';
        setLobbyStatus(message, 'danger');
    });
}

const lobbyStream = new EventSource(streamUrl);

fetchLobbyStatus().done((data) => {
    if (data.status === 'in_progress') {
        goToGame();
    } else if (data.status === 'finished') {
        lobbyStream.close();
        window.location.href = lobbyApp.resultsUrl;
    } else {
        applyLobbyState(data);
        if (data.viewer_is_host) {
            fetchCustomQuizState().fail((xhr) => {
                if (handleLobbyAuthFailure(xhr)) {
                    return;
                }
                setLobbyStatus('Could not load the custom quiz builder.', 'danger');
            });
        }
    }
}).fail((xhr) => {
    if (handleLobbyAuthFailure(xhr)) {
        return;
    }

    setLobbyStatus('Could not load the latest session state.', 'danger');
});

lobbyStream.addEventListener('lobby_update', (event) => {
    const data = JSON.parse(event.data);
    applyLobbyState(data);
});

lobbyStream.addEventListener('game_start', () => {
    goToGame();
});

lobbyStream.onerror = () => {
    if (!lobbyNavigatedToGame) {
        setLobbyStatus('Connection issue. Reconnecting...', 'warning');
    }
};

$copyBtn.on('click', () => {
    navigator.clipboard.writeText(sessionCode).then(() => {
        const originalText = $copyBtn.text();
        $copyBtn.text('Copied!');
        window.setTimeout(() => {
            $copyBtn.text(originalText);
        }, 1800);
    });
});

$modeBuiltIn.on('click', () => {
    if (lobbyQuizMode !== 'built_in') {
        postQuizMode('built_in');
    }
});

$modeCustom.on('click', () => {
    if (lobbyQuizMode !== 'custom') {
        postQuizMode('custom');
    }
});

$startBtn.on('click', () => {
    $startBtn.prop('disabled', true).text('Starting...');

    $.ajax({
        url: lobbyApp.urls.sessionStart,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            session_code: sessionCode,
            player_token: lobbyAuth.playerToken,
        }),
    }).done(() => {
        setLobbyStatus('Starting game...', 'info');
        goToGame();
    }).fail((xhr) => {
        if (handleLobbyAuthFailure(xhr)) {
            return;
        }

        const message = xhr.responseJSON && xhr.responseJSON.error
            ? xhr.responseJSON.error
            : 'Could not start game.';
        setLobbyStatus(message, 'danger');
        refreshStartButton();
    });
});

$customQuestionForm.on('submit', (event) => {
    event.preventDefault();

    const formData = new FormData($customQuestionForm[0]);
    formData.append('session_code', sessionCode);
    formData.append('player_token', lobbyAuth.playerToken);

    $saveCustomQuestionBtn.prop('disabled', true).text($customQuestionId.val() ? 'Updating...' : 'Saving...');

    $.ajax({
        url: lobbyApp.customQuestionSaveUrl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
    }).done((data) => {
        applyBuilderState(data);
        resetCustomQuestionForm();
        setLobbyStatus('Custom question saved to this room.', 'success');
    }).fail((xhr) => {
        if (handleLobbyAuthFailure(xhr)) {
            return;
        }

        const message = xhr.responseJSON && xhr.responseJSON.error
            ? xhr.responseJSON.error
            : 'Could not save that custom question.';
        setLobbyStatus(message, 'danger');
        $saveCustomQuestionBtn.prop('disabled', false).text($customQuestionId.val() ? 'Update Question' : 'Save Question');
    });
});

$cancelCustomEditBtn.on('click', () => {
    resetCustomQuestionForm();
});

$customQuizList.on('click', '[data-action]', (event) => {
    const $button = $(event.currentTarget);
    const action = $button.data('action');
    const $card = $button.closest('[data-question-id]');
    const questionId = Number($card.data('question-id') || 0);
    const question = lobbyCustomQuestions.find((entry) => Number(entry.id) === questionId);

    if (!questionId || !question) {
        return;
    }

    if (action === 'edit') {
        populateCustomQuestionForm(question);
        return;
    }

    if (action === 'delete') {
        deleteCustomQuestion(questionId);
        return;
    }

    if (action === 'up' || action === 'down') {
        moveCustomQuestion(questionId, action);
    }
});

resetCustomQuestionForm();
startLobbyFallbackPolling();
