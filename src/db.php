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
        $pdo->exec("ALTER TABLE questions ADD COLUMN image_url VARCHAR(512) NULL AFTER question_text");
    }

    $question_image_url_column = group_column_definition($pdo, 'questions', 'image_url');
    if ($question_image_url_column !== null
        && preg_match('/^varchar\\((\\d+)\\)$/i', (string) ($question_image_url_column['Type'] ?? ''), $matches)
        && (int) ($matches[1] ?? 0) < 512) {
        $pdo->exec("ALTER TABLE questions MODIFY image_url VARCHAR(512) NULL");
    }

    if (!group_column_exists($pdo, 'questions', 'image_lookup_query')) {
        $pdo->exec("ALTER TABLE questions ADD COLUMN image_lookup_query VARCHAR(255) NULL AFTER image_url");
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

    backfill_question_image_lookup_queries($pdo);

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

function question_image_lookup_seed_map(): array {
    return [
        'capitals' => [
            'What is the capital of France?' => 'Paris',
            'What is the capital of Australia?' => 'Canberra',
            'What is the capital of Brazil?' => 'Brasília',
            'What is the capital of Canada?' => 'Ottawa',
            'What is the capital of Japan?' => 'Tokyo',
            'What is the capital of Germany?' => 'Berlin',
            'What is the capital of Mexico?' => 'Mexico City',
            'What is the (administrative) capital of South Africa?' => 'Pretoria',
            'What is the capital of Argentina?' => 'Buenos Aires',
            'What is the capital of Nigeria?' => 'Abuja',
            'What is the capital of Pakistan?' => 'Islamabad',
            'What is the capital of Indonesia?' => 'Jakarta',
            'What is the capital of Kazakhstan?' => 'Astana',
            'What is the capital of Myanmar?' => 'Naypyidaw',
            'What is the capital of Sri Lanka?' => 'Sri Jayawardenepura Kotte',
        ],
        'languages' => [
            'What is the official language of Brazil?' => 'Rio de Janeiro',
            'What language is primarily spoken in Egypt?' => 'Cairo',
            'What is the official language of Mexico?' => 'Mexico City',
            'What is the most widely spoken language in the world by number of native speakers?' => 'Beijing',
            'What is the national language of Pakistan?' => 'Islamabad',
            'What is the national language of Kenya?' => 'Nairobi',
            'Which country has the most official languages?' => 'Cape Town',
            'What language is spoken in Ethiopia as the official language?' => 'Addis Ababa',
            'What is the official language of Suriname?' => 'Paramaribo',
            'Which language has the most words in its dictionary?' => 'London',
        ],
        'currency' => [
            'What currency does Japan use?' => 'Japanese yen',
            'What is the currency of India?' => 'Indian rupee',
            'What currency does the United States use?' => 'United States dollar',
            'What is the currency of Switzerland?' => 'Swiss franc',
            'What is the currency of Saudi Arabia?' => 'Saudi riyal',
            'Which of these countries uses the Euro?' => 'Euro',
            'What is the currency of South Korea?' => 'South Korean won',
            'What is the currency of Azerbaijan?' => 'Azerbaijani manat',
            'Which country uses the Zloty as its currency?' => 'Polish złoty',
        ],
        'geography' => [
            'Which country is famously shaped like a boot?' => 'Italy',
            'The mouth of the Amazon River is located in which country?' => 'Amazon River',
            'Which is the largest country in the world by land area?' => 'Russia',
            'On which continent is Egypt located?' => 'Egypt',
            'Which country has the most natural lakes in the world?' => 'Canada',
            'The Strait of Malacca separates which two landmasses?' => 'Strait of Malacca',
            'Which country shares the longest land border with Russia?' => 'Kazakhstan',
            'Mount Kilimanjaro is located in which country?' => 'Mount Kilimanjaro',
            'Which country has the highest number of neighbouring countries?' => 'China',
            'The Mariana Trench, the deepest point on Earth, is located in which ocean?' => 'Mariana Trench',
            'Which of these rivers is the longest in the world?' => 'Nile',
        ],
        'government' => [
            'Which of these countries is a republic?' => 'Paris',
            'Which of these countries is a constitutional monarchy?' => 'Stockholm Palace',
            'Which of these countries is governed as a federal republic?' => 'Reichstag building',
            'Which of these countries has a communist single-party government?' => 'Great Hall of the People',
            'Which country uses a parliamentary system with a prime minister as head of government?' => 'Parliament Hill',
            "Which country is widely regarded as the world's oldest continuously governed republic?" => 'Palazzo Pubblico (San Marino)',
            'What type of government system does Switzerland use, where executive power is shared by a seven-member council?' => 'Bern',
            'Which country has a theocratic government led by a Supreme Leader?' => 'Tehran',
        ],
        'alliances' => [
            'What does "UN" stand for?' => 'Headquarters of the United Nations',
            'Which of these countries is NOT a member of NATO?' => 'Geneva',
            'In which year was NATO founded?' => 'NATO Headquarters',
            'What is the primary purpose of OPEC?' => 'OPEC',
            'Which of these is NOT a permanent member of the UN Security Council?' => 'Berlin',
            'How many countries were founding members of the European Communities (predecessor to the EU)?' => 'Treaties of Rome',
            'In which year was the African Union founded?' => 'African Union Headquarters',
            'Which country was the first to leave the European Union?' => 'Palace of Westminster',
            'The ASEAN bloc was founded in 1967 with how many original member states?' => 'ASEAN Headquarters',
        ],
    ];
}

function backfill_question_image_lookup_queries(PDO $pdo): void {
    if (!group_column_exists($pdo, 'questions', 'image_lookup_query')) {
        return;
    }

    $categories = array_keys(question_image_lookup_seed_map());
    if (empty($categories)) {
        return;
    }

    $quoted_categories = array_map([$pdo, 'quote'], $categories);
    $pdo->exec(
        'UPDATE questions
         SET image_url = NULL
         WHERE category IN (' . implode(', ', $quoted_categories) . ")
           AND image_url LIKE 'public/img/questions/%'"
    );

    $missing_lookup_count = (int) $pdo->query(
        'SELECT COUNT(*) FROM questions WHERE category IN (' . implode(', ', $quoted_categories) . ")
         AND (image_lookup_query IS NULL OR image_lookup_query = '')"
    )->fetchColumn();
    if ($missing_lookup_count <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE questions
         SET image_lookup_query = ?
         WHERE category = ?
           AND question_text = ?
           AND (image_lookup_query IS NULL OR image_lookup_query = \'\')'
    );

    foreach (question_image_lookup_seed_map() as $category => $question_map) {
        foreach ($question_map as $question_text => $lookup_query) {
            $stmt->execute([$lookup_query, $category, $question_text]);
        }
    }
}
