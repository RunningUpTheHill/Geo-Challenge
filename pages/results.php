<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge – Results</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body class="page-results">
    <div class="results-container">
        <h1 class="results-title">🏆 Final Results</h1>

        <div id="podium" class="podium"></div>

        <table class="results-table table table-striped table-hover" id="results-table">
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/public/js/results.js"></script>
</body>
</html>
