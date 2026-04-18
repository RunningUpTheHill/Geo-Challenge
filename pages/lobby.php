<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge – Lobby</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body class="page-lobby">
    <div class="lobby-container">
        <div class="lobby-header">
            <span class="globe-sm">🌍</span>
            <h1>Geo Challenge</h1>
        </div>

        <div class="code-box">
            <div class="code-label">Session Code</div>
            <div class="code-value" id="session-code">------</div>
            <button id="copy-btn" class="btn btn-small">Copy</button>
        </div>

        <div class="players-section">
            <div class="players-header">
                <h2>Players</h2>
                <span id="player-count" class="player-count-badge">0</span>
            </div>
            <ul id="player-list" class="player-list"></ul>
        </div>

        <div id="host-controls" class="host-controls hidden">
            <p class="hint">Share the code above, then start when everyone's ready!</p>
            <button id="start-btn" class="btn btn-primary btn-large btn-block">Start Game</button>
        </div>

        <div id="waiting-msg" class="waiting-section hidden">
            <div class="spinner"></div>
            <p>Waiting for the host to start the game&hellip;</p>
        </div>

        <div id="status-msg" class="status-msg" aria-live="polite"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/public/js/lobby.js"></script>
</body>
</html>
