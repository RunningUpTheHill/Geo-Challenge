<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('GET');

$code = normalize_session_code_value((string) ($_GET['code'] ?? ''));
$active = require_player_auth_for_api($code, $_GET['player_token'] ?? null);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = get_pdo();
$session = sync_session_runtime_state($pdo, get_session_by_code($code));

$host_player_id = $session['host_player_id'] !== null ? (int) $session['host_player_id'] : 0;
$viewer_player_id = (int) $active['player_id'];
$viewer_is_host = !empty($active['is_host']) || $host_player_id === $viewer_player_id;
$players = fetch_session_players($pdo, (int) $session['id'], $host_player_id, $viewer_player_id);
$player_count = count($players);
$ready_player_count = count_ready_players_in_session($pdo, (int) $session['id']);
$viewer_ready = is_player_game_ready($pdo, (int) $session['id'], $viewer_player_id);
$phase_elapsed_ms = 0;
$server_now_ms = server_now_ms();
$phase_started_at_ms = phase_started_at_ms_for_session($session);
$phase_deadline_ms = 0;

$current_question = null;
$answered_count = 0;
$leaderboard = null;
$viewer_round_result = null;

if ($session['status'] === 'in_progress') {
    $phase_elapsed_ms = phase_elapsed_ms($pdo, (int) $session['id']);
    $phase_started_at_ms = phase_started_at_ms_for_session($session);
    $phase_deadline_ms = phase_deadline_ms_from_elapsed($session, $phase_elapsed_ms, $server_now_ms);

    if (($session['round_phase'] ?? '') === 'question') {
        $question_row = fetch_session_question_row($pdo, (int) $session['id'], (int) $session['current_q_index']);
        if ($question_row) {
            $current_question = format_question_payload(
                $pdo,
                $question_row,
                (int) $session['current_q_index'],
                question_elapsed_ms($pdo, (int) $session['id'])
            );
            $answered_count = count_answers_for_question($pdo, (int) $session['id'], (int) $question_row['id']);
        }
    }

    if (($session['round_phase'] ?? '') === 'leaderboard') {
        $leaderboard = $players;
        $viewer_round_result = build_viewer_round_result($pdo, $session, $viewer_player_id);
    }
}

if ($session['status'] === 'finished') {
    $leaderboard = $players;
}

json_response([
    'status' => $session['status'],
    'round_phase' => $session['round_phase'] ?? 'lobby',
    'current_q_index' => (int) $session['current_q_index'],
    'state_version' => (int) ($session['state_version'] ?? 0),
    'num_questions' => (int) $session['num_questions'],
    'question_duration' => QUESTION_DURATION_SEC,
    'leaderboard_duration' => LEADERBOARD_PHASE_SEC,
    'ready_duration' => READY_PHASE_SEC,
    'server_now_ms' => $server_now_ms,
    'phase_started_at_ms' => $phase_started_at_ms,
    'phase_deadline_ms' => $phase_deadline_ms,
    'phase_elapsed_ms' => $phase_elapsed_ms,
    'host_player_id' => $host_player_id > 0 ? $host_player_id : null,
    'viewer_player_id' => $viewer_player_id,
    'viewer_is_host' => $viewer_is_host,
    'player_count' => $player_count,
    'ready_player_count' => $ready_player_count,
    'viewer_ready' => $viewer_ready,
    'ready_countdown_started' => initial_ready_countdown_started($session),
    'answered_count' => $answered_count,
    'players' => $players,
    'leaderboard' => $leaderboard,
    'current_question' => $current_question,
    'viewer_round_result' => $viewer_round_result,
]);
