<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$body = require_json_body();

$code = normalize_session_code_value((string) ($body['session_code'] ?? ''));
[$pdo, $session] = require_host_session_for_builder($code, $body['player_token'] ?? null, true);

$custom_question_id = (int) ($body['question_id'] ?? 0);
$direction = trim((string) ($body['direction'] ?? ''));
if ($custom_question_id <= 0 || !in_array($direction, ['up', 'down'], true)) {
    json_response(['error' => 'Invalid move request.'], 400);
}

$existing_row = fetch_session_custom_question_row($pdo, (int) $session['id'], $custom_question_id);
if (!$existing_row) {
    json_response(['error' => 'That custom question was not found for this room.'], 404);
}

$target_position = (int) $existing_row['position'] + ($direction === 'up' ? -1 : 1);
if ($target_position < 0) {
    json_response(custom_quiz_builder_payload($pdo, $session));
}

$target_stmt = $pdo->prepare(
    'SELECT id, position
     FROM session_custom_questions
     WHERE session_id = ? AND position = ?
     LIMIT 1'
);
$target_stmt->execute([(int) $session['id'], $target_position]);
$target_row = $target_stmt->fetch();
if (!$target_row) {
    json_response(custom_quiz_builder_payload($pdo, $session));
}

try {
    $pdo->beginTransaction();

    $update = $pdo->prepare(
        'UPDATE session_custom_questions
         SET position = ?
         WHERE session_id = ? AND id = ?'
    );
    $update->execute([255, (int) $session['id'], $custom_question_id]);
    $update->execute([(int) $existing_row['position'], (int) $session['id'], (int) $target_row['id']]);
    $update->execute([$target_position, (int) $session['id'], $custom_question_id]);

    bump_session_state_version($pdo, (int) $session['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(['error' => 'Could not reorder that custom question.'], 500);
}

$session = refresh_session_by_id($pdo, (int) $session['id']);
json_response(custom_quiz_builder_payload($pdo, $session));
