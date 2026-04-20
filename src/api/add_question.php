<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');

if (empty($_SESSION['admin_logged_in'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$body = require_json_body();

$text    = trim((string) ($body['question_text'] ?? ''));
$cat     = (string) ($body['category']      ?? '');
$diff    = (string) ($body['difficulty']    ?? '');
$opts    = $body['options']       ?? [];
$correct = $body['correct_index'] ?? null;

$valid_cats  = ['capitals', 'flags', 'languages', 'currency', 'geography', 'government', 'alliances'];
$valid_diffs = ['easy', 'medium', 'hard'];

if (strlen($text) < 5) {
    json_response(['error' => 'Question text is too short.'], 422);
}
if (!in_array($cat, $valid_cats, true)) {
    json_response(['error' => 'Invalid category.'], 422);
}
if (!in_array($diff, $valid_diffs, true)) {
    json_response(['error' => 'Invalid difficulty.'], 422);
}
if (!is_array($opts) || count($opts) !== 4) {
    json_response(['error' => 'Exactly 4 options required.'], 422);
}
foreach ($opts as $o) {
    if (!is_string($o) || trim($o) === '') {
        json_response(['error' => 'All options must be filled in.'], 422);
    }
}
if (!is_int($correct) || $correct < 0 || $correct > 3) {
    json_response(['error' => 'Invalid correct index.'], 422);
}

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'INSERT INTO questions (category, difficulty, question_text, options, correct_index)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$cat, $diff, $text, json_encode(array_values($opts)), $correct]);

json_response(['id' => (int) $pdo->lastInsertId()], 201);
