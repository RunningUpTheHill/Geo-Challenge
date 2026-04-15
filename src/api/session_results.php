<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('GET');

$code = normalize_session_code_value((string) ($_GET['code'] ?? ''));
$active = require_player_auth_for_api($code, $_GET['player_token'] ?? null);
$session = get_session_by_code($code);
$pdo = get_pdo();

$players = fetch_session_players(
    $pdo,
    (int) $session['id'],
    $session['host_player_id'] !== null ? (int) $session['host_player_id'] : 0,
    (int) $active['player_id']
);

json_response([
    'code' => $code,
    'num_questions' => (int) $session['num_questions'],
    'players' => $players,
]);
