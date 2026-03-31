<?php
require_method('POST');
$body = require_json_body();

$name = trim($body['player_name'] ?? '');
$code = strtoupper(trim($body['code'] ?? ''));

if (strlen($name) < 2 || strlen($name) > 32) {
    json_response(['error' => 'Player name must be 2–32 characters'], 400);
}
if (strlen($code) !== 6) {
    json_response(['error' => 'Invalid session code'], 400);
}

$pdo     = get_pdo();
$session = get_session_by_code($code);

if ($session['status'] !== 'waiting') {
    json_response(['error' => 'Game has already started or finished'], 409);
}

$cnt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE session_id = ?');
$cnt->execute([$session['id']]);
if ((int) $cnt->fetchColumn() >= 10) {
    json_response(['error' => 'Session is full (max 10 players)'], 409);
}

$pdo->prepare('INSERT INTO players (session_id, name) VALUES (?, ?)')->execute([$session['id'], $name]);
$player_id = (int) $pdo->lastInsertId();

json_response([
    'code'        => $code,
    'player_id'   => $player_id,
    'player_name' => $name,
    'is_host'     => false,
]);
