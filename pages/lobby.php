<?php
$code = strtoupper($route_params['code']);
$page_state = app_state([
    'page' => 'lobby',
    'sessionCode' => $code,
    'statusUrl' => app_url('session_status.php?code=' . urlencode($code)),
    'streamUrl' => app_url('stream.php?code=' . urlencode($code)),
    'gameUrl' => app_url('game.php?code=' . urlencode($code)),
    'resultsUrl' => app_url('results.php?code=' . urlencode($code)),
    'customQuizStateUrl' => app_url('custom_quiz_state.php?code=' . urlencode($code)),
    'quizModeUrl' => app_url('set_quiz_mode.php'),
    'customQuestionSaveUrl' => app_url('save_custom_question.php'),
    'customQuestionDeleteUrl' => app_url('delete_custom_question.php'),
    'customQuestionMoveUrl' => app_url('move_custom_question.php'),
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
                <div class="alert alert-info app-alert mb-3">You are the host for this room. Choose the quiz mode, build your room if needed, then start when everyone has joined.</div>

                <section class="builder-shell mb-4">
                    <div class="builder-mode-header">
                        <div>
                            <h2 class="h4 mb-1">Quiz Mode</h2>
                            <p id="quiz-mode-help" class="subtle-copy mb-0">Use the built-in geography bank or switch this room to a host-made custom quiz.</p>
                        </div>
                        <div class="btn-group quiz-mode-toggle" role="group" aria-label="Quiz mode">
                            <button type="button" id="mode-built-in" class="btn btn-outline-light active">Built-In</button>
                            <button type="button" id="mode-custom" class="btn btn-outline-light">Custom</button>
                        </div>
                    </div>
                    <div id="custom-quiz-summary" class="builder-summary mt-3">Built-in mode uses the question count chosen when the room was created.</div>
                </section>

                <section id="custom-builder" class="builder-shell hidden mb-4">
                    <div class="builder-mode-header align-items-start">
                        <div>
                            <h2 class="h4 mb-1">Custom Quiz Builder</h2>
                            <p class="subtle-copy mb-0">Add your own 4-option questions for this room. Uploaded images stay attached to this room only.</p>
                        </div>
                    </div>

                    <div id="custom-quiz-empty" class="empty-builder-state mt-3">No custom questions yet. Add one below to make this room playable in custom mode.</div>
                    <div id="custom-quiz-list" class="custom-quiz-list mt-3"></div>

                    <div class="builder-form-shell mt-4">
                        <form id="custom-question-form" enctype="multipart/form-data" novalidate>
                            <input type="hidden" id="custom-question-id" name="question_id" value="">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="custom-topic-label">Topic Label</label>
                                    <input class="form-control" type="text" id="custom-topic-label" name="topic_label" maxlength="80" placeholder="e.g. Science, Movies, History">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="custom-question-text">Question</label>
                                    <input class="form-control" type="text" id="custom-question-text" name="question_text" maxlength="255" placeholder="Write your question">
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label" for="custom-option-0">Answer Option 1</label>
                                    <input class="form-control" type="text" id="custom-option-0" name="option_0" maxlength="120" placeholder="First answer">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="custom-option-1">Answer Option 2</label>
                                    <input class="form-control" type="text" id="custom-option-1" name="option_1" maxlength="120" placeholder="Second answer">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="custom-option-2">Answer Option 3</label>
                                    <input class="form-control" type="text" id="custom-option-2" name="option_2" maxlength="120" placeholder="Third answer">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="custom-option-3">Answer Option 4</label>
                                    <input class="form-control" type="text" id="custom-option-3" name="option_3" maxlength="120" placeholder="Fourth answer">
                                </div>
                            </div>

                            <div class="row g-3 mt-1 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label" for="custom-correct-index">Correct Answer</label>
                                    <select class="form-select" id="custom-correct-index" name="correct_index">
                                        <option value="0">Option 1</option>
                                        <option value="1">Option 2</option>
                                        <option value="2">Option 3</option>
                                        <option value="3">Option 4</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="custom-image">Question Image</label>
                                    <input class="form-control" type="file" id="custom-image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                                </div>
                                <div class="col-md-3">
                                    <div id="remove-image-wrap" class="form-check hidden">
                                        <input class="form-check-input" type="checkbox" id="remove-image" name="remove_image" value="1">
                                        <label class="form-check-label" for="remove-image">Remove current image</label>
                                    </div>
                                </div>
                            </div>

                            <div class="builder-form-actions mt-4">
                                <button id="save-custom-question-btn" type="submit" class="btn btn-primary">Save Question</button>
                                <button id="cancel-custom-edit-btn" type="button" class="btn btn-outline-light hidden">Cancel Edit</button>
                            </div>
                        </form>
                    </div>
                </section>

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
