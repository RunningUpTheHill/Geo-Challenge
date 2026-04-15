<?php
$code = strtoupper($route_params['code']);
$page_state = app_state([
    'page' => 'game',
    'sessionCode' => $code,
    'gameReadyUrl' => app_url('game_ready.php?code=' . urlencode($code)),
    'statusUrl' => app_url('session_status.php?code=' . urlencode($code)),
    'streamUrl' => app_url('stream.php?code=' . urlencode($code)),
    'resultsUrl' => app_url('results.php?code=' . urlencode($code)),
    'question_duration' => QUESTION_DURATION_SEC,
    'leaderboard_duration' => LEADERBOARD_PHASE_SEC,
    'ready_duration' => READY_PHASE_SEC,
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge - Game</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= escape_html(asset_url('public/css/style.css')) ?>">
</head>

<body class="page-game">
    <header class="game-header">
        <div class="game-meta">
            <span id="question-counter" class="q-counter">Get Ready</span>
            <span id="category-badge" class="badge text-bg-primary">Starting Soon</span>
        </div>
        <div class="score-display">
            Score: <strong id="current-score">0</strong>
        </div>
    </header>

    <div class="timer-wrapper">
        <div class="timer-track">
            <div id="timer-fill" class="timer-fill"></div>
        </div>
        <div id="timer-text" class="timer-text">--</div>
    </div>

    <main class="container game-layout py-4">
        <div class="row g-4">
            <div class="col-12">
                <section class="card app-card border-0 shadow-lg question-panel">
                    <div class="card-body p-4 p-lg-5">
                        <div id="question-box" class="question-box">
                            <div class="ready-shell">
                                <div class="ready-visual">
                                    <div class="sync-loader" aria-hidden="true">
                                        <span class="sync-loader-ring sync-loader-ring-a"></span>
                                        <span class="sync-loader-ring sync-loader-ring-b"></span>
                                        <span class="sync-loader-core"></span>
                                    </div>
                                </div>
                                <div class="ready-copy">
                                    <span class="ready-status-chip">Syncing players</span>
                                    <p class="pregame-ready ready-headline mb-2">Get ready!</p>
                                    <p class="ready-helper subtle-copy mb-0">Waiting for the game to start and sync every player.</p>
                                </div>
                                <div class="ready-dashboard">
                                    <div class="ready-stat-card">
                                        <span class="ready-stat-label">Players synced</span>
                                        <strong class="ready-stat-value">0/0</strong>
                                    </div>
                                    <div class="ready-stat-card">
                                        <span class="ready-stat-label">Launch status</span>
                                        <strong class="ready-stat-value ready-stat-value-small">Preparing room</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="options-grid" class="options-grid hidden"></div>

                        <div id="feedback-overlay" class="feedback-overlay hidden">
                            <div id="feedback-message" class="feedback-message"></div>
                            <div id="waiting-next" class="waiting-next hidden">
                                <div class="spinner-border spinner-border-sm text-info" role="status"
                                    aria-hidden="true"></div>
                                <span>Waiting for other players&hellip;</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <button id="end-game-btn" class="btn btn-danger btn-end-game hidden">End Game</button>

    <div class="modal fade" id="end-game-modal" tabindex="-1" aria-labelledby="end-game-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content app-modal">
                <div class="modal-header border-0">
                    <h2 class="modal-title fs-5" id="end-game-modal-label">End this game for everyone?</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    The current round will close immediately and everyone will be sent to the final results screen.
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-end-game">End Game</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.GEO_CHALLENGE = <?= json_encode($page_state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escape_html(asset_url('public/js/player-auth.js')) ?>"></script>
    <script src="<?= escape_html(asset_url('public/js/game.js')) ?>"></script>
</body>

</html>
