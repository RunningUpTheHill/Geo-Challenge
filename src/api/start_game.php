<?php
require_method('POST');
$body = require_json_body();

$code      = strtoupper(trim($body['session_code'] ?? ''));
$player_id = (int) ($body['player_id'] ?? 0);

$pdo     = get_pdo();
$session = get_session_by_code($code);

if ((int) $session['host_player_id'] !== $player_id) {
    json_response(['error' => 'Only the host can start the game'], 403);
}
if ($session['status'] !== 'waiting') {
    json_response(['error' => 'Game has already started'], 409);
}

$num_questions = (int) $session['num_questions'];

// Pick 2 questions per category (14 total), shuffle, take num_questions
$categories = ['capitals','flags','languages','currency','geography','government','alliances'];
$question_ids = [];

$per_cat = $pdo->prepare('SELECT id FROM questions WHERE category = ? ORDER BY RAND() LIMIT 2');
foreach ($categories as $cat) {
    $per_cat->execute([$cat]);
    foreach ($per_cat->fetchAll(PDO::FETCH_COLUMN) as $qid) {
        $question_ids[] = (int) $qid;
    }
}

shuffle($question_ids);
$question_ids = array_slice(array_unique($question_ids), 0, $num_questions);

// Fill up if we still don't have enough
$needed = $num_questions - count($question_ids);
if ($needed > 0) {
    if (count($question_ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $fill = $pdo->prepare(
            "SELECT id FROM questions WHERE id NOT IN ($placeholders) ORDER BY RAND() LIMIT $needed"
        );
        $fill->execute($question_ids);
    } else {
        $fill = $pdo->prepare("SELECT id FROM questions ORDER BY RAND() LIMIT $needed");
        $fill->execute();
    }
    foreach ($fill->fetchAll(PDO::FETCH_COLUMN) as $qid) {
        $question_ids[] = (int) $qid;
    }
}

$pdo->beginTransaction();
try {
    foreach ($question_ids as $pos => $qid) {
        $pdo->prepare('INSERT INTO session_questions (session_id, question_id, position) VALUES (?, ?, ?)')
            ->execute([$session['id'], $qid, $pos]);
    }

    $pdo->prepare(
        "UPDATE sessions
         SET status = 'in_progress', started_at = NOW(), question_started_at = NOW(), current_q_index = 0
         WHERE id = ?"
    )->execute([$session['id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error' => 'Failed to start game'], 500);
}

json_response(['success' => true]);
