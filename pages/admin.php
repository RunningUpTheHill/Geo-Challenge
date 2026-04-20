<?php
// ── Handle login / logout ─────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    redirect_to('admin.php');
}

$login_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $submitted_user = (string) ($_POST['username'] ?? '');
    $submitted_pass = (string) ($_POST['password'] ?? '');
    if (hash_equals(ADMIN_USER, $submitted_user) && hash_equals(ADMIN_PASS, $submitted_pass)) {
        $_SESSION['admin_logged_in'] = true;
        redirect_to('admin.php');
    } else {
        $login_error = 'Invalid username or password.';
    }
}

$is_logged_in = !empty($_SESSION['admin_logged_in']);

$admin_url = app_url('admin.php');
$logout_url = $admin_url . '?logout=1';
$app_home_url = app_url();
$add_question_url = app_url('add_question.php');
$delete_question_url = app_url('delete_question.php');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geo Challenge – Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= escape_html(asset_url('public/css/style.css')) ?>">
    <style>
        .page-admin { min-height: 100vh; padding: 2rem 1.5rem; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .question-row td { vertical-align: middle; font-size: .875rem; }
        .option-preview { color: var(--text-muted); font-size: .8rem; }
        .correct-badge { background: var(--success); color: #fff; border-radius: 4px; padding: .1rem .4rem; font-size: .7rem; font-weight: 700; }
        #add-form .form-label { color: var(--text-muted); font-size: .82rem; font-weight: 500; }
        #add-form input, #add-form select, #add-form textarea {
            background: var(--surface2); border-color: var(--border); color: var(--text);
        }
        #add-form input:focus, #add-form select:focus, #add-form textarea:focus {
            background: var(--surface2); border-color: var(--primary); color: var(--text); box-shadow: 0 0 0 .2rem rgba(79,70,229,.25);
        }
        .login-card { max-width: 380px; margin: 10vh auto; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 2.5rem; }
        #status-toast { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; min-width: 260px; }
    </style>
</head>
<body class="page-admin">
<?php if (!$is_logged_in): ?>
<div class="login-card">
    <div class="text-center mb-4">
        <span style="font-size:2rem">🌍</span>
        <h1 class="h4 mt-2" style="color:var(--text)">Admin Login</h1>
    </div>
    <?php if ($login_error !== null): ?>
        <div class="alert alert-danger py-2"><?= escape_html($login_error) ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= escape_html($admin_url) ?>">
        <input type="hidden" name="login" value="1">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" autocomplete="off" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Sign In</button>
    </form>
</div>
<?php else:
$pdo = get_pdo();
$questions = $pdo->query(
    'SELECT id, category, difficulty, question_text, options, correct_index FROM questions ORDER BY id DESC'
)->fetchAll();
?>
<div class="container-lg">
    <div class="admin-header">
        <div>
            <span style="font-size:1.4rem">🌍</span>
            <span class="ms-2 fw-bold fs-5">Geo Challenge — Question Bank</span>
            <span class="badge bg-secondary ms-2"><?= count($questions) ?> questions</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= escape_html($app_home_url) ?>" class="btn btn-secondary btn-small">← Back to App</a>
            <a href="<?= escape_html($logout_url) ?>" class="btn btn-small" style="background:var(--surface2);color:var(--text)">Log Out</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;position:sticky;top:1rem">
                <h2 class="h6 mb-3 text-uppercase" style="color:var(--text-muted);letter-spacing:.5px">Add New Question</h2>
                <form id="add-form">
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <textarea name="question_text" id="q-text" class="form-control" rows="3" required placeholder="e.g. What is the capital of France?"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Category</label>
                            <select name="category" id="q-category" class="form-select">
                                <option value="capitals">Capitals</option>
                                <option value="flags">Flags</option>
                                <option value="languages">Languages</option>
                                <option value="currency">Currency</option>
                                <option value="geography">Geography</option>
                                <option value="government">Government</option>
                                <option value="alliances">Alliances</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Difficulty</label>
                            <select name="difficulty" id="q-difficulty" class="form-select">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>
                    <label class="form-label">Answer Options <span style="color:var(--text-muted);font-weight:400">(select the correct one)</span></label>
                    <div id="options-list" class="mb-3">
                        <?php foreach (['A','B','C','D'] as $i => $letter): ?>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="radio" name="correct_index" id="correct-<?= $i ?>" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> style="width:1.1rem;height:1.1rem;flex-shrink:0;cursor:pointer">
                            <label for="correct-<?= $i ?>" style="width:1.4rem;font-weight:700;color:var(--text-muted);cursor:pointer;margin:0"><?= $letter ?></label>
                            <input type="text" name="option[]" class="form-control option-input" placeholder="Option <?= $letter ?>" required style="flex:1">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="add-btn">Add Question</button>
                    <div id="form-error" class="error-msg mt-2"></div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <h2 class="h6 mb-3 text-uppercase" style="color:var(--text-muted);letter-spacing:.5px">All Questions</h2>
            <div id="question-list">
            <?php if (empty($questions)): ?>
                <p style="color:var(--text-muted)">No questions yet.</p>
            <?php else: ?>
                <table class="table table-striped table-hover results-table w-100" id="questions-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Category</th>
                            <th>Diff</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($questions as $q):
                        $opts = json_decode($q['options'], true) ?: [];
                        $correct_index = (int) $q['correct_index'];
                    ?>
                        <tr class="question-row" id="qrow-<?= (int) $q['id'] ?>">
                            <td style="color:var(--text-muted)"><?= (int) $q['id'] ?></td>
                            <td>
                                <div><?= escape_html($q['question_text']) ?></div>
                                <div class="option-preview mt-1">
                                    <?php foreach ($opts as $i => $opt): ?>
                                        <?php if ($i === $correct_index): ?>
                                            <span class="correct-badge">✓ <?= escape_html((string) $opt) ?></span>
                                        <?php else: ?>
                                            <span class="me-2"><?= escape_html((string) $opt) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><span class="badge" style="background:var(--primary)"><?= escape_html($q['category']) ?></span></td>
                            <td style="color:var(--text-muted)"><?= escape_html($q['difficulty']) ?></td>
                            <td>
                                <button class="btn btn-small delete-btn" style="background:var(--danger);color:#fff" data-id="<?= (int) $q['id'] ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="status-toast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body" id="toast-msg"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ADD_QUESTION_URL = <?= json_encode($add_question_url) ?>;
const DELETE_QUESTION_URL = <?= json_encode($delete_question_url) ?>;

const toastEl = document.getElementById('status-toast');
const toast   = new bootstrap.Toast(toastEl, { delay: 3000 });

function showToast(msg, success) {
    $('#toast-msg').text(msg);
    $(toastEl).removeClass('bg-success bg-danger').addClass(success ? 'bg-success' : 'bg-danger');
    toast.show();
}

$('#add-form').on('submit', function (e) {
    e.preventDefault();
    const opts = $('.option-input').map(function () { return $(this).val().trim(); }).get();
    const correct = $('input[name="correct_index"]:checked').val();
    $('#form-error').text('');
    $('#add-btn').prop('disabled', true).text('Adding…');

    $.ajax({
        url: ADD_QUESTION_URL,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            question_text: $('#q-text').val().trim(),
            category:      $('#q-category').val(),
            difficulty:    $('#q-difficulty').val(),
            options:       opts,
            correct_index: parseInt(correct, 10)
        }),
        success: function () {
            showToast('Question added!', true);
            location.reload();
        },
        error: function (xhr) {
            const err = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to add question.';
            $('#form-error').text(err);
            $('#add-btn').prop('disabled', false).text('Add Question');
        }
    });
});

$(document).on('click', '.delete-btn', function () {
    const id  = $(this).data('id');
    const row = $('#qrow-' + id);

    $.ajax({
        url: DELETE_QUESTION_URL,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ question_id: id }),
        success: function () {
            row.fadeOut(300, function () { $(this).remove(); });
            showToast('Question deleted.', true);
        },
        error: function (xhr) {
            showToast((xhr.responseJSON && xhr.responseJSON.error) || 'Could not delete.', false);
        }
    });
});
</script>
<?php endif; ?>
</body>
</html>
