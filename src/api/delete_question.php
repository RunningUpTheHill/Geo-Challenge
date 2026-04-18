<?php
require_once __DIR__ . '/../../src/helpers.php';

if (empty($_SESSION['admin_logged_in'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = isset($body['question_id']) ? (int) $body['question_id'] : 0;

if ($id <= 0) {
    json_response(['error' => 'Invalid question ID.'], 422);
}

$pdo = get_pdo();

$stmt = $pdo->prepare('DELETE FROM questions WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    json_response(['error' => 'Question not found.'], 404);
}

json_response(['deleted' => $id]);
