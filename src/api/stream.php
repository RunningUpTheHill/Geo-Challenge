<?php
// Release PHP session file lock immediately — prevents blocking other requests
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$code      = $route_params['code']      ?? '';
$player_id = (int) ($route_params['player_id'] ?? 0);

if (!$code || !$player_id) {
    http_response_code(400);
    exit;
}

$pdo = get_pdo();

// Verify this player belongs to this session
$verify = $pdo->prepare(
    'SELECT p.id, s.id AS session_id
     FROM players p
     JOIN sessions s ON s.id = p.session_id
     WHERE s.code = ? AND p.id = ?'
);
$verify->execute([$code, $player_id]);
$row = $verify->fetch();
if (!$row) {
    http_response_code(403);
    exit;
}
$session_id = (int) $row['session_id'];

// ── SSE setup ────────────────────────────────────────────────────────
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Content-Encoding: none');

// Disable all output buffering
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

set_time_limit(0);
ignore_user_abort(true);

// ── Event emitters ───────────────────────────────────────────────────

function sse_emit(string $event, array $data): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    @ob_flush();
    flush();
}

function sse_keepalive(): void {
    echo ": keepalive\n\n";
    @ob_flush();
    flush();
}

function sse_prime(): void {
    echo ':' . str_repeat(' ', 2048) . "\n\n";
    @ob_flush();
    flush();
}

function get_question_data(PDO $pdo, int $session_id, int $position): ?array {
    $stmt = $pdo->prepare(
        'SELECT q.id, q.question_text, q.category, q.options
         FROM session_questions sq
         JOIN questions q ON q.id = sq.question_id
         WHERE sq.session_id = ? AND sq.position = ?'
    );
    $stmt->execute([$session_id, $position]);
    $q = $stmt->fetch();
    if (!$q) return null;
    return [
        'index'       => $position,
        'id'          => (int) $q['id'],
        'text'        => $q['question_text'],
        'category'    => $q['category'],
        'options'     => json_decode($q['options'], true),
        'time_limit'  => QUESTION_DURATION_SEC,
        'server_time' => microtime(true),
    ];
}

function get_players_snapshot(PDO $pdo, int $session_id): array {
    $stmt = $pdo->prepare(
        'SELECT id, name, score FROM players WHERE session_id = ? ORDER BY score DESC, total_time_ms ASC'
    );
    $stmt->execute([$session_id]);
    return $stmt->fetchAll();
}

function get_leaderboard(PDO $pdo, int $session_id): array {
    $stmt = $pdo->prepare(
        'SELECT name, score, total_time_ms
         FROM players
         WHERE session_id = ?
         ORDER BY score DESC, total_time_ms ASC'
    );
    $stmt->execute([$session_id]);
    $rows = $stmt->fetchAll();
    $lb   = [];
    foreach ($rows as $i => $r) {
        $lb[] = [
            'rank'          => $i + 1,
            'name'          => $r['name'],
            'score'         => (int) $r['score'],
            'total_time_ms' => (int) $r['total_time_ms'],
        ];
    }
    return $lb;
}

function auto_advance_if_timeout(PDO $pdo, array $session): void {
    $elapsed_stmt = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, question_started_at, NOW()) FROM sessions WHERE id = ?'
    );
    $elapsed_stmt->execute([$session['id']]);
    $elapsed = (int) $elapsed_stmt->fetchColumn();

    if ($elapsed < QUESTION_DURATION_SEC) return;

    $current = (int) $session['current_q_index'];
    $next    = $current + 1;

    if ($next >= (int) $session['num_questions']) {
        $pdo->prepare(
            "UPDATE sessions SET status = 'finished', finished_at = NOW()
             WHERE id = ? AND current_q_index = ? AND status = 'in_progress'"
        )->execute([$session['id'], $current]);

        $pdo->prepare("UPDATE players SET finished_at = NOW() WHERE session_id = ? AND finished_at IS NULL")
            ->execute([$session['id']]);
    } else {
        $pdo->prepare(
            "UPDATE sessions SET current_q_index = ?, question_started_at = NOW()
             WHERE id = ? AND current_q_index = ? AND status = 'in_progress'"
        )->execute([$next, $session['id'], $current]);
    }
}

// ── Main SSE loop ────────────────────────────────────────────────────

$last_status  = null;
$last_q_index = -1;
$tick         = 0;
$last_keepalive = 0;

sse_prime();

while (true) {
    if (connection_aborted()) break;

    $sess_stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ?');
    $sess_stmt->execute([$session_id]);
    $session = $sess_stmt->fetch();
    if (!$session) break;

    $status  = $session['status'];
    $q_index = (int) $session['current_q_index'];

    if ($status === 'waiting') {
        // Push lobby player list every 3 ticks
        if ($tick % 3 === 0) {
            $players = get_players_snapshot($pdo, $session_id);
            sse_emit('lobby_update', [
                'players'        => $players,
                'host_player_id' => $session['host_player_id'] !== null ? (int) $session['host_player_id'] : null,
            ]);
        }
    } elseif ($status === 'in_progress') {
        if ($last_status !== 'in_progress') {
            // Game just started (or player reconnected)
            $pc = $pdo->prepare('SELECT COUNT(*) FROM players WHERE session_id = ?');
            $pc->execute([$session_id]);
            sse_emit('game_start', ['player_count' => (int) $pc->fetchColumn()]);

            $q_data = get_question_data($pdo, $session_id, $q_index);
            if ($q_data) sse_emit('question', $q_data);
            $last_q_index = $q_index;

        } elseif ($q_index > $last_q_index) {
            // Question advanced (all players answered)
            $q_data = get_question_data($pdo, $session_id, $q_index);
            if ($q_data) sse_emit('question', $q_data);
            $last_q_index = $q_index;

        } else {
            // Check 20-second server-side timeout
            auto_advance_if_timeout($pdo, $session);
        }

        // Push live scores every 3 ticks
        if ($tick % 3 === 0) {
            $players = get_players_snapshot($pdo, $session_id);
            sse_emit('player_progress', ['players' => $players]);
        }

    } elseif ($status === 'finished') {
        $leaderboard = get_leaderboard($pdo, $session_id);
        sse_emit('game_end', ['leaderboard' => $leaderboard]);
        break; // Close the SSE connection
    }

    $last_status = $status;

    // Keepalive comment every 15 seconds to prevent proxy/browser timeouts
    if ($tick - $last_keepalive >= 15) {
        sse_keepalive();
        $last_keepalive = $tick;
    }

    $tick++;
    sleep(1);
}
