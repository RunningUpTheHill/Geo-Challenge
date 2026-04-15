const gameApp = window.GEO_CHALLENGE || {};
const gameAuth = window.GEO_PLAYER_AUTH.requireSession(gameApp.sessionCode, gameApp.urls.home);

if (!gameAuth) {
    throw new Error('Missing player session.');
}

let currentQuestion = null;
let currentPhase = 'ready';
let answered = false;
let score = 0;
let statusPollTimer = null;
let playerCount = 0;
let numQuestions = 10;
let lastAnswerSummary = null;
let timerInterval = null;
let activeTimerExpiresAt = 0;
let activeTimerDurationMs = 0;
let activeTimerKey = '';

const gameStatusUrl = window.GEO_PLAYER_AUTH.withPlayerToken(gameApp.statusUrl, gameAuth.playerToken);
const gameStreamUrl = window.GEO_PLAYER_AUTH.withPlayerToken(gameApp.streamUrl, gameAuth.playerToken);
const questionBox = document.getElementById('question-box');
const optionsGrid = document.getElementById('options-grid');
const feedbackOverlay = document.getElementById('feedback-overlay');
const feedbackMessage = document.getElementById('feedback-message');
const waitingNext = document.getElementById('waiting-next');
const waitingNextText = waitingNext.querySelector('span');
const timerFill = document.getElementById('timer-fill');
const timerText = document.getElementById('timer-text');
const miniScoreboard = document.getElementById('mini-scoreboard');
const miniScoreList = document.getElementById('mini-score-list');
const endGameButton = document.getElementById('end-game-btn');
const endGameModalElement = document.getElementById('end-game-modal');
const endGameModal = endGameModalElement ? new bootstrap.Modal(endGameModalElement) : null;
const questionCounter = document.getElementById('question-counter');
const categoryBadge = document.getElementById('category-badge');
const currentScoreElement = document.getElementById('current-score');

function gameEscapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatTime(ms) {
    if (!ms) {
        return '-';
    }

    return `${(ms / 1000).toFixed(1)}s`;
}

function formatAnswerLabel(value) {
    if (!value) {
        return 'No answer';
    }

    return gameEscapeHtml(value);
}

function isSameQuestion(data) {
    return currentQuestion
        && currentQuestion.id === data.id
        && currentQuestion.index === data.index;
}

function fetchGameStatus() {
    return $.getJSON(gameStatusUrl);
}

function handleGameAuthFailure(xhr) {
    return window.GEO_PLAYER_AUTH.handleAuthFailure(xhr, gameApp.sessionCode, gameApp.urls.home);
}

function refreshGameStatus() {
    return fetchGameStatus().done(applyGameState).fail((xhr) => {
        handleGameAuthFailure(xhr);
    });
}

function goToResults() {
    gameStream.close();
    window.clearInterval(statusPollTimer);
    stopTimer();
    window.location.href = gameApp.resultsUrl;
}

function syncViewerScore(players) {
    const viewer = (players || []).find((player) => Number(player.id) === Number(gameAuth.playerId) || player.is_you);
    if (viewer) {
        score = Number(viewer.score || 0);
        currentScoreElement.textContent = score;
    }
}

function renderMiniScoreboard(players) {
    if (!Array.isArray(players) || players.length === 0) {
        miniScoreboard.classList.add('hidden');
        miniScoreList.innerHTML = '';
        return;
    }

    playerCount = players.length;
    miniScoreboard.classList.remove('hidden');
    miniScoreList.innerHTML = players.map((player) => `
        <li class="list-group-item">
            <span class="ms-name">${gameEscapeHtml(player.name)}${player.is_host ? ' <small>(Host)</small>' : ''}</span>
            <span class="ms-score">${player.score} pts</span>
        </li>
    `).join('');

    syncViewerScore(players);
}

function stopTimer() {
    window.clearInterval(timerInterval);
    timerInterval = null;
    activeTimerExpiresAt = 0;
    activeTimerDurationMs = 0;
}

function updateTimerUi(remainingMs) {
    const safeRemaining = Math.max(0, remainingMs);
    const ratio = activeTimerDurationMs > 0
        ? Math.max(0, Math.min(1, safeRemaining / activeTimerDurationMs))
        : 0;

    timerFill.style.width = `${ratio * 100}%`;
    timerText.textContent = safeRemaining > 0 ? Math.ceil(safeRemaining / 1000) : '0';
}

function resetTimerDisplay(label = '--') {
    stopTimer();
    activeTimerKey = '';
    timerFill.style.width = '0%';
    timerText.textContent = label;
}

function startCountdown(durationSeconds, elapsedMs = 0, onExpire = null) {
    stopTimer();

    activeTimerDurationMs = Math.max(0, Number(durationSeconds || 0) * 1000);
    const initialElapsed = Math.max(0, Math.min(activeTimerDurationMs, Number(elapsedMs || 0)));
    const remainingMs = Math.max(0, activeTimerDurationMs - initialElapsed);
    activeTimerExpiresAt = Date.now() + remainingMs;

    updateTimerUi(remainingMs);
    if (remainingMs <= 0) {
        if (typeof onExpire === 'function') {
            onExpire();
        }
        return;
    }

    timerInterval = window.setInterval(() => {
        const nextRemaining = Math.max(0, activeTimerExpiresAt - Date.now());
        updateTimerUi(nextRemaining);

        if (nextRemaining <= 0) {
            stopTimer();
            if (typeof onExpire === 'function') {
                onExpire();
            }
        }
    }, 100);
}

function syncCountdown(timerKey, durationSeconds, elapsedMs = 0, onExpire = null) {
    const durationMs = Math.max(0, Number(durationSeconds || 0) * 1000);
    const initialElapsed = Math.max(0, Math.min(durationMs, Number(elapsedMs || 0)));
    const expectedRemainingMs = Math.max(0, durationMs - initialElapsed);
    const currentRemainingMs = activeTimerExpiresAt > 0
        ? Math.max(0, activeTimerExpiresAt - Date.now())
        : -1;
    const driftMs = currentRemainingMs >= 0 ? Math.abs(currentRemainingMs - expectedRemainingMs) : Number.POSITIVE_INFINITY;

    if (activeTimerKey !== timerKey || timerInterval === null || activeTimerDurationMs !== durationMs || driftMs > 750) {
        startCountdown(durationSeconds, elapsedMs, onExpire);
        activeTimerKey = timerKey;
    }
}

function renderReadyScreen(elapsedMs = 0) {
    currentPhase = 'ready';
    currentQuestion = null;
    answered = false;
    questionCounter.textContent = 'Get Ready';
    categoryBadge.textContent = 'Starting Soon';
    questionBox.innerHTML = `
        <div class="pregame-wait">
            <div class="spinner-border text-info mb-3" role="status" aria-hidden="true"></div>
            <p class="pregame-ready mb-2">Get ready!</p>
            <p class="mb-0 subtle-copy">The first question will appear in just a moment.</p>
        </div>
    `;
    optionsGrid.classList.add('hidden');
    optionsGrid.innerHTML = '';
    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');
    startCountdown(gameApp.ready_duration || 0, elapsedMs);
    activeTimerKey = `ready:${currentQuestion ? currentQuestion.index : 0}`;
}

function renderQuestion(data) {
    currentPhase = 'question';
    currentQuestion = data;
    answered = false;
    lastAnswerSummary = null;
    restoreStatusPolling();

    questionCounter.textContent = `Question ${data.index + 1} of ${numQuestions}`;
    categoryBadge.textContent = capitalize(data.category);

    questionBox.innerHTML = `<p class="question-text">${gameEscapeHtml(data.text)}</p>`;
    optionsGrid.innerHTML = data.options.map((option, index) => `
        <button class="option-btn" data-index="${index}">
            <span class="option-letter">${'ABCD'[index]}</span>
            <span class="option-text">${gameEscapeHtml(option)}</span>
        </button>
    `).join('');

    optionsGrid.classList.remove('hidden');
    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');

    optionsGrid.querySelectorAll('.option-btn').forEach((button) => {
        button.addEventListener('click', () => submitAnswer(parseInt(button.dataset.index, 10)));
    });

    startCountdown(data.time_limit, data.elapsed_ms || 0, () => {
        if (!answered) {
            submitAnswer(null, true);
        }
    });
    activeTimerKey = `question:${data.index}:${data.id}`;
}

function renderWaitingState(isTimeout = false) {
    optionsGrid.classList.add('hidden');
    questionBox.innerHTML = `
        <div class="pregame-wait">
            <div class="spinner-border text-info mb-3" role="status" aria-hidden="true"></div>
            <p class="pregame-ready mb-2">${isTimeout ? 'Time is up.' : 'Answer locked in.'}</p>
            <p class="mb-0 subtle-copy">Waiting for the rest of the players to finish this question.</p>
        </div>
    `;
    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');
}

function buildRoundSummaryMarkup() {
    if (!lastAnswerSummary) {
        return '<div class="round-summary">Everyone is in. Here are the latest standings before the next question.</div>';
    }

    const resultLabel = lastAnswerSummary.isTimeout
        ? 'Time expired.'
        : lastAnswerSummary.isCorrect
            ? 'Correct.'
            : 'Incorrect.';
    const pointsLine = lastAnswerSummary.pointsAwarded > 0
        ? ` You earned ${lastAnswerSummary.pointsAwarded} points in ${formatTime(lastAnswerSummary.timeMs)}.`
        : lastAnswerSummary.timeMs
            ? ` Response time: ${formatTime(lastAnswerSummary.timeMs)}.`
            : '';

    return `
        <div class="round-summary">
            <strong>${resultLabel}</strong>${pointsLine}
            <div class="mt-2">Your answer: <strong>${formatAnswerLabel(lastAnswerSummary.chosenText)}</strong></div>
            <div>Correct answer: <strong>${formatAnswerLabel(lastAnswerSummary.correctAnswerText)}</strong></div>
        </div>
    `;
}

function renderRoundLeaderboard(players, questionIndex, elapsedMs = 0) {
    currentPhase = 'leaderboard';
    currentQuestion = null;
    answered = true;

    questionCounter.textContent = `Round ${Number(questionIndex) + 1} Results`;
    categoryBadge.textContent = 'Leaderboard';
    optionsGrid.classList.add('hidden');
    optionsGrid.innerHTML = '';
    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');
    startCountdown(gameApp.leaderboard_duration || 0, elapsedMs);
    activeTimerKey = `leaderboard:${questionIndex}`;

    questionBox.innerHTML = `
        <div class="round-board">
            <h2 class="round-board-title">Round Leaderboard</h2>
            ${buildRoundSummaryMarkup()}
            <div class="leaderboard-list">
                ${players.map((player) => `
                    <div class="leaderboard-row ${player.is_you ? 'is-you' : ''}">
                        <span class="leaderboard-rank">#${player.rank}</span>
                        <span class="leaderboard-name">${gameEscapeHtml(player.name)}${player.is_host ? ' <small>(Host)</small>' : ''}${player.is_you ? ' <small>(You)</small>' : ''}</span>
                        <span class="leaderboard-points">${player.score} pts</span>
                    </div>
                `).join('')}
            </div>
            <p class="leaderboard-note mb-0">Next question begins once the shared leaderboard timer finishes.</p>
        </div>
    `;
}

function disableOptions() {
    optionsGrid.querySelectorAll('.option-btn').forEach((button) => {
        button.disabled = true;
    });
}

function speedUpStatusPolling() {
    window.clearInterval(statusPollTimer);
    statusPollTimer = window.setInterval(() => {
        refreshGameStatus();
    }, 300);
}

function restoreStatusPolling() {
    window.clearInterval(statusPollTimer);
    statusPollTimer = window.setInterval(() => {
        refreshGameStatus();
    }, 1000);
}

function applyGameState(data) {
    playerCount = typeof data.player_count === 'number' ? data.player_count : playerCount;
    if (typeof data.num_questions === 'number') {
        numQuestions = data.num_questions;
    }

    gameApp.ready_duration = data.ready_duration || gameApp.ready_duration;
    gameApp.leaderboard_duration = data.leaderboard_duration || gameApp.leaderboard_duration;

    renderMiniScoreboard(data.players);
    endGameButton.classList.toggle('hidden', !(data.viewer_is_host && data.status === 'in_progress'));

    if (data.status === 'finished') {
        goToResults();
        return;
    }

    if (data.round_phase === 'ready') {
        if (currentPhase !== 'ready' || timerInterval === null) {
            renderReadyScreen(data.phase_elapsed_ms || 0);
        } else {
            syncCountdown(activeTimerKey || 'ready:0', gameApp.ready_duration || 0, data.phase_elapsed_ms || 0);
        }
        return;
    }

    if (data.round_phase === 'leaderboard') {
        const leaderboardKey = `leaderboard:${data.current_q_index || 0}`;
        if (currentPhase !== 'leaderboard' || activeTimerKey !== leaderboardKey) {
            renderRoundLeaderboard(data.leaderboard || data.players || [], data.current_q_index || 0, data.phase_elapsed_ms || 0);
        } else {
            syncCountdown(leaderboardKey, gameApp.leaderboard_duration || 0, data.phase_elapsed_ms || 0);
        }
        speedUpStatusPolling();
        return;
    }

    if (data.round_phase === 'question' && data.current_question) {
        if (!isSameQuestion(data.current_question)) {
            renderQuestion(data.current_question);
        }
    }
}

function submitAnswer(chosenIndex, isTimeout = false) {
    if (answered || !currentQuestion) {
        return;
    }

    const questionOptions = Array.isArray(currentQuestion.options) ? currentQuestion.options.slice() : [];
    const questionId = currentQuestion.id;
    const chosenText = !isTimeout && questionOptions[chosenIndex] !== undefined
        ? questionOptions[chosenIndex]
        : null;

    answered = true;
    stopTimer();
    disableOptions();
    renderWaitingState(isTimeout);
    speedUpStatusPolling();

    $.ajax({
        url: gameApp.urls.answerSubmit,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            session_code: gameApp.sessionCode,
            player_token: gameAuth.playerToken,
            question_id: questionId,
            chosen_index: isTimeout ? null : chosenIndex,
        }),
    }).done((data) => {
        const correctAnswerText = questionOptions[data.correct_index] !== undefined
            ? questionOptions[data.correct_index]
            : null;

        lastAnswerSummary = {
            isCorrect: Boolean(data.is_correct),
            isTimeout,
            chosenText,
            correctAnswerText,
            pointsAwarded: Number(data.points_awarded || 0),
            timeMs: Number(data.time_ms || 0),
        };

        score += Number(data.points_awarded || 0);
        currentScoreElement.textContent = score;
    }).fail((xhr) => {
        if (handleGameAuthFailure(xhr)) {
            return;
        }

        lastAnswerSummary = {
            isCorrect: false,
            isTimeout,
            chosenText,
            correctAnswerText: null,
            pointsAwarded: 0,
            timeMs: 0,
        };
    });
}

const gameStream = new EventSource(gameStreamUrl);

gameStream.addEventListener('game_start', (event) => {
    const data = JSON.parse(event.data);
    renderMiniScoreboard(data.players || []);
    renderReadyScreen(data.phase_elapsed_ms || 0);
    speedUpStatusPolling();
});

gameStream.addEventListener('question', (event) => {
    const data = JSON.parse(event.data);
    renderMiniScoreboard(data.players || []);
    if (!isSameQuestion(data)) {
        renderQuestion(data);
    }
});

gameStream.addEventListener('round_leaderboard', (event) => {
    const data = JSON.parse(event.data);
    renderMiniScoreboard(data.leaderboard || data.players || []);
    renderRoundLeaderboard(
        data.leaderboard || data.players || [],
        data.question_index || 0,
        data.phase_elapsed_ms || 0
    );
});

gameStream.addEventListener('player_progress', (event) => {
    const data = JSON.parse(event.data);
    renderMiniScoreboard(data.players || []);
});

gameStream.addEventListener('game_end', () => {
    goToResults();
});

gameStream.onerror = () => {
    // EventSource reconnects automatically.
};

refreshGameStatus().done((data) => {
    if (data.status === 'in_progress') {
        speedUpStatusPolling();
    }
});

restoreStatusPolling();

$('#end-game-btn').on('click', () => {
    if (endGameModal) {
        endGameModal.show();
    }
});

$('#confirm-end-game').on('click', () => {
    $('#confirm-end-game').prop('disabled', true).text('Ending...');

    $.ajax({
        url: gameApp.urls.sessionEnd,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            session_code: gameApp.sessionCode,
            player_token: gameAuth.playerToken,
        }),
    }).fail((xhr) => {
        if (handleGameAuthFailure(xhr)) {
            return;
        }

        $('#confirm-end-game').prop('disabled', false).text('End Game');
    }).always(() => {
        if (endGameModal) {
            endGameModal.hide();
        }
    });
});

$('#end-game-modal').on('hidden.bs.modal', () => {
    $('#confirm-end-game').prop('disabled', false).text('End Game');
});
