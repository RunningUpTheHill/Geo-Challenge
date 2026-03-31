<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge – Results</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body class="page-results">
    <div class="results-container">
        <h1 class="results-title">🏆 Final Results</h1>

        <div id="podium" class="podium"></div>

        <table class="results-table" id="results-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Player</th>
                    <th>Score</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody id="results-body">
                <tr><td colspan="4" class="loading-row">Loading results&hellip;</td></tr>
            </tbody>
        </table>

        <div class="results-actions">
            <a href="/" class="btn btn-primary">Play Again</a>
            <button id="share-btn" class="btn btn-secondary">Copy Link</button>
        </div>
    </div>

    <script src="/public/js/results.js"></script>
</body>
</html>
