const lobbyApp = window.GEO_CHALLENGE || {};
const lobbyAuth = window.GEO_PLAYER_AUTH.requireSession(lobbyApp.sessionCode, lobbyApp.urls.home);

if (!lobbyAuth) {
    throw new Error('Missing player session.');
}

const LOBBY_FALLBACK_POLL_MS = 1000;

let lobbyIsHost = false;
let lobbyNavigatedToGame = false;
let lobbyFallbackTimer = null;

const $hostControls = $('#host-controls');
const $waitingMsg = $('#waiting-msg');
const $statusMsg = $('#status-msg');
const $startBtn = $('#start-btn');
const sessionCode = lobbyApp.sessionCode || '';
const playerId = Number(lobbyAuth.playerId || 0);
const statusUrl = window.GEO_PLAYER_AUTH.withPlayerToken(lobbyApp.statusUrl, lobbyAuth.playerToken);
const streamUrl = window.GEO_PLAYER_AUTH.withPlayerToken(lobbyApp.streamUrl, lobbyAuth.playerToken);

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

function handleLobbyAuthFailure(xhr) {
    return window.GEO_PLAYER_AUTH.handleAuthFailure(xhr, sessionCode, lobbyApp.urls.home);
}

function startLobbyFallbackPolling() {
    window.clearInterval(lobbyFallbackTimer);
    lobbyFallbackTimer = window.setInterval(() => {
        if (lobbyNavigatedToGame) {
            return;
        }

        fetchLobbyStatus().done((data) => {
            setHostState(data.viewer_is_host);

            if (data.status === 'in_progress') {
                goToGame();
            } else if (data.status === 'finished') {
                window.clearInterval(lobbyFallbackTimer);
                window.location.href = lobbyApp.resultsUrl;
            } else {
                renderPlayers(data.players || []);
            }
        }).fail((xhr) => {
            handleLobbyAuthFailure(xhr);
        });
    }, LOBBY_FALLBACK_POLL_MS);
}

function renderPlayers(players) {
    $('#player-count').text(players.length);

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

    $('#player-list').html(markup);
}

const lobbyStream = new EventSource(streamUrl);

fetchLobbyStatus().done((data) => {
    setHostState(data.viewer_is_host);
    if (data.status === 'in_progress') {
        goToGame();
    } else if (data.status === 'finished') {
        lobbyStream.close();
        window.location.href = lobbyApp.resultsUrl;
    } else {
        renderPlayers(data.players);
    }
}).fail((xhr) => {
    if (handleLobbyAuthFailure(xhr)) {
        return;
    }

    setLobbyStatus('Could not load the latest session state.', 'danger');
});

lobbyStream.addEventListener('lobby_update', (event) => {
    const data = JSON.parse(event.data);
    setHostState(data.viewer_is_host);
    renderPlayers(data.players);
    setLobbyStatus('');
});

lobbyStream.addEventListener('game_start', () => {
    goToGame();
});

lobbyStream.onerror = () => {
    if (!lobbyNavigatedToGame) {
        setLobbyStatus('Connection issue. Reconnecting...', 'warning');
    }
};

$('#copy-btn').on('click', () => {
    navigator.clipboard.writeText(sessionCode).then(() => {
        const originalText = $('#copy-btn').text();
        $('#copy-btn').text('Copied!');
        window.setTimeout(() => {
            $('#copy-btn').text(originalText);
        }, 1800);
    });
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
        $startBtn.prop('disabled', false).text('Start Game');
    });
});

startLobbyFallbackPolling();
