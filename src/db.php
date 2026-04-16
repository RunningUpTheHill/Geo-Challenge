<?php
require_once dirname(__DIR__) . '/config.php';

function get_db_config(): array {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config_candidates = [
        dirname(__DIR__) . '/db_config.php',
        dirname(__DIR__, 2) . '/db_config.php',
    ];

    $config_file = null;
    foreach ($config_candidates as $candidate) {
        if (file_exists($candidate)) {
            $config_file = $candidate;
            break;
        }
    }

    if ($config_file === null) {
        throw new RuntimeException(
            'Database configuration file is missing. Create db_config.php in the project root or parent group directory before running the app.'
        );
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

    $session_datetime_columns = [
        'question_started_at' => 'NULL',
        'phase_started_at' => 'NULL',
        'started_at' => 'NULL',
        'finished_at' => 'NULL',
        'created_at' => 'NOT NULL DEFAULT CURRENT_TIMESTAMP(6)',
    ];
    foreach ($session_datetime_columns as $column => $definition) {
        $column_info = group_column_definition($pdo, 'sessions', $column);
        if ($column_info !== null && stripos((string) ($column_info['Type'] ?? ''), 'datetime(6)') === false) {
            $pdo->exec("ALTER TABLE sessions MODIFY {$column} DATETIME(6) {$definition}");
        }
    }

    if (!group_column_exists($pdo, 'sessions', 'state_version')) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN state_version BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER created_at");
    }

    if (!group_column_exists($pdo, 'sessions', 'state_changed_at')) {
        $pdo->exec(
            "ALTER TABLE sessions
             ADD COLUMN state_changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
             AFTER state_version"
        );
    }

    if (!group_column_exists($pdo, 'answers', 'points_awarded')) {
        $pdo->exec("ALTER TABLE answers ADD COLUMN points_awarded INT UNSIGNED NOT NULL DEFAULT 0 AFTER time_ms");
    }

    if (!group_column_exists($pdo, 'questions', 'image_url')) {
        $pdo->exec("ALTER TABLE questions ADD COLUMN image_url VARCHAR(255) NULL AFTER question_text");
    }

    if (!group_column_exists($pdo, 'players', 'auth_token')) {
        $pdo->exec("ALTER TABLE players ADD COLUMN auth_token CHAR(64) NULL AFTER name");
    }

    if (!group_column_exists($pdo, 'players', 'game_ready_at')) {
        $pdo->exec("ALTER TABLE players ADD COLUMN game_ready_at DATETIME NULL AFTER last_seen_at");
    }

    $player_datetime_columns = [
        'finished_at' => 'NULL',
        'last_seen_at' => 'NOT NULL DEFAULT CURRENT_TIMESTAMP(6)',
        'game_ready_at' => 'NULL',
        'created_at' => 'NOT NULL DEFAULT CURRENT_TIMESTAMP(6)',
    ];
    foreach ($player_datetime_columns as $column => $definition) {
        $column_info = group_column_definition($pdo, 'players', $column);
        if ($column_info !== null && stripos((string) ($column_info['Type'] ?? ''), 'datetime(6)') === false) {
            $pdo->exec("ALTER TABLE players MODIFY {$column} DATETIME(6) {$definition}");
        }
    }

    $answer_submitted_column = group_column_definition($pdo, 'answers', 'submitted_at');
    if ($answer_submitted_column !== null
        && stripos((string) ($answer_submitted_column['Type'] ?? ''), 'datetime(6)') === false) {
        $pdo->exec("ALTER TABLE answers MODIFY submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)");
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

    // Fix answers unique key to include session_id so answers from different game sessions
    // for the same question don't collide and cause INSERT IGNORE to silently fail.
    $needs_key_fix = false;
    $key_rows = $pdo->query(
        "SHOW INDEX FROM `answers` WHERE Key_name = 'uq_player_question'"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($key_rows)) {
        $columns_in_key = array_column($key_rows, 'Column_name');
        if (!in_array('session_id', $columns_in_key, true)) {
            $needs_key_fix = true;
        }
    }
    if ($needs_key_fix) {
        // The old unique key also doubles as the supporting index for fk_ans_player.
        // Add a dedicated player_id index before dropping it so the migration works on
        // existing databases without violating the foreign key requirement.
        if (!group_index_exists($pdo, 'answers', 'idx_answers_player_id')) {
            $pdo->exec("ALTER TABLE answers ADD KEY idx_answers_player_id (player_id)");
        }
        $pdo->exec("ALTER TABLE answers DROP INDEX uq_player_question");
        $pdo->exec("ALTER TABLE answers ADD UNIQUE KEY uq_player_question (player_id, question_id, session_id)");
    } elseif (empty($key_rows)) {
        // Key doesn't exist at all — create it with the correct columns
        $pdo->exec("ALTER TABLE answers ADD UNIQUE KEY uq_player_question (player_id, question_id, session_id)");
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
         WHERE (round_phase IS NULL OR round_phase = '')
            OR (round_phase = 'lobby' AND status <> 'waiting')
            OR (round_phase NOT IN ('lobby','ready','question','leaderboard','finished') AND status <> 'finished')"
    );

    $pdo->exec(
        "UPDATE sessions s
         LEFT JOIN (
             SELECT
                 session_id,
                 COUNT(*) AS player_count,
                 SUM(CASE WHEN game_ready_at IS NOT NULL THEN 1 ELSE 0 END) AS ready_player_count
             FROM players
             GROUP BY session_id
         ) p ON p.session_id = s.id
         SET s.phase_started_at = NULL,
             s.state_version = s.state_version + 1,
             s.state_changed_at = NOW(6)
         WHERE s.status = 'in_progress'
           AND s.round_phase = 'ready'
           AND s.current_q_index = 0
           AND s.question_started_at IS NULL
           AND COALESCE(p.ready_player_count, 0) < COALESCE(p.player_count, 0)
           AND s.phase_started_at IS NOT NULL"
    );

    $pdo->exec(
        "UPDATE sessions
         SET phase_started_at = COALESCE(
             phase_started_at,
             question_started_at,
             started_at,
             created_at
         )
         WHERE phase_started_at IS NULL
           AND NOT (
               status = 'in_progress'
               AND round_phase = 'ready'
               AND current_q_index = 0
               AND question_started_at IS NULL
           )"
    );

    $pdo->exec(
        "UPDATE sessions
         SET state_changed_at = COALESCE(
             state_changed_at,
             phase_started_at,
             question_started_at,
             started_at,
             created_at,
             NOW(6)
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
