<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

const STREAM_LOOP_DELAY_US = 125000;
const STREAM_PROGRESS_INTERVAL_TICKS = 16;
const STREAM_KEEPALIVE_INTERVAL_TICKS = 120;

$code = normalize_session_code_value((string) ($_GET['code'] ?? ''));
$active = require_player_auth_for_api($code, $_GET['player_token'] ?? null);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

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

function build_stream_payload(PDO $pdo, array $session, int $player_id, array $active): array {
    $session_id = (int) $session['id'];
    $host_player_id = $session['host_player_id'] !== null ? (int) $session['host_player_id'] : 0;
    $players = fetch_session_players($pdo, $session_id, $host_player_id, $player_id);
    $server_now_ms = server_now_ms();
    $phase_started_at_ms = phase_started_at_ms_for_session($session);
    $phase_elapsed_ms = ($session['status'] ?? '') === 'in_progress'
        ? phase_elapsed_ms($pdo, $session_id)
        : 0;
    $phase_deadline_ms = phase_deadline_ms_from_elapsed($session, $phase_elapsed_ms, $server_now_ms);
    $payload = [
        'status' => $session['status'],
        'round_phase' => $session['round_phase'] ?? 'lobby',
        'state_version' => (int) ($session['state_version'] ?? 0),
        'question_duration' => QUESTION_DURATION_SEC,
        'leaderboard_duration' => LEADERBOARD_PHASE_SEC,
        'ready_duration' => READY_PHASE_SEC,
        'current_q_index' => (int) $session['current_q_index'],
        'server_now_ms' => $server_now_ms,
        'phase_started_at_ms' => $phase_started_at_ms,
        'phase_deadline_ms' => $phase_deadline_ms,
        'phase_elapsed_ms' => $phase_elapsed_ms,
        'host_player_id' => $host_player_id > 0 ? $host_player_id : null,
        'viewer_is_host' => !empty($active['is_host']) || $host_player_id === $player_id,
        'player_count' => count($players),
        'ready_player_count' => count_ready_players_in_session($pdo, $session_id),
        'viewer_ready' => is_player_game_ready($pdo, $session_id, $player_id),
        'ready_countdown_started' => initial_ready_countdown_started($session),
    ];

    $payload['players'] = $players;

    if (($session['round_phase'] ?? '') === 'leaderboard') {
        $payload['viewer_round_result'] = build_viewer_round_result($pdo, $session, $player_id);
    }

    return $payload;
}

sse_prime();

$last_status = null;
$last_phase = null;
$last_question_index = -1;
$last_state_version = -1;
$tick = 0;

while (true) {
    if (connection_aborted()) {
        break;
    }

    $session = refresh_session_stream_state($pdo, $session_id);
    $session = sync_session_runtime_state($pdo, $session);

    $status = (string) ($session['status'] ?? 'waiting');
    $phase = (string) ($session['round_phase'] ?? 'lobby');
    $question_index = (int) ($session['current_q_index'] ?? 0);
    $state_version = (int) ($session['state_version'] ?? 0);
    $phase_changed = $status !== $last_status
        || $phase !== $last_phase
        || $question_index !== $last_question_index;
    $state_changed = $state_version !== $last_state_version;
    $should_emit_progress = $status === 'in_progress'
        && !$phase_changed
        && !$state_changed
        && $tick > 0
        && $tick % STREAM_PROGRESS_INTERVAL_TICKS === 0;

    if ($status === 'waiting') {
        if ($phase_changed || $state_changed) {
            sse_emit('lobby_update', build_stream_payload($pdo, $session, $player_id, $active));
        }
    } elseif ($status === 'in_progress') {
        if ($phase_changed) {
            $payload = build_stream_payload($pdo, $session, $player_id, $active);

            if ($phase === 'ready') {
                sse_emit('game_start', $payload);
            } elseif ($phase === 'question') {
                $question = fetch_session_question_row($pdo, $session_id, $question_index);
                if ($question) {
                    sse_emit('question', array_merge(
                        $payload,
                        format_question_payload(
                            $question,
                            $question_index,
                            question_elapsed_ms($pdo, $session_id)
                        )
                    ));
                }
            } elseif ($phase === 'leaderboard') {
                sse_emit('round_leaderboard', array_merge($payload, [
                    'leaderboard' => $payload['players'],
                    'question_index' => $question_index,
                ]));
            }
        } elseif ($state_changed || $should_emit_progress) {
            sse_emit('player_progress', build_stream_payload($pdo, $session, $player_id, $active));
        }
    } elseif ($status === 'finished') {
        if ($phase_changed || $state_changed) {
            $payload = build_stream_payload($pdo, $session, $player_id, $active);
            sse_emit('game_end', array_merge($payload, ['leaderboard' => $payload['players']]));
        }
        break;
    }

    $last_status = $status;
    $last_phase = $phase;
    $last_question_index = $question_index;
    $last_state_version = $state_version;

    if ($tick > 0 && $tick % STREAM_KEEPALIVE_INTERVAL_TICKS === 0) {
        sse_keepalive();
    }

    $tick++;
    usleep(STREAM_LOOP_DELAY_US);
}
