const code      = window.location.pathname.split('/').pop();
const playerId  = parseInt(sessionStorage.getItem('gc_player_id'), 10);
const myName    = sessionStorage.getItem('gc_player_name') || '';
let isHost      = false;
let hasNavigatedToGame = false;
let startFallbackTimer = null;

document.getElementById('session-code').textContent = code;
const hostControls = document.getElementById('host-controls');
const waitingMsg   = document.getElementById('waiting-msg');
const statusMsg    = document.getElementById('status-msg');
const startBtn     = document.getElementById('start-btn');
const hasValidPlayerId = Number.isInteger(playerId) && playerId > 0;

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function setHostState(nextIsHost) {
    isHost = nextIsHost;
    hostControls.classList.toggle('hidden', !isHost);
    waitingMsg.classList.toggle('hidden', isHost);
}

function goToGame() {
    if (hasNavigatedToGame) return;
    hasNavigatedToGame = true;
    clearInterval(startFallbackTimer);
    es.close();
    window.location.href = '/game/' + code;
}

async function fetchStatus() {
    const res = await fetch(`/api/session/${code}/status`, { cache: 'no-store' });
    if (!res.ok) {
        throw new Error('Failed to load session status');
    }
    return res.json();
}

function startGameFallbackPolling() {
    clearInterval(startFallbackTimer);
    startFallbackTimer = setInterval(async () => {
        if (hasNavigatedToGame) return;

        try {
            const data = await fetchStatus();
            if (data.status === 'in_progress') {
                goToGame();
            } else if (data.status === 'finished') {
                clearInterval(startFallbackTimer);
                window.location.href = '/results/' + code;
            }
        } catch (err) {
            // Keep polling until SSE or status confirms the transition.
        }
    }, 750);
}

function renderPlayers(players) {
    const list  = document.getElementById('player-list');
    const badge = document.getElementById('player-count');
    badge.textContent = players.length;

    list.innerHTML = players.map(p => {
        const isYou = (p.id && p.id === playerId) || p.name === myName;
        return `<li class="player-item">
            <span class="player-avatar">${escapeHtml(p.name.charAt(0).toUpperCase())}</span>
            <span class="player-name">${escapeHtml(p.name)}</span>
            ${isYou ? '<span class="you-badge">You</span>' : ''}
        </li>`;
    }).join('');
}

if (!hasValidPlayerId) {
    window.location.replace('/');
    throw new Error('Missing player session');
}

// ── SSE connection ───────────────────────────────────────────────────
const es = new EventSource(`/api/stream/${code}/${playerId}`);

// Hydrate player list on page load
fetchStatus()
    .then(data => {
        setHostState(data.host_player_id === playerId);
        if (data.status === 'in_progress') {
            goToGame();
        } else if (data.status === 'finished') {
            es.close();
            window.location.href = '/results/' + code;
        } else {
            renderPlayers(data.players);
        }
    })
    .catch(() => {
        statusMsg.textContent = 'Could not load the latest session state.';
    });

es.addEventListener('lobby_update', e => {
    const data = JSON.parse(e.data);
    setHostState(data.host_player_id === playerId);
    renderPlayers(data.players);
    statusMsg.textContent = '';
});

es.addEventListener('game_start', () => {
    goToGame();
});

es.onerror = () => {
    if (!hasNavigatedToGame) {
        statusMsg.textContent = 'Connection issue - reconnecting...';
    }
};

// ── Copy code button ─────────────────────────────────────────────────
document.getElementById('copy-btn').addEventListener('click', () => {
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById('copy-btn');
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
});

// ── Start game button (host only) ────────────────────────────────────
if (startBtn) {
    startBtn.addEventListener('click', async () => {
        startBtn.disabled    = true;
        startBtn.textContent = 'Starting...';

        try {
            const res  = await fetch('/api/session/start', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ session_code: code, player_id: playerId }),
            });
            const data = await res.json();

            if (!res.ok) {
                statusMsg.textContent = data.error || 'Could not start game.';
                startBtn.disabled    = false;
                startBtn.textContent = 'Start Game';
                return;
            }

            statusMsg.textContent = 'Starting game...';
            goToGame();
        } catch (e) {
            statusMsg.textContent = 'Network error.';
            startBtn.disabled    = false;
            startBtn.textContent = 'Start Game';
        }
    });
}
