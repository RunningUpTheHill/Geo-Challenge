<?php
session_start();

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Route params populated by match_route()
$route_params = [];

function match_route(string $pattern, string $uri, array &$params): bool {
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (preg_match($regex, $uri, $matches)) {
        foreach ($matches as $key => $val) {
            if (is_string($key)) {
                $params[$key] = $val;
            }
        }
        return true;
    }
    return false;
}

// ── Page routes ────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '') {
    require __DIR__ . '/pages/home.php'; exit;
}
if ($method === 'GET' && match_route('lobby/{code}', $uri, $route_params)) {
    require __DIR__ . '/pages/lobby.php'; exit;
}
if ($method === 'GET' && match_route('game/{code}', $uri, $route_params)) {
    require __DIR__ . '/pages/game.php'; exit;
}
if ($method === 'GET' && match_route('results/{code}', $uri, $route_params)) {
    require __DIR__ . '/pages/results.php'; exit;
}

if (match_route('admin', $uri, $route_params)) {
    require __DIR__ . '/pages/admin.php'; exit;
}

// ── API routes ─────────────────────────────────────────────────────
if ($method === 'POST' && $uri === 'api/session/create') {
    require __DIR__ . '/src/api/create_session.php'; exit;
}
if ($method === 'POST' && $uri === 'api/session/join') {
    require __DIR__ . '/src/api/join_session.php'; exit;
}
if ($method === 'POST' && $uri === 'api/session/start') {
    require __DIR__ . '/src/api/start_game.php'; exit;
}
if ($method === 'POST' && $uri === 'api/answer/submit') {
    require __DIR__ . '/src/api/submit_answer.php'; exit;
}
if ($method === 'POST' && $uri === 'api/session/end') {
    require __DIR__ . '/src/api/end_game.php'; exit;
}
if ($method === 'GET' && match_route('api/session/{code}/status', $uri, $route_params)) {
    require __DIR__ . '/src/api/session_status.php'; exit;
}
if ($method === 'GET' && match_route('api/stream/{code}/{player_id}', $uri, $route_params)) {
    require __DIR__ . '/src/api/stream.php'; exit;
}
if ($method === 'POST' && $uri === 'api/question/add') {
    require __DIR__ . '/src/api/add_question.php'; exit;
}
if ($method === 'POST' && $uri === 'api/question/delete') {
    require __DIR__ . '/src/api/delete_question.php'; exit;
}

// ── 404 ────────────────────────────────────────────────────────────
http_response_code(404);
echo '<!DOCTYPE html><html><body><h1>404 Not Found</h1></body></html>';
