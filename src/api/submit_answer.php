<?php
require_method('POST');
$body = require_json_body();

$player_id    = (int) ($body['player_id']    ?? 0);
$code         = strtoupper(trim($body['session_code'] ?? ''));
$question_id  = (int) ($body['question_id']  ?? 0);
$raw_chosen_index = $body['chosen_index'] ?? null;
$is_timeout       = !is_numeric($raw_chosen_index);
$chosen_index     = $is_timeout ? 255 : (int) $raw_chosen_index;

if (!$player_id || !$code || !$question_id || (!$is_timeout && ($chosen_index < 0 || $chosen_index > 3))) {
    json_response(['error' => 'Invalid input'], 400);
}

$pdo     = get_pdo();
$session = get_session_by_code($code);

if ($session['status'] !== 'in_progress') {
    json_response(['error' => 'Game is not in progress'], 409);
}

$q_index = (int) $session['current_q_index'];

// Verify submitted question matches the current session question
$sq = $pdo->prepare(
    'SELECT sq.question_id, q.correct_index
     FROM session_questions sq
     JOIN questions q ON q.id = sq.question_id
     WHERE sq.session_id = ? AND sq.position = ?'
);
$sq->execute([$session['id'], $q_index]);
$current_q = $sq->fetch();

if (!$current_q || (int) $current_q['question_id'] !== $question_id) {
    json_response(['error' => 'Question mismatch — already advanced'], 409);
}

$is_correct    = (!$is_timeout && (int) $current_q['correct_index'] === $chosen_index) ? 1 : 0;
$correct_index = (int) $current_q['correct_index'];

// Calculate elapsed time in ms (capped at the question duration)
$time_stmt = $pdo->prepare(
    'SELECT TIMESTAMPDIFF(MICROSECOND, question_started_at, NOW()) FROM sessions WHERE id = ?'
);
$time_stmt->execute([$session['id']]);
$time_ms = (int) min((int) ($time_stmt->fetchColumn() / 1000), QUESTION_DURATION_SEC * 1000);

// Insert answer — INSERT IGNORE prevents double-submit
$ins = $pdo->prepare(
    'INSERT IGNORE INTO answers (player_id, session_id, question_id, chosen_index, is_correct, time_ms)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$ins->execute([$player_id, $session['id'], $question_id, $chosen_index, $is_correct, $time_ms]);

if ($ins->rowCount() > 0) {
    if ($is_correct) {
        $pdo->prepare('UPDATE players SET score = score + 1, total_time_ms = total_time_ms + ? WHERE id = ?')
            ->execute([$time_ms, $player_id]);
    } else {
        $pdo->prepare('UPDATE players SET total_time_ms = total_time_ms + ? WHERE id = ?')
            ->execute([$time_ms, $player_id]);
    }
}

// Check if all players have now answered this question
$tp_stmt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE session_id = ?');
$tp_stmt->execute([$session['id']]);
$total_players = (int) $tp_stmt->fetchColumn();

$ans_stmt = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE session_id = ? AND question_id = ?');
$ans_stmt->execute([$session['id'], $question_id]);
$answered_count = (int) $ans_stmt->fetchColumn();

if ($answered_count >= $total_players) {
    advance_question($pdo, $session, $q_index);
}

json_response([
    'is_correct'    => (bool) $is_correct,
    'correct_index' => $correct_index,
    'time_ms'       => $time_ms,
]);

// ── Helpers ──────────────────────────────────────────────────────────

function advance_question(PDO $pdo, array $session, int $current_index): void {
    $next = $current_index + 1;

    if ($next >= QUESTIONS_PER_GAME) {
        // End the game — optimistic update prevents race conditions
        $pdo->prepare(
            "UPDATE sessions SET status = 'finished', finished_at = NOW()
             WHERE id = ? AND current_q_index = ? AND status = 'in_progress'"
        )->execute([$session['id'], $current_index]);

        $pdo->prepare("UPDATE players SET finished_at = NOW() WHERE session_id = ? AND finished_at IS NULL")
            ->execute([$session['id']]);
    } else {
        // Advance to next question — only if we're still on the expected index
        $pdo->prepare(
            "UPDATE sessions SET current_q_index = ?, question_started_at = NOW()
             WHERE id = ? AND current_q_index = ? AND status = 'in_progress'"
        )->execute([$next, $session['id'], $current_index]);
    }
}
