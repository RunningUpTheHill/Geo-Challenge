<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$body = require_json_body();

$code = normalize_session_code_value((string) ($body['session_code'] ?? ''));
[$pdo, $session] = require_host_session_for_builder($code, $body['player_token'] ?? null, true);

$custom_question_id = (int) ($body['question_id'] ?? 0);
if ($custom_question_id <= 0) {
    json_response(['error' => 'Choose a custom question to delete.'], 400);
}

$existing_row = fetch_session_custom_question_row($pdo, (int) $session['id'], $custom_question_id);
if (!$existing_row) {
    json_response(['error' => 'That custom question was not found for this room.'], 404);
}

try {
    $pdo->beginTransaction();

    $delete = $pdo->prepare('DELETE FROM session_custom_questions WHERE session_id = ? AND id = ?');
    $delete->execute([(int) $session['id'], $custom_question_id]);

    $shift = $pdo->prepare(
        'UPDATE session_custom_questions
         SET position = position - 1
         WHERE session_id = ?
           AND position > ?'
    );
    $shift->execute([(int) $session['id'], (int) $existing_row['position']]);

    bump_session_state_version($pdo, (int) $session['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(['error' => 'Could not delete that custom question.'], 500);
}

delete_custom_quiz_image_file($existing_row['image_asset_path'] ?? null);

$session = refresh_session_by_id($pdo, (int) $session['id']);
json_response(custom_quiz_builder_payload($pdo, $session));
