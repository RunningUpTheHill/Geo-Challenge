<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$content_type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$is_json_request = stripos($content_type, 'application/json') !== false;
$body = $is_json_request ? require_json_body() : $_POST;

$name = trim($body['player_name'] ?? '');
if (strlen($name) < 2 || strlen($name) > 32) {
    if ($is_json_request) {
        json_response(['error' => 'Player name must be 2–32 characters'], 400);
    }

    set_flash_message('Player name must be 2-32 characters.', 'danger');
    redirect_to('', 303);
}

$num_questions = (int) ($body['num_questions'] ?? QUESTIONS_PER_GAME);
if ($num_questions < 5 || $num_questions > 20) {
    $num_questions = QUESTIONS_PER_GAME;
}

$pdo = get_pdo();
$code = generate_session_code();
$player_token = generate_player_token();

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO sessions (code, num_questions) VALUES (?, ?)')->execute([$code, $num_questions]);
    $session_id = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO players (session_id, name, auth_token) VALUES (?, ?, ?)')
        ->execute([$session_id, $name, $player_token]);
    $player_id = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE sessions SET host_player_id = ? WHERE id = ?')->execute([$player_id, $session_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    if ($is_json_request) {
        json_response(['error' => 'Failed to create session'], 500);
    }

    set_flash_message('Failed to create session.', 'danger');
    redirect_to('', 303);
}

if ($is_json_request) {
    json_response([
        'code' => $code,
        'player_id' => $player_id,
        'player_name' => $name,
        'is_host' => true,
        'player_token' => $player_token,
    ]);
}

render_player_bootstrap_redirect($code, $player_id, $name, $player_token);
