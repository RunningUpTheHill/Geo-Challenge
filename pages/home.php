<?php
$flash = pull_flash_message();
$page_state = app_state([
    'page' => 'home',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= escape_html(asset_url('public/css/style.css')) ?>">
</head>
<body class="page-home">
    <div class="home-bg" aria-hidden="true"></div>
    <main class="container py-5">
        <section class="hero-panel row align-items-center g-4 mb-4">
            <div class="col-lg-6">
                <div class="eyebrow">Multiplayer Geography Trivia</div>
                <h1 class="logo">Geo Challenge</h1>
                <p class="tagline">Launch a live trivia room, challenge your classmates, and race through capitals, flags, languages, currencies, and world geography.</p>
                <div class="hero-highlights">
                    <span class="highlight-pill">PHP Sessions</span>
                    <span class="highlight-pill">MySQL + Join Queries</span>
                    <span class="highlight-pill">JSON API + SSE</span>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img
                    src="<?= escape_html(app_url('public/img/geo-hero.svg')) ?>"
                    alt="Illustrated globe with map markers and a trophy"
                    class="hero-graphic img-fluid"
                >
            </div>
        </section>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= escape_html($flash['type']) ?> app-alert" role="alert">
                <?= escape_html($flash['message']) ?>
            </div>
        <?php endif; ?>

        <section class="row g-4 align-items-stretch">
            <div class="col-lg-6">
                <div class="card app-card h-100 border-0 shadow-lg">
                    <div class="card-body p-4">
                        <h2 class="h3 mb-2">Create Game</h2>
                        <p class="card-desc">Start a new room, pick how many questions you want, and share the 6-character code with your group.</p>
                        <form id="create-form" method="post" action="<?= escape_html(app_url('create_session.php')) ?>" novalidate>
                            <div class="mb-3">
                                <label class="form-label" for="create-name">Your Name</label>
                                <input class="form-control" type="text" id="create-name" name="player_name" placeholder="Enter your name" maxlength="32" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="create-questions">Number of Questions</label>
                                <select class="form-select" id="create-questions" name="num_questions">
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="15">15</option>
                                    <option value="20">20</option>
                                </select>
                            </div>
                            <button id="create-btn" type="submit" class="btn btn-primary btn-lg w-100">Create Game</button>
                            <div id="create-error" class="alert alert-danger app-inline-alert d-none mt-3 mb-0" aria-live="polite"></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card app-card h-100 border-0 shadow-lg">
                    <div class="card-body p-4">
                        <h2 class="h3 mb-2">Join Game</h2>
                        <p class="card-desc">Hop into an active room with the host’s code and start competing immediately.</p>
                        <form id="join-form" method="post" action="<?= escape_html(app_url('join_session.php')) ?>" novalidate>
                            <div class="mb-3">
                                <label class="form-label" for="join-name">Your Name</label>
                                <input class="form-control" type="text" id="join-name" name="player_name" placeholder="Enter your name" maxlength="32" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="join-code">Session Code</label>
                                <input class="form-control code-input" type="text" id="join-code" name="code" placeholder="e.g. XK92TF" maxlength="6" autocomplete="off">
                            </div>
                            <button id="join-btn" type="submit" class="btn btn-outline-light btn-lg w-100">Join Game</button>
                            <div id="join-error" class="alert alert-danger app-inline-alert d-none mt-3 mb-0" aria-live="polite"></div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        window.GEO_CHALLENGE = <?= json_encode($page_state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escape_html(asset_url('public/js/player-auth.js')) ?>"></script>
    <script src="<?= escape_html(asset_url('public/js/home.js')) ?>"></script>
</body>
</html>
