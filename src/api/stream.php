<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$code = normalize_session_code_value((string) ($_GET['code'] ?? ''));
$active = require_player_auth_for_api($code, $_GET['player_token'] ?? null);
$player_id = (int) $active['player_id'];

if ($code === '' || $player_id <= 0) {
    http_response_code(400);
    exit;
}

$pdo = get_pdo();
$session_id = (int) $active['session_id'];

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Content-Encoding: none');

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level()) {
    ob_end_clean();
}
ob_implicit_flush(true);

set_time_limit(0);
ignore_user_abort(true);

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

sse_prime();

$last_status = null;
$last_phase = null;
$last_question_index = -1;
$tick = 0;

while (true) {
    if (connection_aborted()) {
        break;
    }

    $session = refresh_session_by_id($pdo, $session_id);
    $session = sync_session_runtime_state($pdo, $session);

    $host_player_id = $session['host_player_id'] !== null ? (int) $session['host_player_id'] : 0;
    $players = fetch_session_players($pdo, $session_id, $host_player_id, $player_id);
    $payload = [
        'players' => $players,
        'host_player_id' => $host_player_id > 0 ? $host_player_id : null,
        'viewer_is_host' => !empty($active['is_host']) || $host_player_id === $player_id,
        'current_q_index' => (int) $session['current_q_index'],
        'phase_elapsed_ms' => phase_elapsed_ms($pdo, $session_id),
    ];

    if ($session['status'] === 'waiting') {
        if ($tick % 2 === 0) {
            sse_emit('lobby_update', $payload);
        }
    } elseif ($session['status'] === 'in_progress') {
        if ($last_status !== 'in_progress' || $last_phase !== ($session['round_phase'] ?? '')) {
            if (($session['round_phase'] ?? '') === 'ready') {
                sse_emit('game_start', $payload);
            } elseif (($session['round_phase'] ?? '') === 'question') {
                $question = fetch_session_question_row($pdo, $session_id, (int) $session['current_q_index']);
                if ($question) {
                    sse_emit('question', array_merge(
                        $payload,
                        format_question_payload(
                            $question,
                            (int) $session['current_q_index'],
                            question_elapsed_ms($pdo, $session_id)
                        )
                    ));
                    $last_question_index = (int) $session['current_q_index'];
                }
            } elseif (($session['round_phase'] ?? '') === 'leaderboard') {
                sse_emit('round_leaderboard', array_merge($payload, [
                    'leaderboard' => $players,
                    'question_index' => (int) $session['current_q_index'],
                ]));
            }
        } elseif (($session['round_phase'] ?? '') === 'question' && (int) $session['current_q_index'] > $last_question_index) {
            $question = fetch_session_question_row($pdo, $session_id, (int) $session['current_q_index']);
            if ($question) {
                sse_emit('question', array_merge(
                    $payload,
                    format_question_payload(
                        $question,
                        (int) $session['current_q_index'],
                        question_elapsed_ms($pdo, $session_id)
                    )
                ));
                $last_question_index = (int) $session['current_q_index'];
            }
        } elseif ($tick % 2 === 0) {
            sse_emit('player_progress', $payload);
        }
    } elseif ($session['status'] === 'finished') {
        sse_emit('game_end', array_merge($payload, ['leaderboard' => $players]));
        break;
    }

    $last_status = $session['status'];
    $last_phase = $session['round_phase'] ?? null;

    if ($tick > 0 && $tick % 15 === 0) {
        sse_keepalive();
    }

    $tick++;
    sleep(1);
}
