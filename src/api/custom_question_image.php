<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('GET');

$custom_question_id = (int) ($_GET['question_id'] ?? 0);
if ($custom_question_id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = get_pdo()->prepare(
    'SELECT image_asset_path
     FROM session_custom_questions
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$custom_question_id]);
$relative_path = $stmt->fetchColumn();
$absolute_path = custom_quiz_absolute_image_path(is_string($relative_path) ? $relative_path : null);

if ($absolute_path === null || !is_file($absolute_path)) {
    http_response_code(404);
    exit;
}

$mime = custom_quiz_detect_uploaded_image_mime($absolute_path);
if ($mime === null) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absolute_path));
header('Cache-Control: public, max-age=3600');
readfile($absolute_path);
exit;
