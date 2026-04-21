<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('POST');

$code = normalize_session_code_value((string) ($_POST['session_code'] ?? ''));
[$pdo, $session] = require_host_session_for_builder($code, $_POST['player_token'] ?? null, true);

$custom_question_id = (int) ($_POST['question_id'] ?? 0);
$topic_label = trim((string) ($_POST['topic_label'] ?? ''));
$question_text = trim((string) ($_POST['question_text'] ?? ''));
$correct_index = (int) ($_POST['correct_index'] ?? -1);
$remove_image = !empty($_POST['remove_image']);
$options = [];

for ($index = 0; $index < 4; $index++) {
    $options[] = trim((string) ($_POST['option_' . $index] ?? ''));
}

if (strlen($topic_label) < 2 || strlen($topic_label) > 80) {
    json_response(['error' => 'Topic label must be between 2 and 80 characters.'], 400);
}

if (strlen($question_text) < 5) {
    json_response(['error' => 'Question text must be at least 5 characters long.'], 400);
}

foreach ($options as $option) {
    if ($option === '') {
        json_response(['error' => 'Fill in all four answer options.'], 400);
    }
}

if ($correct_index < 0 || $correct_index > 3) {
    json_response(['error' => 'Choose which answer is correct.'], 400);
}

$existing_row = null;
if ($custom_question_id > 0) {
    $existing_row = fetch_session_custom_question_row($pdo, (int) $session['id'], $custom_question_id);
    if (!$existing_row) {
        json_response(['error' => 'That custom question was not found for this room.'], 404);
    }
}

$uploaded_file = $_FILES['image'] ?? null;
$has_new_upload = is_array($uploaded_file) && (int) ($uploaded_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$new_image_path = null;

try {
    if ($has_new_upload) {
        $new_image_path = store_custom_quiz_uploaded_image((int) $session['id'], $uploaded_file);
    }
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 400);
}

$image_asset_path = $existing_row['image_asset_path'] ?? null;
if ($has_new_upload) {
    $image_asset_path = $new_image_path;
} elseif ($remove_image) {
    $image_asset_path = null;
}

try {
    $pdo->beginTransaction();

    if ($existing_row) {
        $stmt = $pdo->prepare(
            'UPDATE session_custom_questions
             SET topic_label = ?,
                 question_text = ?,
                 options = ?,
                 correct_index = ?,
                 image_asset_path = ?
             WHERE id = ? AND session_id = ?'
        );
        $stmt->execute([
            $topic_label,
            $question_text,
            json_encode($options),
            $correct_index,
            $image_asset_path,
            $custom_question_id,
            (int) $session['id'],
        ]);
    } else {
        $position_stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(position), -1) + 1
             FROM session_custom_questions
             WHERE session_id = ?'
        );
        $position_stmt->execute([(int) $session['id']]);
        $next_position = (int) $position_stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO session_custom_questions (session_id, position, topic_label, question_text, options, correct_index, image_asset_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $session['id'],
            $next_position,
            $topic_label,
            $question_text,
            json_encode($options),
            $correct_index,
            $image_asset_path,
        ]);
    }

    bump_session_state_version($pdo, (int) $session['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($new_image_path !== null) {
        delete_custom_quiz_image_file($new_image_path);
    }

    json_response(['error' => 'Could not save that custom question.'], 500);
}

if ($existing_row && $has_new_upload && $new_image_path !== null && $new_image_path !== ($existing_row['image_asset_path'] ?? null)) {
    delete_custom_quiz_image_file($existing_row['image_asset_path'] ?? null);
} elseif ($existing_row && $remove_image) {
    delete_custom_quiz_image_file($existing_row['image_asset_path'] ?? null);
}

$session = refresh_session_by_id($pdo, (int) $session['id']);
json_response(custom_quiz_builder_payload($pdo, $session));
