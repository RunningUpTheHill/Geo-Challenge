<?php
require_method('POST');
$body = require_json_body();

$name = trim($body['player_name'] ?? '');
if (strlen($name) < 2 || strlen($name) > 32) {
    json_response(['error' => 'Player name must be 2–32 characters'], 400);
}

$pdo  = get_pdo();
$code = generate_session_code();

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO sessions (code) VALUES (?)')->execute([$code]);
    $session_id = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO players (session_id, name) VALUES (?, ?)')->execute([$session_id, $name]);
    $player_id = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE sessions SET host_player_id = ? WHERE id = ?')->execute([$player_id, $session_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error' => 'Failed to create session'], 500);
}

json_response([
    'code'        => $code,
    'player_id'   => $player_id,
    'player_name' => $name,
    'is_host'     => true,
]);
