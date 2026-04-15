<?php
require_once dirname(__DIR__) . '/config.php';

function get_db_config(): array {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config_file = dirname(__DIR__) . '/db_config.php';
    if (!file_exists($config_file)) {
        throw new RuntimeException('Database configuration file is missing. Create group/db_config.php before running the app.');
    }

    $config = require $config_file;
    if (!is_array($config)) {
        throw new RuntimeException('Database configuration file must return an array.');
    }

    foreach (['host', 'port', 'dbname', 'username', 'password', 'charset'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException('Database configuration is missing the "' . $key . '" value.');
        }
    }

    $placeholders = ['YOUR_DB_NAME', 'YOUR_USERNAME', 'YOUR_PASSWORD'];
    foreach (['dbname', 'username', 'password'] as $key) {
        if (in_array($config[$key], $placeholders, true)) {
            throw new RuntimeException('Update group/db_config.php with your remote MySQL credentials before running the app.');
        }
    }

    return $config;
}

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $config = get_db_config();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        ensure_group_schema($pdo);
    }
    return $pdo;
}

function ensure_group_schema(PDO $pdo): void {
    static $checked = false;

    if ($checked) {
        return;
    }

    if (!group_column_exists($pdo, 'sessions', 'round_phase')) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN round_phase VARCHAR(20) NOT NULL DEFAULT 'lobby' AFTER status");
    }

    if (!group_column_exists($pdo, 'sessions', 'phase_started_at')) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN phase_started_at DATETIME NULL AFTER question_started_at");
    }

    if (!group_column_exists($pdo, 'answers', 'points_awarded')) {
        $pdo->exec("ALTER TABLE answers ADD COLUMN points_awarded INT UNSIGNED NOT NULL DEFAULT 0 AFTER time_ms");
    }

    if (!group_column_exists($pdo, 'players', 'auth_token')) {
        $pdo->exec("ALTER TABLE players ADD COLUMN auth_token CHAR(64) NULL AFTER name");
    }

    $missing_token_count = (int) $pdo->query(
        "SELECT COUNT(*) FROM players WHERE auth_token IS NULL OR auth_token = ''"
    )->fetchColumn();
    if ($missing_token_count > 0) {
        $pdo->exec(
            "UPDATE players
             SET auth_token = SHA2(CONCAT(UUID(), '-', id, '-', RAND(), '-', NOW(6)), 256)
             WHERE auth_token IS NULL OR auth_token = ''"
        );
    }

    if (!group_unique_index_exists_for_column($pdo, 'players', 'auth_token')) {
        $pdo->exec("ALTER TABLE players ADD UNIQUE KEY uq_players_auth_token (auth_token)");
    }

    $auth_token_column = group_column_definition($pdo, 'players', 'auth_token');
    if ($auth_token_column !== null && strtoupper((string) ($auth_token_column['Null'] ?? 'YES')) !== 'NO') {
        $pdo->exec("ALTER TABLE players MODIFY auth_token CHAR(64) NOT NULL");
    }

    $score_column = group_column_definition($pdo, 'players', 'score');
    if ($score_column !== null && stripos($score_column['Type'] ?? '', 'tinyint') !== false) {
        $pdo->exec("ALTER TABLE players MODIFY score INT UNSIGNED NOT NULL DEFAULT 0");
    }

    $pdo->exec(
        "UPDATE sessions
         SET round_phase = CASE
             WHEN status = 'waiting' THEN 'lobby'
             WHEN status = 'finished' THEN 'leaderboard'
             WHEN status = 'in_progress' AND question_started_at IS NULL THEN 'ready'
             WHEN status = 'in_progress' THEN 'question'
             ELSE round_phase
         END
         WHERE round_phase IS NULL
            OR round_phase = ''
            OR (round_phase = 'lobby' AND status <> 'waiting')"
    );

    $pdo->exec(
        "UPDATE sessions
         SET phase_started_at = COALESCE(
             phase_started_at,
             question_started_at,
             started_at,
             created_at
         )"
    );

    $checked = true;
}

function group_column_exists(PDO $pdo, string $table, string $column): bool {
    return group_column_definition($pdo, $table, $column) !== null;
}

function group_column_definition(PDO $pdo, string $table, string $column): ?array {
    $row = $pdo->query(
        "SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column)
    )->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function group_index_exists(PDO $pdo, string $table, string $index): bool {
    $row = $pdo->query(
        "SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($index)
    )->fetch(PDO::FETCH_ASSOC);

    return $row !== false;
}

function group_unique_index_exists_for_column(PDO $pdo, string $table, string $column): bool {
    $rows = $pdo->query(
        "SHOW INDEX FROM `{$table}` WHERE Column_name = " . $pdo->quote($column)
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $index) {
        if ((int) ($index['Non_unique'] ?? 1) === 0) {
            return true;
        }
    }

    return false;
}
