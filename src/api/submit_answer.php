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
$session_id = (int) $session['id'];
$phase = (string) ($session['round_phase'] ?? '');
$q_index = (int) $session['current_q_index'];
$current_q = fetch_session_question_row($pdo, $session_id, $q_index);
$existing_answer = fetch_player_answer_row($pdo, $session_id, $question_id, $player_id);

if ($existing_answer && (int) $existing_answer['chosen_index'] !== 255) {
    json_response([
        'is_correct' => !empty($existing_answer['is_correct']),
        'correct_index' => $current_q ? (int) $current_q['correct_index'] : 0,
        'time_ms' => (int) $existing_answer['time_ms'],
        'points_awarded' => (int) $existing_answer['points_awarded'],
    ]);
}

if (!$current_q || (int) $current_q['id'] !== $question_id) {
    json_response(['error' => 'Question mismatch - this round has already advanced.'], 409);
}

$leaderboard_grace = $session['status'] === 'in_progress'
    && $phase === 'leaderboard'
    && phase_elapsed_ms($pdo, $session_id) <= ANSWER_SUBMIT_GRACE_MS;

if ($session['status'] !== 'in_progress' || ($phase !== 'question' && !$leaderboard_grace)) {
    json_response(['error' => 'Question is not currently accepting answers.'], 409);
}

$is_correct = (!$is_timeout && (int) $current_q['correct_index'] === $chosen_index);
$correct_index = (int) $current_q['correct_index'];
$time_ms = min(question_elapsed_ms($pdo, $session_id), QUESTION_DURATION_SEC * 1000);
$points_awarded = calculate_answer_points($is_correct, $time_ms);

$answer_saved = false;
$score_delta = 0;
$time_delta = 0;

try {
    $pdo->beginTransaction();

    if ($existing_answer === null) {
        $ins = $pdo->prepare(
            'INSERT INTO answers (player_id, session_id, question_id, chosen_index, is_correct, time_ms, points_awarded)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $player_id,
            $session_id,
            $question_id,
            $chosen_index,
            $is_correct ? 1 : 0,
            $time_ms,
            $points_awarded,
        ]);

        $answer_saved = $ins->rowCount() > 0;
        if ($answer_saved) {
            $score_delta = $points_awarded;
            $time_delta = $time_ms;
        }
    } elseif ((int) $existing_answer['chosen_index'] === 255 && !$is_timeout) {
        $update = $pdo->prepare(
            'UPDATE answers
             SET chosen_index = ?,
                 is_correct = ?,
                 time_ms = ?,
                 points_awarded = ?
             WHERE player_id = ?
               AND session_id = ?
               AND question_id = ?
               AND chosen_index = 255'
        );
        $update->execute([
            $chosen_index,
            $is_correct ? 1 : 0,
            $time_ms,
            $points_awarded,
            $player_id,
            $session_id,
            $question_id,
        ]);

        $answer_saved = $update->rowCount() > 0;
        if ($answer_saved) {
            $score_delta = $points_awarded - (int) $existing_answer['points_awarded'];
            $time_delta = $time_ms - (int) $existing_answer['time_ms'];
        }
    }

    if ($answer_saved && ($score_delta !== 0 || $time_delta !== 0)) {
        $pdo->prepare(
            'UPDATE players
             SET score = score + ?,
                 total_time_ms = total_time_ms + ?
             WHERE id = ?'
        )->execute([$score_delta, $time_delta, $player_id]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(['error' => 'Failed to record answer.'], 500);
}

if ($answer_saved) {
    $answered_count = count_answers_for_question($pdo, $session_id, $question_id);
    $total_players = count_players_in_session($pdo, $session_id);

    if ($phase === 'question' && $answered_count >= $total_players) {
        set_session_leaderboard_phase($pdo, $session);
    } else {
        bump_session_state_version($pdo, $session_id);
    }
}

json_response([
    'is_correct' => $is_correct,
    'correct_index' => $correct_index,
    'time_ms' => $time_ms,
    'points_awarded' => $points_awarded,
]);
