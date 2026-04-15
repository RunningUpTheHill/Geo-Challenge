<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST'));
if ($method !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$code = normalize_session_code_value((string) ($_GET['code'] ?? ''));
$active = require_player_auth_for_api($code, $_GET['player_token'] ?? null);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = get_pdo();
$session = sync_session_runtime_state($pdo, get_session_by_code($code));
$session_id = (int) $session['id'];
$player_id = (int) $active['player_id'];

if ($method === 'POST'
    && $session['status'] === 'in_progress'
    && ($session['round_phase'] ?? '') === 'ready'
    && (int) ($session['current_q_index'] ?? 0) === 0) {
    if (mark_player_game_ready($pdo, $session_id, $player_id)) {
        bump_session_state_version($pdo, $session_id);
        $session = refresh_session_by_id($pdo, $session_id);
    }

    if (($session['round_phase'] ?? '') === 'ready' && (int) $session['current_q_index'] === 0) {
        $player_count = count_players_in_session($pdo, $session_id);
        $ready_player_count = count_ready_players_in_session($pdo, $session_id);

        if ($player_count > 0 && $ready_player_count > 0 && $ready_player_count >= $player_count) {
            if (arm_initial_ready_countdown($pdo, $session_id)) {
                $session = refresh_session_by_id($pdo, $session_id);
            }
        }
    }

    $session = sync_session_runtime_state($pdo, $session);
}

$server_now_ms = server_now_ms();
$phase_started_at_ms = phase_started_at_ms_for_session($session);
$phase_elapsed_ms = ($session['status'] ?? '') === 'in_progress'
    ? phase_elapsed_ms($pdo, $session_id)
    : 0;
$phase_deadline_ms = phase_deadline_ms_from_elapsed($session, $phase_elapsed_ms, $server_now_ms);

$response = [
    'status' => $session['status'],
    'round_phase' => $session['round_phase'] ?? 'lobby',
    'current_q_index' => (int) ($session['current_q_index'] ?? 0),
    'state_version' => (int) ($session['state_version'] ?? 0),
    'question_duration' => QUESTION_DURATION_SEC,
    'leaderboard_duration' => LEADERBOARD_PHASE_SEC,
    'ready_duration' => READY_PHASE_SEC,
    'server_now_ms' => $server_now_ms,
    'phase_started_at_ms' => $phase_started_at_ms,
    'phase_deadline_ms' => $phase_deadline_ms,
    'phase_elapsed_ms' => $phase_elapsed_ms,
    'player_count' => count_players_in_session($pdo, $session_id),
    'ready_player_count' => count_ready_players_in_session($pdo, $session_id),
    'viewer_ready' => is_player_game_ready($pdo, $session_id, $player_id),
    'ready_countdown_started' => initial_ready_countdown_started($session),
];

json_response($response);
