<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$body = require_json_body();

$code = normalize_session_code_value((string) ($body['session_code'] ?? ''));
$active = require_player_auth_for_api($code, $body['player_token'] ?? null);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$player_id = (int) $active['player_id'];
$question_id = (int) ($body['question_id'] ?? 0);
$raw_chosen_index = $body['chosen_index'] ?? null;
$is_timeout = !is_numeric($raw_chosen_index);
$chosen_index = $is_timeout ? 255 : (int) $raw_chosen_index;

if (!$player_id || !$code || !$question_id || (!$is_timeout && ($chosen_index < 0 || $chosen_index > 3))) {
    json_response(['error' => 'Invalid input'], 400);
}

$pdo = get_pdo();
$session = sync_session_runtime_state($pdo, get_session_by_code($code));

if ($session['status'] !== 'in_progress' || ($session['round_phase'] ?? '') !== 'question') {
    json_response(['error' => 'Question is not currently accepting answers.'], 409);
}

$q_index = (int) $session['current_q_index'];
$current_q = fetch_session_question_row($pdo, (int) $session['id'], $q_index);

if (!$current_q || (int) $current_q['id'] !== $question_id) {
    json_response(['error' => 'Question mismatch - this round has already advanced.'], 409);
}

$is_correct = (!$is_timeout && (int) $current_q['correct_index'] === $chosen_index);
$correct_index = (int) $current_q['correct_index'];
$time_ms = min(question_elapsed_ms($pdo, (int) $session['id']), QUESTION_DURATION_SEC * 1000);
$points_awarded = calculate_answer_points($is_correct, $time_ms);

$ins = $pdo->prepare(
    'INSERT IGNORE INTO answers (player_id, session_id, question_id, chosen_index, is_correct, time_ms, points_awarded)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([
    $player_id,
    (int) $session['id'],
    $question_id,
    $chosen_index,
    $is_correct ? 1 : 0,
    $time_ms,
    $points_awarded,
]);

if ($ins->rowCount() > 0) {
    $pdo->prepare(
        'UPDATE players
         SET score = score + ?,
             total_time_ms = total_time_ms + ?
         WHERE id = ?'
    )->execute([$points_awarded, $time_ms, $player_id]);

    $answered_count = count_answers_for_question($pdo, (int) $session['id'], $question_id);
    $total_players = count_players_in_session($pdo, (int) $session['id']);

    if ($answered_count >= $total_players) {
        set_session_leaderboard_phase($pdo, $session);
    } else {
        bump_session_state_version($pdo, (int) $session['id']);
    }
}

json_response([
    'is_correct' => $is_correct,
    'correct_index' => $correct_index,
    'time_ms' => $time_ms,
    'points_awarded' => $points_awarded,
]);
