<?php
require_method('GET');

$code    = $route_params['code'] ?? '';
$session = get_session_by_code($code);
$pdo     = get_pdo();

$stmt = $pdo->prepare(
    'SELECT id, name, score, total_time_ms, finished_at
     FROM players
     WHERE session_id = ?
     ORDER BY score DESC, total_time_ms ASC'
);
$stmt->execute([$session['id']]);
$rows = $stmt->fetchAll();

$players = [];
foreach ($rows as $i => $p) {
    $players[] = [
        'rank'          => $i + 1,
        'id'            => (int) $p['id'],
        'name'          => $p['name'],
        'score'         => (int) $p['score'],
        'total_time_ms' => (int) $p['total_time_ms'],
        'finished_at'   => $p['finished_at'],
    ];
}

$current_question = null;
if ($session['status'] === 'in_progress') {
    $q_stmt = $pdo->prepare(
        'SELECT q.id, q.question_text, q.category, q.options
         FROM session_questions sq
         JOIN questions q ON q.id = sq.question_id
         WHERE sq.session_id = ? AND sq.position = ?'
    );
    $q_stmt->execute([$session['id'], (int) $session['current_q_index']]);
    $q = $q_stmt->fetch();

    if ($q) {
        $current_question = [
            'index'       => (int) $session['current_q_index'],
            'id'          => (int) $q['id'],
            'text'        => $q['question_text'],
            'category'    => $q['category'],
            'options'     => json_decode($q['options'], true),
            'time_limit'  => QUESTION_DURATION_SEC,
            'server_time' => microtime(true),
        ];
    }
}

json_response([
    'status'          => $session['status'],
    'current_q_index' => (int) $session['current_q_index'],
    'num_questions'   => (int) $session['num_questions'],
    'host_player_id'  => $session['host_player_id'] !== null ? (int) $session['host_player_id'] : null,
    'player_count'    => count($players),
    'players'         => $players,
    'current_question'=> $current_question,
]);
