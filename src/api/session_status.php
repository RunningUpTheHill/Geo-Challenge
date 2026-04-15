<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('GET');

$code = normalize_session_code_value((string) ($_GET['code'] ?? ''));
$active = require_player_auth_for_api($code, $_GET['player_token'] ?? null);
$pdo = get_pdo();
$session = sync_session_runtime_state($pdo, get_session_by_code($code));

$host_player_id = $session['host_player_id'] !== null ? (int) $session['host_player_id'] : 0;
$viewer_player_id = (int) $active['player_id'];
$viewer_is_host = !empty($active['is_host']) || $host_player_id === $viewer_player_id;
$players = fetch_session_players($pdo, (int) $session['id'], $host_player_id, $viewer_player_id);
$player_count = count($players);
$phase_elapsed_ms = 0;

$current_question = null;
$answered_count = 0;
$leaderboard = null;

if ($session['status'] === 'in_progress') {
    $phase_elapsed_ms = phase_elapsed_ms($pdo, (int) $session['id']);

    if (($session['round_phase'] ?? '') === 'question') {
        $question_row = fetch_session_question_row($pdo, (int) $session['id'], (int) $session['current_q_index']);
        if ($question_row) {
            $current_question = format_question_payload(
                $question_row,
                (int) $session['current_q_index'],
                question_elapsed_ms($pdo, (int) $session['id'])
            );
            $answered_count = count_answers_for_question($pdo, (int) $session['id'], (int) $question_row['id']);
        }
    }

    if (($session['round_phase'] ?? '') === 'leaderboard') {
        $leaderboard = $players;
    }
}

if ($session['status'] === 'finished') {
    $leaderboard = $players;
}

json_response([
    'status' => $session['status'],
    'round_phase' => $session['round_phase'] ?? 'lobby',
    'current_q_index' => (int) $session['current_q_index'],
    'num_questions' => (int) $session['num_questions'],
    'question_duration' => QUESTION_DURATION_SEC,
    'leaderboard_duration' => LEADERBOARD_PHASE_SEC,
    'ready_duration' => READY_PHASE_SEC,
    'phase_elapsed_ms' => $phase_elapsed_ms,
    'host_player_id' => $host_player_id > 0 ? $host_player_id : null,
    'viewer_player_id' => $viewer_player_id,
    'viewer_is_host' => $viewer_is_host,
    'player_count' => $player_count,
    'answered_count' => $answered_count,
    'players' => $players,
    'leaderboard' => $leaderboard,
    'current_question' => $current_question,
]);
