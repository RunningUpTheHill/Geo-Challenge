<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');
$body = require_json_body();

$code = normalize_session_code_value((string) ($body['session_code'] ?? ''));
$active = require_player_auth_for_api($code, $body['player_token'] ?? null);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo     = get_pdo();
$session = get_session_by_code($code);

if ((int) $session['host_player_id'] !== (int) $active['player_id']) {
    json_response(['error' => 'Only the host can start the game'], 403);
}
if ($session['status'] !== 'waiting') {
    json_response(['error' => 'Game has already started'], 409);
}

$num_questions = (int) $session['num_questions'];

/**
 * Pick $count random question IDs at a given difficulty, excluding already-chosen IDs.
 * Returns fewer than $count if the question bank doesn't have enough at that tier.
 */
function pick_ids_at_difficulty(PDO $pdo, string $difficulty, int $count, array $exclude): array
{
    if ($count <= 0) {
        return [];
    }

    if (count($exclude) > 0) {
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));
        $stmt = $pdo->prepare(
            "SELECT id FROM questions
             WHERE difficulty = ? AND id NOT IN ($placeholders)
             ORDER BY RAND() LIMIT $count"
        );
        $stmt->execute(array_merge([$difficulty], $exclude));
    } else {
        $stmt = $pdo->prepare(
            "SELECT id FROM questions WHERE difficulty = ? ORDER BY RAND() LIMIT $count"
        );
        $stmt->execute([$difficulty]);
    }

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Progressive difficulty tiers: 40% easy → 40% medium → 20% hard
$hard_count   = max(1, (int) round($num_questions * 0.20));
$medium_count = (int) round($num_questions * 0.40);
$easy_count   = $num_questions - $hard_count - $medium_count;

$easy_ids   = pick_ids_at_difficulty($pdo, 'easy',   $easy_count,   []);
$medium_ids = pick_ids_at_difficulty($pdo, 'medium', $medium_count, $easy_ids);
$hard_ids   = pick_ids_at_difficulty($pdo, 'hard',   $hard_count,   array_merge($easy_ids, $medium_ids));

// If any tier is short, backfill from adjacent difficulties
$all_chosen  = array_merge($easy_ids, $medium_ids, $hard_ids);
$still_needed = $num_questions - count($all_chosen);

if ($still_needed > 0) {
    // Pull from whatever difficulty has leftovers, avoiding duplicates
    foreach (['medium', 'easy', 'hard'] as $fallback) {
        if ($still_needed <= 0) {
            break;
        }
        $extras = pick_ids_at_difficulty($pdo, $fallback, $still_needed, $all_chosen);
        $all_chosen    = array_merge($all_chosen, $extras);
        $still_needed -= count($extras);
    }
}

// Shuffle within each tier so category order is random, but tiers stay ordered
shuffle($easy_ids);
shuffle($medium_ids);
shuffle($hard_ids);

// Final ordered list: easy → medium → hard
$question_ids = array_merge($easy_ids, $medium_ids, $hard_ids);

// If we still need more (extreme edge case: very small question bank), append backfill
if (count($question_ids) < $num_questions) {
    $question_ids = array_merge($question_ids, array_slice($all_chosen, count($question_ids)));
}

$question_ids = array_slice(array_unique($question_ids), 0, $num_questions);

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE players
         SET game_ready_at = NULL
         WHERE session_id = ?'
    )->execute([$session['id']]);

    foreach ($question_ids as $pos => $qid) {
        $pdo->prepare('INSERT INTO session_questions (session_id, question_id, position) VALUES (?, ?, ?)')
            ->execute([$session['id'], $qid, $pos]);
    }

    $pdo->prepare(
        "UPDATE sessions
         SET status = 'in_progress',
             round_phase = 'ready',
             started_at = NOW(6),
             phase_started_at = NULL,
             question_started_at = NULL,
             current_q_index = 0
         WHERE id = ?"
    )->execute([$session['id']]);
    bump_session_state_version($pdo, (int) $session['id']);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error' => 'Failed to start game'], 500);
}

json_response(['success' => true]);
