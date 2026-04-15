<?php
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($data);
    exit;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        json_response(['error' => 'Method not allowed'], 405);
    }
}

function require_json_body(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        json_response(['error' => 'Invalid JSON body'], 400);
    }
    return $body;
}

function normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function string_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return substr($haystack, 0, strlen($needle)) === $needle;
}

function app_script_path(): string
{
    return normalize_path($_SERVER['SCRIPT_NAME'] ?? '/index.php');
}

function app_base_path(): string
{
    static $base_path = null;

    if ($base_path !== null) {
        return $base_path;
    }

    $dir = dirname(app_script_path());
    if ($dir === DIRECTORY_SEPARATOR || $dir === '.' || $dir === '/') {
        $base_path = '';
    } else {
        $base_path = rtrim(normalize_path($dir), '/');
    }

    return $base_path;
}

function request_path(): string
{
    $request_path = normalize_path(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $script_path = app_script_path();
    $base_path = app_base_path();

    if ($request_path === $script_path) {
        return '';
    }
    if ($base_path !== '' && $request_path === $base_path) {
        return '';
    }
    if ($base_path !== '' && string_starts_with($request_path, $base_path . '/')) {
        return trim(substr($request_path, strlen($base_path)), '/');
    }

    return trim($request_path, '/');
}

function app_url(string $path = ''): string
{
    $base_path = app_base_path();
    $path = trim($path, '/');

    if ($path === '') {
        return $base_path === '' ? '/' : $base_path . '/';
    }

    return ($base_path === '' ? '' : $base_path) . '/' . $path;
}

function asset_url(string $path): string
{
    $relative_path = trim($path, '/');
    $absolute_path = dirname(__DIR__) . '/' . $relative_path;
    $url = app_url($relative_path);

    if (is_file($absolute_path)) {
        return $url . '?v=' . rawurlencode((string) filemtime($absolute_path));
    }

    return $url;
}

function redirect_to(string $path = '', int $status_code = 302): void
{
    header('Location: ' . app_url($path), true, $status_code);
    exit;
}

function set_flash_message(string $message, string $type = 'warning'): void
{
    $_SESSION['geo_flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function pull_flash_message(): ?array
{
    if (!isset($_SESSION['geo_flash']) || !is_array($_SESSION['geo_flash'])) {
        return null;
    }

    $flash = $_SESSION['geo_flash'];
    unset($_SESSION['geo_flash']);
    return $flash;
}

function normalize_session_code_value(string $code): string
{
    return strtoupper(trim($code));
}

function player_auth_error_message(): string
{
    return 'This tab is not signed in for that game. Return to the home page and join again.';
}

function player_auth_error_response(): void
{
    json_response(['error' => player_auth_error_message()], 403);
}

function generate_player_token(): string
{
    return bin2hex(random_bytes(32));
}

function normalize_player_token(?string $token): string
{
    return trim((string) $token);
}

function find_player_by_session_token(PDO $pdo, string $code, string $player_token): ?array
{
    if ($code === '' || $player_token === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT
            p.id AS player_id,
            p.name AS player_name,
            p.session_id,
            p.auth_token,
            s.code AS session_code,
            s.host_player_id
         FROM players p
         JOIN sessions s ON s.id = p.session_id
         WHERE s.code = ? AND p.auth_token = ?
         LIMIT 1'
    );
    $stmt->execute([$code, $player_token]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $row['player_id'] = (int) $row['player_id'];
    $row['session_id'] = (int) $row['session_id'];
    $row['host_player_id'] = $row['host_player_id'] !== null ? (int) $row['host_player_id'] : null;
    $row['is_host'] = $row['host_player_id'] !== null && $row['host_player_id'] === $row['player_id'];

    return $row;
}

function require_player_auth_for_api(string $code, ?string $player_token = null): array
{
    $normalized_code = normalize_session_code_value($code);
    $normalized_token = normalize_player_token($player_token);
    if ($normalized_code === '' || $normalized_token === '') {
        player_auth_error_response();
    }

    $active = find_player_by_session_token(get_pdo(), $normalized_code, $normalized_token);
    if ($active === null) {
        player_auth_error_response();
    }

    return $active;
}

function app_state(array $extra = []): array
{
    return array_merge([
        'basePath' => app_base_path(),
        'urls' => [
            'home' => app_url(''),
            'sessionCreate' => app_url('create_session.php'),
            'sessionJoin' => app_url('join_session.php'),
            'sessionStart' => app_url('start_game.php'),
            'sessionEnd' => app_url('end_game.php'),
            'answerSubmit' => app_url('submit_answer.php'),
        ],
    ], $extra);
}

function generate_session_code(): string
{
    // Unambiguous characters only (no 0/O, 1/I/L)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pdo = get_pdo();
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM sessions WHERE code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

function get_session_by_code(string $code): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM sessions WHERE code = ?');
    $stmt->execute([normalize_session_code_value($code)]);
    $session = $stmt->fetch();
    if (!$session) {
        json_response(['error' => 'Session not found'], 404);
    }
    return $session;
}

function render_player_bootstrap_redirect(
    string $code,
    int $player_id,
    string $player_name,
    string $player_token,
    string $target_path = 'lobby.php'
): void {
    $payload = [
        'code' => normalize_session_code_value($code),
        'playerId' => $player_id,
        'playerName' => $player_name,
        'playerToken' => $player_token,
    ];
    $redirect_url = app_url($target_path . '?code=' . urlencode($payload['code']));

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?= escape_html($redirect_url) ?>">
    <title>Redirecting...</title>
</head>
<body>
<script>
    (function bootstrapPlayerAuth() {
        var payload = <?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var storageKey = 'geoChallenge.player.' + String(payload.code || '').toUpperCase();
        try {
            window.sessionStorage.setItem(storageKey, JSON.stringify(payload));
        } catch (error) {
            // Ignore storage errors and fall through to the redirect.
        }
        window.location.replace(<?= json_encode($redirect_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    }());
</script>
<noscript>
    <p><a href="<?= escape_html($redirect_url) ?>">Continue to the game lobby</a></p>
</noscript>
</body>
</html>
<?php
    exit;
}

function refresh_session_by_id(PDO $pdo, int $session_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ?');
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if (!$session) {
        throw new RuntimeException('Session not found.');
    }

    return $session;
}

function refresh_session_stream_state(PDO $pdo, int $session_id): array
{
    $stmt = $pdo->prepare(
        'SELECT
            id,
            host_player_id,
            status,
            round_phase,
            current_q_index,
            num_questions,
            phase_started_at,
            state_version
         FROM sessions
         WHERE id = ?'
    );
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if (!$session) {
        throw new RuntimeException('Session not found.');
    }

    $session['id'] = (int) $session['id'];
    $session['host_player_id'] = $session['host_player_id'] !== null ? (int) $session['host_player_id'] : null;
    $session['current_q_index'] = (int) $session['current_q_index'];
    $session['num_questions'] = (int) $session['num_questions'];
    $session['state_version'] = (int) $session['state_version'];

    return $session;
}

function bump_session_state_version(PDO $pdo, int $session_id): void
{
    $stmt = $pdo->prepare(
        "UPDATE sessions
         SET state_version = state_version + 1,
             state_changed_at = NOW(6)
         WHERE id = ?"
    );
    $stmt->execute([$session_id]);
}

function fetch_session_question_row(PDO $pdo, int $session_id, int $position): ?array
{
    $stmt = $pdo->prepare(
        'SELECT q.id, q.question_text, q.category, q.options, q.correct_index
         FROM session_questions sq
         JOIN questions q ON q.id = sq.question_id
         WHERE sq.session_id = ? AND sq.position = ?'
    );
    $stmt->execute([$session_id, $position]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetch_session_question_plan(PDO $pdo, int $session_id): array
{
    $stmt = $pdo->prepare(
        'SELECT
            sq.position,
            q.id,
            q.question_text,
            q.category,
            q.options
         FROM session_questions sq
         JOIN questions q ON q.id = sq.question_id
         WHERE sq.session_id = ?
         ORDER BY sq.position ASC'
    );
    $stmt->execute([$session_id]);

    $plan = [];
    foreach ($stmt->fetchAll() as $row) {
        $position = (int) $row['position'];
        $plan[] = format_question_payload($row, $position, 0);
    }

    return $plan;
}

function question_options_from_row(array $question_row): array
{
    $options = json_decode($question_row['options'], true);
    return is_array($options) ? array_values($options) : [];
}

function question_option_text_at_index(array $question_row, ?int $option_index): ?string
{
    if ($option_index === null || $option_index < 0) {
        return null;
    }

    $options = question_options_from_row($question_row);
    return array_key_exists($option_index, $options) ? (string) $options[$option_index] : null;
}

function format_question_payload(array $question_row, int $position, int $elapsed_ms = 0): array
{
    return [
        'index' => $position,
        'id' => (int) $question_row['id'],
        'text' => $question_row['question_text'],
        'category' => $question_row['category'],
        'options' => question_options_from_row($question_row),
        'time_limit' => QUESTION_DURATION_SEC,
        'server_time' => microtime(true),
        'elapsed_ms' => max(0, $elapsed_ms),
    ];
}

function calculate_answer_points(bool $is_correct, int $time_ms): int
{
    if (!$is_correct) {
        return 0;
    }

    $max_time_ms = QUESTION_DURATION_SEC * 1000;
    $capped_time = max(0, min($time_ms, $max_time_ms));
    $bonus_range = MAX_POINTS_PER_QUESTION - MIN_POINTS_FOR_CORRECT;
    $speed_ratio = ($max_time_ms - $capped_time) / $max_time_ms;

    return MIN_POINTS_FOR_CORRECT + (int) round($bonus_range * $speed_ratio);
}

function fetch_session_players(PDO $pdo, int $session_id, int $host_player_id = 0, ?int $viewer_player_id = null): array
{
    $stmt = $pdo->prepare(
        'SELECT
            p.id,
            p.name,
            p.score,
            p.total_time_ms,
            p.finished_at,
            COALESCE(SUM(a.is_correct), 0) AS correct_answers
         FROM players p
         LEFT JOIN answers a
            ON a.player_id = p.id
           AND a.session_id = p.session_id
         WHERE p.session_id = ?
         GROUP BY p.id, p.name, p.score, p.total_time_ms, p.finished_at
         ORDER BY p.score DESC, correct_answers DESC, p.total_time_ms ASC, p.id ASC'
    );
    $stmt->execute([$session_id]);

    $players = [];
    foreach ($stmt->fetchAll() as $index => $player) {
        $player_id = (int) $player['id'];
        $players[] = [
            'rank' => $index + 1,
            'id' => $player_id,
            'name' => $player['name'],
            'score' => (int) $player['score'],
            'correct_answers' => (int) $player['correct_answers'],
            'total_time_ms' => (int) $player['total_time_ms'],
            'finished_at' => $player['finished_at'],
            'is_host' => $host_player_id > 0 && $player_id === $host_player_id,
            'is_you' => $viewer_player_id !== null && $player_id === $viewer_player_id,
        ];
    }

    return $players;
}

function count_answers_for_question(PDO $pdo, int $session_id, int $question_id): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE session_id = ? AND question_id = ?');
    $stmt->execute([$session_id, $question_id]);
    return (int) $stmt->fetchColumn();
}

function fetch_player_answer_row(PDO $pdo, int $session_id, int $question_id, int $player_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT chosen_index, is_correct, time_ms, points_awarded
         FROM answers
         WHERE session_id = ? AND question_id = ? AND player_id = ?
         LIMIT 1'
    );
    $stmt->execute([$session_id, $question_id, $player_id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function build_viewer_round_result(PDO $pdo, array $session, int $viewer_player_id): ?array
{
    $session_id = (int) ($session['id'] ?? 0);
    if ($session_id <= 0 || $viewer_player_id <= 0) {
        return null;
    }

    $question_index = (int) ($session['current_q_index'] ?? 0);
    $question_row = fetch_session_question_row($pdo, $session_id, $question_index);
    if (!$question_row) {
        return null;
    }

    $correct_index = (int) $question_row['correct_index'];
    $correct_text = question_option_text_at_index($question_row, $correct_index);
    $answer_row = fetch_player_answer_row($pdo, $session_id, (int) $question_row['id'], $viewer_player_id);

    if (!$answer_row) {
        return [
            'question_index' => $question_index,
            'chosen_index' => null,
            'chosen_text' => null,
            'correct_index' => $correct_index,
            'correct_text' => $correct_text,
            'is_correct' => false,
            'is_timeout' => false,
            'points_awarded' => 0,
            'time_ms' => 0,
        ];
    }

    $raw_chosen_index = (int) $answer_row['chosen_index'];
    $is_timeout = $raw_chosen_index === 255;
    $chosen_index = $is_timeout ? null : $raw_chosen_index;

    return [
        'question_index' => $question_index,
        'chosen_index' => $chosen_index,
        'chosen_text' => question_option_text_at_index($question_row, $chosen_index),
        'correct_index' => $correct_index,
        'correct_text' => $correct_text,
        'is_correct' => !empty($answer_row['is_correct']),
        'is_timeout' => $is_timeout,
        'points_awarded' => (int) $answer_row['points_awarded'],
        'time_ms' => (int) $answer_row['time_ms'],
    ];
}

function count_players_in_session(PDO $pdo, int $session_id): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE session_id = ?');
    $stmt->execute([$session_id]);
    return (int) $stmt->fetchColumn();
}

function count_ready_players_in_session(PDO $pdo, int $session_id): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM players WHERE session_id = ? AND game_ready_at IS NOT NULL'
    );
    $stmt->execute([$session_id]);
    return (int) $stmt->fetchColumn();
}

function is_player_game_ready(PDO $pdo, int $session_id, int $player_id): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM players
         WHERE session_id = ?
           AND id = ?
           AND game_ready_at IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([$session_id, $player_id]);
    return $stmt->fetchColumn() !== false;
}

function mark_player_game_ready(PDO $pdo, int $session_id, int $player_id): bool
{
    $stmt = $pdo->prepare(
        'UPDATE players
         SET game_ready_at = NOW(6)
         WHERE session_id = ? AND id = ? AND game_ready_at IS NULL'
    );
    $stmt->execute([$session_id, $player_id]);

    return $stmt->rowCount() > 0;
}

function initial_ready_countdown_started(array $session): bool
{
    return ($session['round_phase'] ?? '') === 'ready'
        && (int) ($session['current_q_index'] ?? 0) === 0
        && !empty($session['phase_started_at']);
}

function arm_initial_ready_countdown(PDO $pdo, int $session_id): bool
{
    $stmt = $pdo->prepare(
        "UPDATE sessions
         SET phase_started_at = TIMESTAMPADD(MICROSECOND, ?, NOW(6))
         WHERE id = ?
           AND status = 'in_progress'
           AND round_phase = 'ready'
           AND current_q_index = 0
           AND phase_started_at IS NULL"
    );
    $stmt->execute([INITIAL_READY_SYNC_DELAY_MS * 1000, $session_id]);

    if ($stmt->rowCount() > 0) {
        bump_session_state_version($pdo, $session_id);
        return true;
    }

    return false;
}

function question_elapsed_ms(PDO $pdo, int $session_id): int
{
    $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(MICROSECOND, question_started_at, NOW(6)) FROM sessions WHERE id = ?');
    $stmt->execute([$session_id]);
    return max(0, (int) ($stmt->fetchColumn() / 1000));
}

function server_now_ms(): int
{
    return (int) round(microtime(true) * 1000);
}

function phase_duration_ms_for_session(array $session): int
{
    if (($session['status'] ?? '') !== 'in_progress') {
        return 0;
    }

    if (empty($session['phase_started_at'])) {
        return 0;
    }

    $phase = (string) ($session['round_phase'] ?? '');
    if ($phase === 'ready') {
        return READY_PHASE_SEC * 1000;
    }
    if ($phase === 'question') {
        return QUESTION_DURATION_SEC * 1000;
    }
    if ($phase === 'leaderboard') {
        return LEADERBOARD_PHASE_SEC * 1000;
    }

    return 0;
}

function phase_deadline_ms_from_elapsed(array $session, int $phase_elapsed_ms, int $server_now_ms): int
{
    $phase_duration_ms = phase_duration_ms_for_session($session);
    if ($phase_duration_ms <= 0) {
        return 0;
    }

    return $server_now_ms + max(0, $phase_duration_ms - max(0, $phase_elapsed_ms));
}

function mysql_datetime_to_epoch_ms(?string $value): int
{
    $normalized_value = trim((string) $value);
    if ($normalized_value === '') {
        return 0;
    }

    $timezone = new DateTimeZone(date_default_timezone_get());
    $formats = ['Y-m-d H:i:s.u', 'Y-m-d H:i:s'];

    foreach ($formats as $format) {
        $datetime = DateTimeImmutable::createFromFormat($format, $normalized_value, $timezone);
        if ($datetime instanceof DateTimeImmutable) {
            return ((int) $datetime->format('U') * 1000)
                + (int) floor(((int) $datetime->format('u')) / 1000);
        }
    }

    try {
        $datetime = new DateTimeImmutable($normalized_value, $timezone);
        return ((int) $datetime->format('U') * 1000)
            + (int) floor(((int) $datetime->format('u')) / 1000);
    } catch (Throwable $e) {
        return 0;
    }
}

function phase_started_at_ms_for_session(array $session): int
{
    return mysql_datetime_to_epoch_ms($session['phase_started_at'] ?? null);
}

function phase_deadline_ms_for_session(array $session): int
{
    $phase_started_at_ms = phase_started_at_ms_for_session($session);
    $phase_duration_ms = phase_duration_ms_for_session($session);
    if ($phase_started_at_ms <= 0 || $phase_duration_ms <= 0) {
        return 0;
    }

    return $phase_started_at_ms + $phase_duration_ms;
}

function phase_elapsed_seconds(PDO $pdo, int $session_id): int
{
    $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, phase_started_at, NOW(6)) FROM sessions WHERE id = ?');
    $stmt->execute([$session_id]);
    return max(0, (int) $stmt->fetchColumn());
}

function phase_elapsed_ms(PDO $pdo, int $session_id): int
{
    $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(MICROSECOND, phase_started_at, NOW(6)) FROM sessions WHERE id = ?');
    $stmt->execute([$session_id]);
    return max(0, (int) ($stmt->fetchColumn() / 1000));
}

function add_timeout_answers_for_missing_players(PDO $pdo, array $session, int $question_id): void
{
    $stmt = $pdo->prepare(
        'SELECT p.id
         FROM players p
         LEFT JOIN answers a
           ON a.player_id = p.id
          AND a.session_id = p.session_id
          AND a.question_id = ?
         WHERE p.session_id = ?
           AND a.id IS NULL'
    );
    $stmt->execute([$question_id, $session['id']]);

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO answers (player_id, session_id, question_id, chosen_index, is_correct, time_ms, points_awarded)
         VALUES (?, ?, ?, ?, 0, ?, 0)'
    );
    $update = $pdo->prepare('UPDATE players SET total_time_ms = total_time_ms + ? WHERE id = ?');
    $timeout_ms = QUESTION_DURATION_SEC * 1000;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $player_id) {
        $insert->execute([(int) $player_id, (int) $session['id'], $question_id, 255, $timeout_ms]);
        $update->execute([$timeout_ms, (int) $player_id]);
    }
}

function set_session_question_phase(
    PDO $pdo,
    int $session_id,
    int $question_index,
    string $expected_phase,
    int $expected_question_index
): bool
{
    $stmt = $pdo->prepare(
        "UPDATE sessions
         SET current_q_index = ?,
             round_phase = 'question',
             phase_started_at = NOW(6),
             question_started_at = NOW(6)
         WHERE id = ?
           AND status = 'in_progress'
           AND round_phase = ?
           AND current_q_index = ?"
    );
    $stmt->execute([$question_index, $session_id, $expected_phase, $expected_question_index]);

    if ($stmt->rowCount() > 0) {
        bump_session_state_version($pdo, $session_id);
        return true;
    }

    return false;
}

function set_session_leaderboard_phase(PDO $pdo, array $session): void
{
    if (($session['round_phase'] ?? '') !== 'question') {
        return;
    }

    $question = fetch_session_question_row($pdo, (int) $session['id'], (int) $session['current_q_index']);
    if (!$question) {
        return;
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "UPDATE sessions
             SET round_phase = 'leaderboard',
                 phase_started_at = NOW(6)
             WHERE id = ?
               AND status = 'in_progress'
               AND round_phase = 'question'
               AND current_q_index = ?"
        );
        $stmt->execute([
            (int) $session['id'],
            (int) $session['current_q_index'],
        ]);

        if ($stmt->rowCount() > 0) {
            add_timeout_answers_for_missing_players($pdo, $session, (int) $question['id']);
            bump_session_state_version($pdo, (int) $session['id']);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function finish_session(PDO $pdo, int $session_id): void
{
    $stmt = $pdo->prepare(
        "UPDATE sessions
         SET status = 'finished',
             finished_at = NOW(6)
         WHERE id = ? AND status <> 'finished'"
    );
    $stmt->execute([$session_id]);

    $pdo->prepare(
        'UPDATE players
         SET finished_at = NOW(6)
         WHERE session_id = ? AND finished_at IS NULL'
    )->execute([$session_id]);

    if ($stmt->rowCount() > 0) {
        bump_session_state_version($pdo, $session_id);
    }
}

function sync_session_runtime_state(PDO $pdo, array $session): array
{
    if (($session['status'] ?? '') !== 'in_progress') {
        return $session;
    }

    $phase = $session['round_phase'] ?? 'question';

    if ($phase === 'ready') {
        if ((int) $session['current_q_index'] === 0) {
            $player_count = count_players_in_session($pdo, (int) $session['id']);
            $ready_player_count = count_ready_players_in_session($pdo, (int) $session['id']);
            $countdown_started = initial_ready_countdown_started($session);

            if (!$countdown_started) {
                if ($player_count <= 0 || $ready_player_count <= 0 || $ready_player_count < $player_count) {
                    return $session;
                }

                arm_initial_ready_countdown($pdo, (int) $session['id']);
                return refresh_session_by_id($pdo, (int) $session['id']);
            }
        }

        if (phase_elapsed_ms($pdo, (int) $session['id']) >= READY_PHASE_SEC * 1000) {
            set_session_question_phase(
                $pdo,
                (int) $session['id'],
                (int) $session['current_q_index'],
                'ready',
                (int) $session['current_q_index']
            );
            return refresh_session_by_id($pdo, (int) $session['id']);
        }
        return $session;
    }

    if ($phase === 'question') {
        $question = fetch_session_question_row($pdo, (int) $session['id'], (int) $session['current_q_index']);
        if (!$question) {
            finish_session($pdo, (int) $session['id']);
            return refresh_session_by_id($pdo, (int) $session['id']);
        }

        $answered_count = count_answers_for_question($pdo, (int) $session['id'], (int) $question['id']);
        $player_count = count_players_in_session($pdo, (int) $session['id']);
        $elapsed_ms = question_elapsed_ms($pdo, (int) $session['id']);

        if ($answered_count >= $player_count || $elapsed_ms >= QUESTION_DURATION_SEC * 1000) {
            set_session_leaderboard_phase($pdo, $session);
            return refresh_session_by_id($pdo, (int) $session['id']);
        }

        return $session;
    }

    if ($phase === 'leaderboard') {
        if (phase_elapsed_ms($pdo, (int) $session['id']) >= LEADERBOARD_PHASE_SEC * 1000) {
            $next_question_index = (int) $session['current_q_index'] + 1;

            if ($next_question_index >= (int) $session['num_questions']) {
                finish_session($pdo, (int) $session['id']);
            } else {
                set_session_question_phase(
                    $pdo,
                    (int) $session['id'],
                    $next_question_index,
                    'leaderboard',
                    (int) $session['current_q_index']
                );
            }

            return refresh_session_by_id($pdo, (int) $session['id']);
        }
    }

    return $session;
}

function escape_html(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
