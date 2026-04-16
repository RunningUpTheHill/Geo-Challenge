<?php
$code = strtoupper($route_params['code']);
$page_state = app_state([
    'page' => 'results',
    'sessionCode' => $code,
    'resultsApiUrl' => app_url('session_results.php?code=' . urlencode($code)),
    'shareUrl' => app_url('results.php?code=' . urlencode($code)),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge - Results</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= escape_html(asset_url('public/css/style.css')) ?>">
</head>
<body class="page-results">
    <main class="container py-5">
        <section class="results-shell mx-auto">
            <div class="text-center mb-4">
                <div class="eyebrow">Session Complete</div>
                <h1 class="results-title">Final Results</h1>
                <p class="subtle-copy mb-0">Ranked by accuracy &mdash; most correct answers wins. Ties are broken by total response time.</p>
            </div>

            <div id="podium" class="podium mb-5"></div>

            <div class="card app-card border-0 shadow-lg">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 results-table" id="results-table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Player</th>
                                    <th scope="col">Correct</th>
                                    <th scope="col">Score</th>
                                    <th scope="col">Time</th>
                                </tr>
                            </thead>
                            <tbody id="results-body">
                                <tr><td colspan="5" class="loading-row">Loading results&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="results-actions mt-4">
                <a href="<?= escape_html(app_url('')) ?>" class="btn btn-primary">Play Again</a>
                <button id="share-btn" class="btn btn-outline-light">Copy Results Link</button>
            </div>
        </section>
    </main>

    <script>
        window.GEO_CHALLENGE = <?= json_encode($page_state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= escape_html(asset_url('public/js/player-auth.js')) ?>"></script>
    <script src="<?= escape_html(asset_url('public/js/results.js')) ?>"></script>
</body>
</html>
