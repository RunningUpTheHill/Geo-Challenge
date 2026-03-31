CREATE DATABASE IF NOT EXISTS geo_challenge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE geo_challenge;

CREATE TABLE IF NOT EXISTS sessions (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code                CHAR(6)         NOT NULL UNIQUE,
    host_player_id      INT UNSIGNED    NULL,
    status              ENUM('waiting','in_progress','finished') NOT NULL DEFAULT 'waiting',
    current_q_index     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    question_started_at DATETIME        NULL,
    started_at          DATETIME        NULL,
    finished_at         DATETIME        NULL,
    created_at          DATETIME        NOT NULL DEFAULT NOW()
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS players (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id    INT UNSIGNED    NOT NULL,
    name          VARCHAR(32)     NOT NULL,
    score         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    total_time_ms INT UNSIGNED    NOT NULL DEFAULT 0,
    finished_at   DATETIME        NULL,
    last_seen_at  DATETIME        NOT NULL DEFAULT NOW(),
    created_at    DATETIME        NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_player_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE sessions ADD CONSTRAINT fk_session_host
    FOREIGN KEY (host_player_id) REFERENCES players(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS questions (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category      ENUM('capitals','flags','languages','currency','geography','government','alliances') NOT NULL,
    difficulty    ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
    question_text TEXT            NOT NULL,
    options       JSON            NOT NULL,
    correct_index TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS session_questions (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id  INT UNSIGNED    NOT NULL,
    question_id INT UNSIGNED    NOT NULL,
    position    TINYINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_session_position (session_id, position),
    CONSTRAINT fk_sq_session  FOREIGN KEY (session_id)  REFERENCES sessions(id)  ON DELETE CASCADE,
    CONSTRAINT fk_sq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS answers (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id    INT UNSIGNED    NOT NULL,
    session_id   INT UNSIGNED    NOT NULL,
    question_id  INT UNSIGNED    NOT NULL,
    chosen_index TINYINT UNSIGNED NOT NULL,
    is_correct   TINYINT(1)      NOT NULL DEFAULT 0,
    time_ms      INT UNSIGNED    NOT NULL DEFAULT 0,
    submitted_at DATETIME        NOT NULL DEFAULT NOW(),
    UNIQUE KEY uq_player_question (player_id, question_id),
    CONSTRAINT fk_ans_player   FOREIGN KEY (player_id)   REFERENCES players(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ans_session  FOREIGN KEY (session_id)  REFERENCES sessions(id)  ON DELETE CASCADE,
    CONSTRAINT fk_ans_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
