<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$body = require_json_body();

$code = normalize_session_code_value((string) ($body['session_code'] ?? ''));
$quiz_mode = trim((string) ($body['quiz_mode'] ?? ''));
if (!in_array($quiz_mode, ['built_in', 'custom'], true)) {
    json_response(['error' => 'Choose either the built-in quiz or a custom quiz.'], 400);
}

[$pdo, $session] = require_host_session_for_builder($code, $body['player_token'] ?? null, true);

$stmt = $pdo->prepare('UPDATE sessions SET quiz_mode = ? WHERE id = ?');
$stmt->execute([$quiz_mode, (int) $session['id']]);
bump_session_state_version($pdo, (int) $session['id']);

$session = refresh_session_by_id($pdo, (int) $session['id']);
json_response(custom_quiz_builder_payload($pdo, $session));
