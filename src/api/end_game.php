<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$body = require_json_body();
$code = normalize_session_code_value((string) ($body['session_code'] ?? ''));
$active = require_player_auth_for_api($code, $body['player_token'] ?? null);

$pdo = get_pdo();
$session = get_session_by_code($code);

if ((int) $session['host_player_id'] !== (int) $active['player_id']) {
    json_response(['error' => 'Only the host can end the game'], 403);
}
if ($session['status'] !== 'in_progress') {
    json_response(['error' => 'Game is not in progress'], 409);
}

finish_session($pdo, (int) $session['id']);

json_response(['success' => true]);
