#!/usr/local/bin/php
<?php
require_once __DIR__ . '/bootstrap.php';

$route_params = [
    'code' => normalize_session_code_value((string) ($_GET['code'] ?? '')),
];

require __DIR__ . '/pages/lobby.php';
