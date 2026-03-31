const code     = window.location.pathname.split('/').pop();
const playerId = parseInt(sessionStorage.getItem('gc_player_id'), 10);

if (!Number.isInteger(playerId) || playerId <= 0) {
    window.location.replace('/');
    throw new Error('Missing player session');
}

let currentQuestion  = null;
let questionStartTime = null;
let timerInterval    = null;
let answered         = false;
let score            = 0;
let statusPollTimer  = null;
let playerCount      = 0;

const questionBox    = document.getElementById('question-box');
const optionsGrid    = document.getElementById('options-grid');
const feedbackOverlay = document.getElementById('feedback-overlay');
const feedbackMessage = document.getElementById('feedback-message');
const waitingNext    = document.getElementById('waiting-next');
const waitingNextText = waitingNext.querySelector('span');
const timerFill      = document.getElementById('timer-fill');
const timerText      = document.getElementById('timer-text');
const miniScoreboard = document.getElementById('mini-scoreboard');
const miniScoreList  = document.getElementById('mini-score-list');

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function isSameQuestion(data) {
    return !!currentQuestion
        && currentQuestion.id === data.id
        && currentQuestion.index === data.index;
}

async function fetchStatus() {
    const res = await fetch(`/api/session/${code}/status`, { cache: 'no-store' });
    if (!res.ok) {
        throw new Error('Failed to load game state');
    }
    return res.json();
}

function renderMiniScoreboard(players) {
    if (!Array.isArray(players) || players.length === 0) return;

    playerCount = players.length;
    miniScoreboard.classList.remove('hidden');
    miniScoreList.innerHTML = players.map(p =>
        `<li><span class="ms-name">${escapeHtml(p.name)}</span><span class="ms-score">${p.score}</span></li>`
    ).join('');
}

function applyGameState(data) {
    playerCount = typeof data.player_count === 'number' ? data.player_count : playerCount;

    if (data.status === 'finished') {
        es.close();
        clearInterval(statusPollTimer);
        window.location.href = '/results/' + code;
        return;
    }

    if (data.status === 'in_progress' && data.current_question && !isSameQuestion(data.current_question)) {
        renderQuestion(data.current_question);
    }

    renderMiniScoreboard(data.players);
}

// ── Question rendering ───────────────────────────────────────────────

function renderQuestion(data) {
    currentQuestion   = data;
    answered          = false;
    restoreStatusPolling();

    document.getElementById('question-counter').textContent =
        `Question ${data.index + 1} of 10`;
    document.getElementById('category-badge').textContent = capitalize(data.category);

    questionBox.innerHTML = `<p class="question-text">${escapeHtml(data.text)}</p>`;

    optionsGrid.innerHTML = data.options.map((opt, i) =>
        `<button class="option-btn" data-index="${i}">
            <span class="option-letter">${'ABCD'[i]}</span>
            <span class="option-text">${escapeHtml(opt)}</span>
        </button>`
    ).join('');
    optionsGrid.classList.remove('hidden');

    optionsGrid.querySelectorAll('.option-btn').forEach(btn => {
        btn.addEventListener('click', () => submitAnswer(parseInt(btn.dataset.index, 10)));
    });

    feedbackOverlay.classList.add('hidden');
    waitingNext.classList.add('hidden');

    startTimer(data.time_limit, data.server_time);
}

// ── Timer ────────────────────────────────────────────────────────────

function startTimer(duration, serverTime) {
    clearInterval(timerInterval);

    // Compensate for network latency using server timestamp
    const serverNowMs = serverTime * 1000;
    const elapsed     = Math.max(0, Date.now() - serverNowMs);
    const remaining   = Math.max(0, duration * 1000 - elapsed);

    questionStartTime = Date.now() - elapsed;

    // Reset bar: kill transition, snap to current width, then re-enable
    timerFill.style.transition = 'none';
    timerFill.style.width      = (remaining / (duration * 1000) * 100) + '%';
    void timerFill.offsetWidth; // force reflow
    timerFill.style.transition = `width ${remaining / 1000}s linear`;
    timerFill.style.width      = '0%';

    let remainingSec = Math.ceil(remaining / 1000);
    timerText.textContent = remainingSec;

    timerInterval = setInterval(() => {
        remainingSec--;
        timerText.textContent = Math.max(0, remainingSec);
        if (remainingSec <= 0) {
            clearInterval(timerInterval);
            if (!answered) {
                submitTimeout();
            }
        }
    }, 1000);
}

// ── Answer submission ────────────────────────────────────────────────

async function submitAnswer(chosenIndex, isTimeout = false) {
    if (answered || !currentQuestion) return;
    answered = true;
    clearInterval(timerInterval);
    disableOptions();

    if (isTimeout) {
        showFeedback(null, null, null);
        speedUpStatusPolling();
    }

    try {
        const res  = await fetch('/api/answer/submit', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                player_id:    playerId,
                session_code: code,
                question_id:  currentQuestion.id,
                chosen_index: isTimeout ? null : chosenIndex,
            }),
        });
        const data = await res.json();

        if (res.ok) {
            if (data.is_correct) score++;
            document.getElementById('current-score').textContent = score;
            if (!isTimeout) {
                showFeedback(chosenIndex, data.correct_index, data.is_correct);
                speedUpStatusPolling();
            }
        } else if (!isTimeout) {
            showFeedback(chosenIndex, null, null);
            speedUpStatusPolling();
        }
    } catch (e) {
        if (!isTimeout) {
            showFeedback(chosenIndex, null, null);
            speedUpStatusPolling();
        }
    }
}

function submitTimeout() {
    submitAnswer(null, true);
}

function setWaitingMessage() {
    if (playerCount <= 1) {
        waitingNextText.textContent = 'Loading next question...';
    } else {
        waitingNextText.textContent = 'Waiting for other players...';
    }
}

function showFeedback(chosen, correct, isCorrect) {
    const btns = optionsGrid.querySelectorAll('.option-btn');

    if (correct !== null) {
        btns.forEach((btn, i) => {
            if (i === correct) btn.classList.add('correct');
            else if (i === chosen && !isCorrect) btn.classList.add('wrong');
        });
        feedbackMessage.innerHTML = isCorrect
            ? '<span class="fb-correct">&#10003; Correct!</span>'
            : `<span class="fb-wrong">&#10007; Wrong — the answer was <strong>${escapeHtml(currentQuestion.options[correct])}</strong></span>`;
    } else {
        feedbackMessage.innerHTML = '<span class="fb-wrong">&#8987; Time\'s up!</span>';
    }

    setWaitingMessage();
    feedbackOverlay.classList.remove('hidden');
    waitingNext.classList.remove('hidden');
}

function disableOptions() {
    optionsGrid.querySelectorAll('.option-btn').forEach(btn => btn.disabled = true);
}

function speedUpStatusPolling() {
    clearInterval(statusPollTimer);
    statusPollTimer = setInterval(async () => {
        try {
            const data = await fetchStatus();
            applyGameState(data);
        } catch (err) {
            // EventSource is still the primary transport; polling is a fallback.
        }
    }, 250);
}

function restoreStatusPolling() {
    clearInterval(statusPollTimer);
    statusPollTimer = setInterval(async () => {
        try {
            const data = await fetchStatus();
            applyGameState(data);
        } catch (err) {
            // EventSource is still the primary transport; polling is a fallback.
        }
    }, 1000);
}

// ── SSE ──────────────────────────────────────────────────────────────

const es = new EventSource(`/api/stream/${code}/${playerId}`);

es.addEventListener('game_start', () => {
    // Game starting — clear the waiting screen; question event follows immediately
    questionBox.innerHTML = '<p class="pregame-ready">Get ready!</p>';
    speedUpStatusPolling();
});

es.addEventListener('question', e => {
    const data = JSON.parse(e.data);
    if (!isSameQuestion(data)) {
        renderQuestion(data);
    }
});

es.addEventListener('player_progress', e => {
    const data = JSON.parse(e.data);
    renderMiniScoreboard(data.players);
});

es.addEventListener('game_end', e => {
    const data = JSON.parse(e.data);
    sessionStorage.setItem('gc_leaderboard', JSON.stringify(data.leaderboard));
    clearInterval(statusPollTimer);
    es.close();
    window.location.href = '/results/' + code;
});

es.onerror = () => {
    // EventSource auto-reconnects; no user action needed
};

fetchStatus()
    .then(data => {
        applyGameState(data);
        if (data.status === 'in_progress') {
            speedUpStatusPolling();
        }
    })
    .catch(() => {
        // Leave the waiting UI in place and let SSE/polling recover.
    });

restoreStatusPolling();
