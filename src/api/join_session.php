<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$content_type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$is_json_request = stripos($content_type, 'application/json') !== false;
$body = $is_json_request ? require_json_body() : $_POST;

$name = trim($body['player_name'] ?? '');
$code = strtoupper(trim($body['code'] ?? ''));

if (strlen($name) < 2 || strlen($name) > 32) {
    if ($is_json_request) {
        json_response(['error' => 'Player name must be 2–32 characters'], 400);
    }

    set_flash_message('Player name must be 2-32 characters.', 'danger');
    redirect_to('', 303);
}
if (strlen($code) !== 6) {
    if ($is_json_request) {
        json_response(['error' => 'Invalid session code'], 400);
    }

    set_flash_message('Enter a valid 6-character session code.', 'danger');
    redirect_to('', 303);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo     = get_pdo();
$session = get_session_by_code($code);

if ($session['status'] !== 'waiting') {
    if ($is_json_request) {
        json_response(['error' => 'Game has already started or finished'], 409);
    }

    set_flash_message('That game has already started or finished.', 'danger');
    redirect_to('', 303);
}

$cnt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE session_id = ?');
$cnt->execute([$session['id']]);
if ((int) $cnt->fetchColumn() >= MAX_PLAYERS_PER_SESSION) {
    if ($is_json_request) {
        json_response(['error' => 'Session is full (max ' . MAX_PLAYERS_PER_SESSION . ' players)'], 409);
    }

    set_flash_message('That session is already full.', 'danger');
    redirect_to('', 303);
}

$player_token = generate_player_token();

$pdo->prepare('INSERT INTO players (session_id, name, auth_token) VALUES (?, ?, ?)')
    ->execute([$session['id'], $name, $player_token]);
$player_id = (int) $pdo->lastInsertId();
bump_session_state_version($pdo, (int) $session['id']);

if ($is_json_request) {
    json_response([
        'code'        => $code,
        'player_id'   => $player_id,
        'player_name' => $name,
        'is_host'     => false,
        'player_token' => $player_token,
    ]);
}

render_player_bootstrap_redirect($code, $player_id, $name, $player_token);
