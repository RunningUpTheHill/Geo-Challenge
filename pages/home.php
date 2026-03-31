<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body class="page-home">
    <div class="hero">
        <div class="globe-icon">🌍</div>
        <h1 class="logo">Geo Challenge</h1>
        <p class="tagline">Test your geography knowledge against players worldwide</p>
    </div>

    <div class="cards">
        <div class="card">
            <h2>Create Game</h2>
            <p class="card-desc">Start a new session and invite friends with a 6-letter code</p>
            <div class="form-group">
                <label for="create-name">Your Name</label>
                <input type="text" id="create-name" placeholder="Enter your name" maxlength="32" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="create-questions">Number of Questions</label>
                <select id="create-questions">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="15">15</option>
                    <option value="20">20</option>
                </select>
            </div>
            <button id="create-btn" class="btn btn-primary btn-block">Create Game</button>
            <div id="create-error" class="error-msg" aria-live="polite"></div>
        </div>

        <div class="divider"><span>OR</span></div>

        <div class="card">
            <h2>Join Game</h2>
            <p class="card-desc">Enter a session code to join an existing game</p>
            <div class="form-group">
                <label for="join-name">Your Name</label>
                <input type="text" id="join-name" placeholder="Enter your name" maxlength="32" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="join-code">Session Code</label>
                <input type="text" id="join-code" placeholder="e.g. XK92TF" maxlength="6"
                       autocomplete="off" style="text-transform:uppercase;letter-spacing:4px;font-size:1.2rem">
            </div>
            <button id="join-btn" class="btn btn-secondary btn-block">Join Game</button>
            <div id="join-error" class="error-msg" aria-live="polite"></div>
        </div>
    </div>

    <script src="/public/js/home.js"></script>
</body>
</html>
