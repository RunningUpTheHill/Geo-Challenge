<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_method('GET');

$code = normalize_session_code_value((string) ($_GET['code'] ?? ''));
[$pdo, $session] = require_host_session_for_builder($code, $_GET['player_token'] ?? null, false);

json_response(custom_quiz_builder_payload($pdo, $session));
