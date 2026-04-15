<?php
$code = strtoupper($route_params['code']);
$page_state = app_state([
    'page' => 'lobby',
    'sessionCode' => $code,
    'statusUrl' => app_url('session_status.php?code=' . urlencode($code)),
    'streamUrl' => app_url('stream.php?code=' . urlencode($code)),
    'gameUrl' => app_url('game.php?code=' . urlencode($code)),
    'resultsUrl' => app_url('results.php?code=' . urlencode($code)),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge - Lobby</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= escape_html(asset_url('public/css/style.css')) ?>">
</head>
<body class="page-lobby">
    <main class="container py-5">
        <div class="lobby-shell mx-auto">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                <div>
                    <div class="eyebrow">Waiting Room</div>
                    <h1 class="page-title mb-1">Geo Challenge Lobby</h1>
                    <p class="subtle-copy mb-0">Share the code below, then start when everyone is ready.</p>
                </div>
                <a href="<?= escape_html(app_url('')) ?>" class="btn btn-outline-light">Back Home</a>
            </div>

            <section class="code-box mb-4">
                <div>
                    <div class="code-label">Session Code</div>
                    <div class="code-value" id="session-code"><?= escape_html($code) ?></div>
                </div>
                <button id="copy-btn" class="btn btn-outline-light">Copy Code</button>
            </section>

            <section class="players-section">
                <div class="players-header">
                    <h2 class="h4 mb-0">Players</h2>
                    <span id="player-count" class="badge rounded-pill text-bg-primary">0</span>
                </div>
                <ul id="player-list" class="list-group player-list mt-3"></ul>
            </section>

            <div id="host-controls" class="hidden mt-4">
                <div class="alert alert-info app-alert mb-3">You are the host for this room. Start the game when the full team has joined.</div>
                <button id="start-btn" class="btn btn-primary btn-lg w-100">Start Game</button>
            </div>

            <div id="waiting-msg" class="hidden waiting-section mt-4">
                <div class="spinner-border text-info mb-3" role="status" aria-hidden="true"></div>
                <p class="mb-0">Waiting for the host to start the game&hellip;</p>
            </div>

            <div id="status-msg" class="alert app-inline-alert d-none mt-4" aria-live="polite"></div>
        </div>
    </main>

    <script>
        window.GEO_CHALLENGE = <?= json_encode($page_state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escape_html(asset_url('public/js/player-auth.js')) ?>"></script>
    <script src="<?= escape_html(asset_url('public/js/lobby.js')) ?>"></script>
</body>
</html>
