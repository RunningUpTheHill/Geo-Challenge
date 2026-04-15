const gameApp = window.GEO_CHALLENGE || {};
const gameAuth = window.GEO_PLAYER_AUTH.requireSession(gameApp.sessionCode, gameApp.urls.home);

if (!gameAuth) {
    throw new Error('Missing player session.');
}

const GAME_STATUS_POLL_MS = 3000;
const GAME_FAST_STATUS_POLL_MS = 250;

let currentQuestion = null;
let currentPhase = 'ready';
let answered = false;
let score = 0;
let statusPollTimer = null;
let playerCount = 0;
let numQuestions = 10;
let lastAnswerSummary = null;
let answerSubmitPending = false;
let timerInterval = null;
let activeTimerStartsAt = 0;
let activeTimerExpiresAt = 0;
let activeTimerDurationMs = 0;
let activeTimerKey = '';
let activeRoundSummaryKey = '';
let latestStateVersion = -1;
let initialReadyAckPending = false;
let viewerReadyConfirmed = false;
let readyScreenRefs = null;

const gameReadyUrl = window.GEO_PLAYER_AUTH.withPlayerToken(gameApp.gameReadyUrl, gameAuth.playerToken);
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

function buildPulseLoaderMarkup() {
    return `
        <div class="sync-loader" aria-hidden="true">
            <span class="sync-loader-ring sync-loader-ring-a"></span>
            <span class="sync-loader-ring sync-loader-ring-b"></span>
            <span class="sync-loader-core"></span>
        </div>
    `;
}

function normalizeRoundResult(result) {
    if (!result || typeof result !== 'object') {
        return null;
    }

    return {
        questionIndex: Number(result.question_index || 0),
        chosenIndex: result.chosen_index === null || result.chosen_index === undefined
            ? null
            : Number(result.chosen_index),
        chosenText: result.chosen_text || null,
        correctIndex: result.correct_index === null || result.correct_index === undefined
            ? null
            : Number(result.correct_index),
        correctAnswerText: result.correct_text || null,
        isCorrect: Boolean(result.is_correct),
        isTimeout: Boolean(result.is_timeout),
        pointsAwarded: Number(result.points_awarded || 0),
        timeMs: Number(result.time_ms || 0),
    };
}

function buildRoundSummaryKey(summary, questionIndex) {
    if (!summary) {
        return `round-summary:${Number(questionIndex)}:missing`;
    }

    return [
        'round-summary',
        Number(questionIndex),
        summary.chosenIndex === null ? 'na' : summary.chosenIndex,
        summary.correctIndex === null ? 'na' : summary.correctIndex,
        summary.isCorrect ? 1 : 0,
        summary.isTimeout ? 1 : 0,
        summary.pointsAwarded,
        summary.timeMs,
        summary.chosenText || '',
        summary.correctAnswerText || '',
    ].join(':');
}

function isMissingRoundResult(summary) {
    return Boolean(summary)
        && summary.chosenIndex === null
        && !summary.isTimeout
        && !summary.isCorrect
        && summary.pointsAwarded === 0
        && summary.timeMs === 0;
}

function isSameQuestion(data) {
    return currentQuestion
        && currentQuestion.id === data.id
        && currentQuestion.index === data.index;
}

function normalizeQuestionRecord(question, fallbackIndex = null) {
    if (!question || typeof question !== 'object') {
        return null;
    }

    return {
        index: Number(question.index ?? fallbackIndex ?? 0),
        id: Number(question.id || 0),
        text: String(question.text || ''),
        category: String(question.category || ''),
        options: Array.isArray(question.options) ? question.options.slice() : [],
        time_limit: Number(question.time_limit || gameApp.question_duration || 0),
        elapsed_ms: Number(question.elapsed_ms || 0),
    };
}

function resolveQuestionData(question, fallbackIndex = null) {
    return normalizeQuestionRecord(question, fallbackIndex);
}

function ackGameReady() {
    return $.ajax({
        url: gameReadyUrl,
        method: 'POST',
        dataType: 'json',
        timeout: 4000,
        cache: false,
        headers: {
            'Cache-Control': 'no-cache',
            Pragma: 'no-cache',
        },
    });
}

function fetchGameStatus() {
    return $.ajax({
        url: gameStatusUrl,
        method: 'GET',
        dataType: 'json',
        cache: false,
        headers: {
            'Cache-Control': 'no-cache',
            Pragma: 'no-cache',
        },
    });
}

function getStateVersion(data) {
    const version = Number(data && data.state_version);
    return Number.isFinite(version) ? version : -1;
}

function acceptIncomingState(data) {
    const incomingStateVersion = getStateVersion(data);

    if (incomingStateVersion < 0) {
        return true;
    }

    if (latestStateVersion >= 0 && incomingStateVersion < latestStateVersion) {
        return false;
    }

    latestStateVersion = incomingStateVersion;
    return true;
}

function syncDurations(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    gameApp.question_duration = data.question_duration || gameApp.question_duration;
    gameApp.ready_duration = data.ready_duration || gameApp.ready_duration;
    gameApp.leaderboard_duration = data.leaderboard_duration || gameApp.leaderboard_duration;
}

function shouldAckInitialReady(data) {
    return Boolean(data)
        && data.status === 'in_progress'
        && data.round_phase === 'ready'
        && Number(data.current_q_index || 0) === 0
        && !Boolean(data.viewer_ready)
        && !Boolean(data.ready_countdown_started);
}

function syncViewerReadyState(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    if (data.status !== 'in_progress' || data.round_phase !== 'ready' || Number(data.current_q_index || 0) !== 0) {
        return;
    }

    if (Boolean(data.viewer_ready)) {
        viewerReadyConfirmed = true;
    }
}

function resolveLocalTimerSchedule(durationMs, elapsedMs = 0, timingData = null) {
    const nowMs = Date.now();
    const safeDurationMs = Math.max(0, Number(durationMs || 0));
    const initialElapsed = Math.max(0, Math.min(safeDurationMs, Number(elapsedMs || 0)));
    const fallbackStartAt = nowMs - initialElapsed;
    const fallbackExpiresAt = fallbackStartAt + safeDurationMs;

    if (timingData && typeof timingData === 'object') {
        const serverNowMs = Number(timingData.server_now_ms || 0);
        const phaseDeadlineMs = Number(timingData.phase_deadline_ms || 0);

        if (serverNowMs > 0 && phaseDeadlineMs > 0) {
            const serverOffsetMs = nowMs - serverNowMs;
            const expiresAt = phaseDeadlineMs + serverOffsetMs;
            return {
                startAt: expiresAt - safeDurationMs,
                expiresAt,
            };
        }
    }

    return {
        startAt: fallbackStartAt,
        expiresAt: fallbackExpiresAt,
    };
}

function currentTimerRemainingMs(nowMs = Date.now()) {
    if (activeTimerDurationMs <= 0 || activeTimerExpiresAt <= 0) {
        return 0;
    }

    if (activeTimerStartsAt > 0 && nowMs < activeTimerStartsAt) {
        return activeTimerDurationMs;
    }

    return Math.max(0, activeTimerExpiresAt - nowMs);
}

function maybeAckInitialReady(data) {
    syncViewerReadyState(data);

    if (!shouldAckInitialReady(data) || viewerReadyConfirmed || initialReadyAckPending) {
        return;
    }

    initialReadyAckPending = true;
    ackGameReady().done((ackData) => {
        syncViewerReadyState(ackData);
        syncDurations(ackData);
        applyGameState(ackData);
    }).fail((xhr) => {
        if (handleGameAuthFailure(xhr)) {
            return;
        }
    }).always(() => {
        initialReadyAckPending = false;
        speedUpStatusPolling();
        refreshGameStatus();
    });
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


function stopTimer() {
    if (timerInterval !== null) {
        window.cancelAnimationFrame(timerInterval);
    }
    timerInterval = null;
    activeTimerStartsAt = 0;
    activeTimerExpiresAt = 0;
    activeTimerDurationMs = 0;
}

function updateTimerUi(remainingMs) {
    const safeRemaining = Math.max(0, remainingMs);
    const ratio = activeTimerDurationMs > 0
        ? Math.max(0, Math.min(1, safeRemaining / activeTimerDurationMs))
        : 0;

    timerFill.style.transform = `scaleX(${ratio})`;
    timerText.textContent = safeRemaining > 0 ? Math.ceil(safeRemaining / 1000) : '0';
}

function resetTimerDisplay(label = '--') {
    stopTimer();
    activeTimerKey = '';
    timerFill.style.transform = 'scaleX(0)';
    timerText.textContent = label;
}

function startCountdown(durationSeconds, elapsedMs = 0, onExpire = null, timingData = null) {
    stopTimer();

    activeTimerDurationMs = Math.max(0, Number(durationSeconds || 0) * 1000);
    const nextSchedule = resolveLocalTimerSchedule(activeTimerDurationMs, elapsedMs, timingData);
    activeTimerStartsAt = nextSchedule.startAt;
    activeTimerExpiresAt = Math.max(nextSchedule.expiresAt, nextSchedule.startAt);
    const remainingMs = currentTimerRemainingMs();

    updateTimerUi(remainingMs);
    if (remainingMs <= 0) {
        if (typeof onExpire === 'function') {
            onExpire();
        }
        return;
    }

    const tick = () => {
        const nextRemaining = currentTimerRemainingMs();
        updateTimerUi(nextRemaining);

        if (nextRemaining <= 0) {
            stopTimer();
            if (typeof onExpire === 'function') {
                onExpire();
            }
            return;
        }

        timerInterval = window.requestAnimationFrame(tick);
    };

    timerInterval = window.requestAnimationFrame(tick);
}

function syncCountdown(timerKey, durationSeconds, elapsedMs = 0, onExpire = null, timingData = null) {
    const durationMs = Math.max(0, Number(durationSeconds || 0) * 1000);
    const nextSchedule = resolveLocalTimerSchedule(durationMs, elapsedMs, timingData);
    const startDriftMs = activeTimerStartsAt > 0
        ? Math.abs(activeTimerStartsAt - nextSchedule.startAt)
        : Number.POSITIVE_INFINITY;
    const expiryDriftMs = activeTimerExpiresAt > 0
        ? Math.abs(activeTimerExpiresAt - nextSchedule.expiresAt)
        : Number.POSITIVE_INFINITY;

    if (activeTimerKey !== timerKey
        || timerInterval === null
        || activeTimerDurationMs !== durationMs
        || startDriftMs > 350
        || expiryDriftMs > 500) {
        startCountdown(durationSeconds, elapsedMs, onExpire, timingData);
        activeTimerKey = timerKey;
        return;
    }

    if (nextSchedule.startAt < activeTimerStartsAt) {
        activeTimerStartsAt = nextSchedule.startAt;
    }
    if (nextSchedule.expiresAt < activeTimerExpiresAt) {
        activeTimerExpiresAt = nextSchedule.expiresAt;
    }
    activeTimerKey = timerKey;
}

function ensureReadyScreen() {
    if (readyScreenRefs && questionBox.contains(readyScreenRefs.root)) {
        return readyScreenRefs;
    }

    questionBox.dataset.view = 'ready';
    questionBox.innerHTML = `
        <div class="ready-shell">
            <div class="ready-visual">
                ${buildPulseLoaderMarkup()}
            </div>
            <div class="ready-copy">
                <span class="ready-status-chip" data-ready-status>Syncing players</span>
                <p class="pregame-ready ready-headline mb-2" data-ready-title>Get ready!</p>
                <p class="ready-helper subtle-copy mb-0" data-ready-helper>Waiting for everyone to reach the ready screen.</p>
            </div>
            <div class="ready-dashboard">
                <div class="ready-stat-card">
                    <span class="ready-stat-label">Players synced</span>
                    <strong class="ready-stat-value" data-ready-count>0/0</strong>
                </div>
                <div class="ready-stat-card">
                    <span class="ready-stat-label">Launch status</span>
                    <strong class="ready-stat-value ready-stat-value-small" data-ready-launch>Waiting for everyone</strong>
                </div>
            </div>
            <div class="ready-roster" data-ready-roster></div>
        </div>
    `;

    readyScreenRefs = {
        root: questionBox.querySelector('.ready-shell'),
        status: questionBox.querySelector('[data-ready-status]'),
        title: questionBox.querySelector('[data-ready-title]'),
        helper: questionBox.querySelector('[data-ready-helper]'),
        count: questionBox.querySelector('[data-ready-count]'),
        launch: questionBox.querySelector('[data-ready-launch]'),
        roster: questionBox.querySelector('[data-ready-roster]'),
    };

    return readyScreenRefs;
}

function buildReadyRosterMarkup(players = [], viewerReady = false) {
    if (!Array.isArray(players) || players.length === 0) {
        return `
            <span class="ready-roster-pill ready-roster-pill-muted">
                Lobby is syncing players...
            </span>
        `;
    }

    return players.map((player) => {
        const labels = [];
        if (player.is_host) {
            labels.push('Host');
        }
        if (player.is_you) {
            labels.push(viewerReady ? 'You synced' : 'You');
        }

        const metaMarkup = labels.length
            ? `<small>${gameEscapeHtml(labels.join(' · '))}</small>`
            : '';

        return `
            <span class="ready-roster-pill ${player.is_you ? 'is-you' : ''}">
                <strong>${gameEscapeHtml(player.name)}</strong>
                ${metaMarkup}
            </span>
        `;
    }).join('');
}

function renderReadyScreen(
    elapsedMs = 0,
    readyPlayerCount = 0,
    totalPlayerCount = playerCount,
    questionIndex = 0,
    readyCountdownStarted = false,
    timingData = null,
    players = [],
    viewerReady = false
) {
    currentPhase = 'ready';
    currentQuestion = null;
    answered = false;
    answerSubmitPending = false;
    activeRoundSummaryKey = '';
    const refs = ensureReadyScreen();
    const initialRound = Number(questionIndex || 0) === 0;
    const waitingForPlayers = initialRound
        && (
            Number(totalPlayerCount || 0) <= 0
            || Number(readyPlayerCount || 0) < Number(totalPlayerCount || 0)
            || !readyCountdownStarted
        );
    const everyoneSynced = Number(totalPlayerCount || 0) > 0
        && Number(readyPlayerCount || 0) >= Number(totalPlayerCount || 0);
    const helperText = waitingForPlayers
        ? (
            everyoneSynced
                ? 'Everyone is locked in. Waiting for the shared launch timer to settle across the room.'
                : viewerReady
                    ? `You are synced in. Waiting for the rest of the lobby (${Number(readyPlayerCount || 0)}/${Number(totalPlayerCount || 0)} ready).`
                    : `Waiting for everyone to reach the ready screen (${Number(readyPlayerCount || 0)}/${Number(totalPlayerCount || 0)} ready).`
        )
        : 'The whole room is synced. Question 1 will launch together when this countdown ends.';
    const statusLabel = waitingForPlayers
        ? viewerReady
            ? 'You are synced'
            : initialReadyAckPending
                ? 'Syncing your seat'
                : 'Syncing players'
        : 'Shared countdown live';
    const launchLabel = waitingForPlayers
        ? everyoneSynced
            ? 'Arming launch window'
            : viewerReady
                ? 'Waiting for others'
                : 'Joining launch queue'
        : `Question 1 in ${Math.max(0, Math.ceil(((gameApp.ready_duration || 0) * 1000 - Number(elapsedMs || 0)) / 1000))}s`;

    questionCounter.textContent = 'Get Ready';
    categoryBadge.textContent = 'Starting Soon';
    refs.root.classList.toggle('is-countdown-live', !waitingForPlayers);
    refs.root.classList.toggle('is-viewer-ready', Boolean(viewerReady) || initialReadyAckPending);
    refs.status.textContent = statusLabel;
    refs.title.textContent = waitingForPlayers ? 'Get ready!' : 'Launch sequence live';
    refs.helper.textContent = helperText;
    refs.count.textContent = `${Number(readyPlayerCount || 0)}/${Math.max(0, Number(totalPlayerCount || 0))}`;
    refs.launch.textContent = launchLabel;
    refs.roster.innerHTML = buildReadyRosterMarkup(players, viewerReady);
    optionsGrid.classList.add('hidden');
    optionsGrid.innerHTML = '';
    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');
    speedUpStatusPolling();

    if (waitingForPlayers) {
        if (activeTimerKey !== `ready:waiting:${questionIndex}` || timerInterval !== null) {
            resetTimerDisplay('--');
            activeTimerKey = `ready:waiting:${questionIndex}`;
        }
        return;
    }

    syncCountdown(`ready:${questionIndex}`, gameApp.ready_duration || 0, elapsedMs, () => {
        speedUpStatusPolling();
        refreshGameStatus();
    }, timingData);
}

function renderQuestion(data, timingData = null) {
    currentPhase = 'question';
    currentQuestion = data;
    answered = false;
    answerSubmitPending = false;
    lastAnswerSummary = null;
    activeRoundSummaryKey = '';
    readyScreenRefs = null;
    questionBox.dataset.view = 'question';
    restoreStatusPolling();

    questionCounter.textContent = `Question ${data.index + 1} of ${numQuestions}`;
    categoryBadge.textContent = capitalize(data.category);

    questionBox.innerHTML = `
        <div class="question-stage">
            <span class="question-kicker">Make your pick</span>
            <p class="question-text">${gameEscapeHtml(data.text)}</p>
            <p class="question-subtle mb-0">Choose fast, but choose carefully. Once you lock it in, your answer is final.</p>
        </div>
    `;
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
    }, timingData || data);
    activeTimerKey = `question:${data.index}:${data.id}`;
}

function renderWaitingState(isTimeout = false) {
    optionsGrid.classList.add('hidden');
    readyScreenRefs = null;
    questionBox.dataset.view = 'waiting';
    questionBox.innerHTML = `
        <div class="status-card">
            <div class="status-card-visual">
                ${buildPulseLoaderMarkup()}
            </div>
            <p class="pregame-ready mb-2">${isTimeout ? 'Time is up.' : 'Answer locked in.'}</p>
            <p class="mb-0 subtle-copy">Waiting for the rest of the players to finish this question.</p>
        </div>
    `;
    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');
}

function buildRoundSummaryMarkup(summary) {
    if (!summary || isMissingRoundResult(summary)) {
        const correctAnswerLine = summary && summary.correctAnswerText
            ? `<div>Correct answer: <strong>${formatAnswerLabel(summary.correctAnswerText)}</strong></div>`
            : '';

        return `
            <div class="round-summary">
                <strong>No answer submitted.</strong>
                <div class="mt-2">Your answer: <strong>No answer</strong></div>
                ${correctAnswerLine}
            </div>
        `;
    }

    const resultLabel = summary.isTimeout
        ? 'Time expired.'
        : summary.isCorrect
            ? 'Correct.'
            : 'Incorrect.';
    const pointsLine = summary.pointsAwarded > 0
        ? ` You earned ${summary.pointsAwarded} points in ${formatTime(summary.timeMs)}.`
        : summary.isTimeout && summary.timeMs
            ? ` The round timed out at ${formatTime(summary.timeMs)}.`
            : summary.timeMs
                ? ` Response time: ${formatTime(summary.timeMs)}.`
                : '';

    return `
        <div class="round-summary">
            <strong>${resultLabel}</strong>${pointsLine}
            <div class="mt-2">Your answer: <strong>${formatAnswerLabel(summary.chosenText)}</strong></div>
            <div>Correct answer: <strong>${formatAnswerLabel(summary.correctAnswerText)}</strong></div>
        </div>
    `;
}

function renderRoundLeaderboard(players, questionIndex, elapsedMs = 0, viewerRoundResult = null, timingData = null) {
    const serverRoundResult = normalizeRoundResult(viewerRoundResult);
    const summary = serverRoundResult || lastAnswerSummary;

    if (serverRoundResult) {
        lastAnswerSummary = serverRoundResult;
    }

    activeRoundSummaryKey = buildRoundSummaryKey(summary, questionIndex);

    currentPhase = 'leaderboard';
    currentQuestion = null;
    answered = true;
    readyScreenRefs = null;
    questionBox.dataset.view = 'leaderboard';

    questionCounter.textContent = `Round ${Number(questionIndex) + 1} Results`;
    categoryBadge.textContent = 'Leaderboard';
    optionsGrid.classList.add('hidden');
    optionsGrid.innerHTML = '';
    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');
    startCountdown(gameApp.leaderboard_duration || 0, elapsedMs, () => {
        speedUpStatusPolling();
        refreshGameStatus();
    }, timingData);
    activeTimerKey = `leaderboard:${questionIndex}`;

    questionBox.innerHTML = `
        <div class="round-board">
            <h2 class="round-board-title">Round Leaderboard</h2>
            ${buildRoundSummaryMarkup(summary)}
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

function syncCountdownFromProgress(data) {
    if (!data || data.status !== 'in_progress') {
        return;
    }

    const phase = data.round_phase || '';
    const questionIndex = Number(data.current_q_index || 0);
    const elapsedMs = Number(data.phase_elapsed_ms || 0);
    const playerTotal = Number(data.player_count || playerCount || 0);
    const readyPlayerCount = Number(data.ready_player_count || 0);
    const readyCountdownStarted = Boolean(data.ready_countdown_started);
    const waitingForPlayers = phase === 'ready'
        && questionIndex === 0
        && (
            playerTotal <= 0
            || readyPlayerCount < playerTotal
            || !readyCountdownStarted
        );

    if (phase === 'ready' && currentPhase === 'ready') {
        if (waitingForPlayers) {
            resetTimerDisplay('--');
            activeTimerKey = `ready:waiting:${questionIndex}`;
            return;
        }
        syncCountdown(`ready:${questionIndex}`, gameApp.ready_duration || 0, elapsedMs, null, data);
        return;
    }

    if (phase === 'question' && currentPhase === 'question' && currentQuestion
        && Number(currentQuestion.index) === questionIndex) {
        syncCountdown(
            `question:${currentQuestion.index}:${currentQuestion.id}`,
            currentQuestion.time_limit || 0,
            elapsedMs,
            () => {
                if (!answered) {
                    submitAnswer(null, true);
                }
            },
            data
        );
        return;
    }

    if (phase === 'leaderboard' && currentPhase === 'leaderboard') {
        syncCountdown(`leaderboard:${questionIndex}`, gameApp.leaderboard_duration || 0, elapsedMs, null, data);
    }
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
    }, GAME_FAST_STATUS_POLL_MS);
}

function restoreStatusPolling() {
    window.clearInterval(statusPollTimer);
    statusPollTimer = window.setInterval(() => {
        refreshGameStatus();
    }, GAME_STATUS_POLL_MS);
}

function applyGameState(data) {
    if (!acceptIncomingState(data)) {
        return;
    }

    syncDurations(data);
    syncViewerReadyState(data);
    playerCount = typeof data.player_count === 'number' ? data.player_count : playerCount;
    if (typeof data.num_questions === 'number') {
        numQuestions = data.num_questions;
    }

    syncViewerScore(data.players || []);
    endGameButton.classList.toggle('hidden', !(data.viewer_is_host && data.status === 'in_progress'));

    if (data.status === 'finished') {
        goToResults();
        return;
    }

    if (data.round_phase === 'ready') {
        maybeAckInitialReady(data);
        renderReadyScreen(
            data.phase_elapsed_ms || 0,
            data.ready_player_count || 0,
            data.player_count || playerCount,
            data.current_q_index || 0,
            Boolean(data.ready_countdown_started),
            data,
            data.players || [],
            Boolean(data.viewer_ready)
        );
        return;
    }

    if (data.round_phase === 'leaderboard') {
        const leaderboardKey = `leaderboard:${data.current_q_index || 0}`;
        const summaryKey = buildRoundSummaryKey(
            normalizeRoundResult(data.viewer_round_result) || lastAnswerSummary,
            data.current_q_index || 0
        );

        if (currentPhase !== 'leaderboard' || activeTimerKey !== leaderboardKey || activeRoundSummaryKey !== summaryKey) {
            renderRoundLeaderboard(
                data.leaderboard || data.players || [],
                data.current_q_index || 0,
                data.phase_elapsed_ms || 0,
                data.viewer_round_result,
                data
            );
        } else {
            syncCountdown(leaderboardKey, gameApp.leaderboard_duration || 0, data.phase_elapsed_ms || 0, null, data);
        }

        if (!answerSubmitPending) {
            speedUpStatusPolling();
        }
        return;
    }

    if (data.round_phase === 'question') {
        const resolvedQuestion = resolveQuestionData(data.current_question, data.current_q_index || 0);
        if (!resolvedQuestion) {
            return;
        }

        if (!isSameQuestion(resolvedQuestion)) {
            renderQuestion(resolvedQuestion, data);
        } else {
            syncCountdown(
                `question:${resolvedQuestion.index}:${resolvedQuestion.id}`,
                resolvedQuestion.time_limit || 0,
                resolvedQuestion.elapsed_ms || 0,
                () => {
                    if (!answered) {
                        submitAnswer(null, true);
                    }
                },
                data
            );

            if (answerSubmitPending || answered) {
                speedUpStatusPolling();
            } else {
                restoreStatusPolling();
            }
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
    let shouldRefresh = true;

    answered = true;
    answerSubmitPending = true;
    disableOptions();
    renderWaitingState(isTimeout);
    restoreStatusPolling();

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
            questionIndex: Number(currentQuestion ? currentQuestion.index : 0),
            chosenIndex: isTimeout ? null : chosenIndex,
            chosenText,
            correctIndex: Number(data.correct_index || 0),
            correctAnswerText,
            isCorrect: Boolean(data.is_correct),
            isTimeout,
            pointsAwarded: Number(data.points_awarded || 0),
            timeMs: Number(data.time_ms || 0),
        };

        score += Number(data.points_awarded || 0);
        currentScoreElement.textContent = score;
    }).fail((xhr) => {
        if (handleGameAuthFailure(xhr)) {
            shouldRefresh = false;
            return;
        }

        lastAnswerSummary = {
            questionIndex: Number(currentQuestion ? currentQuestion.index : 0),
            chosenIndex: isTimeout ? null : chosenIndex,
            chosenText,
            correctIndex: null,
            correctAnswerText: null,
            isCorrect: false,
            isTimeout,
            pointsAwarded: 0,
            timeMs: 0,
        };
    }).always(() => {
        answerSubmitPending = false;

        if (!shouldRefresh) {
            return;
        }

        speedUpStatusPolling();
        refreshGameStatus();
    });
}

const gameStream = new EventSource(gameStreamUrl);

gameStream.addEventListener('game_start', (event) => {
    const data = JSON.parse(event.data);
    if (!acceptIncomingState(data)) {
        return;
    }
    syncDurations(data);
    syncViewerScore(data.players || []);
    maybeAckInitialReady(data);
    renderReadyScreen(
        data.phase_elapsed_ms || 0,
        data.ready_player_count || 0,
        data.player_count || playerCount,
        data.current_q_index || 0,
        Boolean(data.ready_countdown_started),
        data,
        data.players || [],
        Boolean(data.viewer_ready)
    );
    speedUpStatusPolling();
});

gameStream.addEventListener('question', (event) => {
    const data = JSON.parse(event.data);
    if (!acceptIncomingState(data)) {
        return;
    }
    syncDurations(data);
    syncViewerScore(data.players || []);
    const resolvedQuestion = resolveQuestionData(data, data.current_q_index || data.index || 0);
    if (!resolvedQuestion) {
        return;
    }
    if (!isSameQuestion(resolvedQuestion)) {
        renderQuestion(resolvedQuestion, data);
    }
    restoreStatusPolling();
});

gameStream.addEventListener('round_leaderboard', (event) => {
    const data = JSON.parse(event.data);
    if (!acceptIncomingState(data)) {
        return;
    }
    syncDurations(data);
    syncViewerScore(data.leaderboard || data.players || []);
    renderRoundLeaderboard(
        data.leaderboard || data.players || [],
        data.question_index || 0,
        data.phase_elapsed_ms || 0,
        data.viewer_round_result,
        data
    );
    restoreStatusPolling();
});

gameStream.addEventListener('player_progress', (event) => {
    const data = JSON.parse(event.data);
    if (!acceptIncomingState(data)) {
        return;
    }
    syncDurations(data);
    if (data.round_phase === 'ready') {
        applyGameState(data);
        return;
    }
    syncViewerScore(data.players || []);
    syncCountdownFromProgress(data);
});

gameStream.addEventListener('game_end', () => {
    goToResults();
});

gameStream.onerror = () => {
    speedUpStatusPolling();
};

speedUpStatusPolling();
refreshGameStatus();

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
