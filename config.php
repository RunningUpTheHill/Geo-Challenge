<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', PHP_OS_FAMILY === 'Windows' ? '3306' : '8889');
define('DB_NAME', 'geo_challenge');
define('DB_USER', 'root');
define('DB_PASS', 'root');  // MAMP default password

define('QUESTIONS_PER_GAME', 10);
define('QUESTION_DURATION_SEC', 20);
