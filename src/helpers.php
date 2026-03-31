<?php
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        json_response(['error' => 'Method not allowed'], 405);
    }
}

function require_json_body(): array {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        json_response(['error' => 'Invalid JSON body'], 400);
    }
    return $body;
}

function generate_session_code(): string {
    // Unambiguous characters only (no 0/O, 1/I/L)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pdo   = get_pdo();
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM sessions WHERE code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

function get_session_by_code(string $code): array {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM sessions WHERE code = ?');
    $stmt->execute([$code]);
    $session = $stmt->fetch();
    if (!$session) {
        json_response(['error' => 'Session not found'], 404);
    }
    return $session;
}

function escape_html(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
