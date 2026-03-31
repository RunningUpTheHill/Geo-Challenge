<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge – Game</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body class="page-game">
    <header class="game-header">
        <div class="game-meta">
            <span id="question-counter" class="q-counter">Question 1 of 10</span>
            <span id="category-badge" class="badge">Geography</span>
        </div>
        <div class="score-display">
            Score: <strong id="current-score">0</strong>
        </div>
    </header>

    <div class="timer-track">
        <div id="timer-fill" class="timer-fill"></div>
    </div>
    <div id="timer-text" class="timer-text">20</div>

    <main class="game-main">
        <div id="question-box" class="question-box">
            <div class="pregame-wait">
                <div class="spinner"></div>
                <p>Waiting for the game to start&hellip;</p>
            </div>
        </div>

        <div id="options-grid" class="options-grid hidden"></div>

        <div id="feedback-overlay" class="feedback-overlay hidden">
            <div id="feedback-message" class="feedback-message"></div>
            <div id="waiting-next" class="waiting-next hidden">
                <div class="spinner spinner-sm"></div>
                <span>Waiting for other players&hellip;</span>
            </div>
        </div>

    </main>

    <aside id="mini-scoreboard" class="mini-scoreboard hidden">
        <h3>Live Scores</h3>
        <ul id="mini-score-list"></ul>
    </aside>

    <button id="end-game-btn" class="btn btn-end-game hidden">End Game</button>

    <script src="/public/js/game.js"></script>
</body>
</html>
