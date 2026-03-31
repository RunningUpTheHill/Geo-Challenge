<?php
require_method('POST');
$body = require_json_body();

$code      = strtoupper(trim($body['session_code'] ?? ''));
$player_id = (int) ($body['player_id'] ?? 0);

$pdo     = get_pdo();
$session = get_session_by_code($code);

if ((int) $session['host_player_id'] !== $player_id) {
    json_response(['error' => 'Only the host can end the game'], 403);
}
if ($session['status'] !== 'in_progress') {
    json_response(['error' => 'Game is not in progress'], 409);
}

$pdo->prepare(
    "UPDATE sessions SET status = 'finished', finished_at = NOW() WHERE id = ?"
)->execute([$session['id']]);

$pdo->prepare(
    "UPDATE players SET finished_at = NOW() WHERE session_id = ? AND finished_at IS NULL"
)->execute([$session['id']]);

json_response(['success' => true]);
