-- SMART Circle v2: the weekly Quick 5 review, and anonymous termly feedback.

CREATE TABLE quick5_attempts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    correct     TINYINT(1)   NOT NULL,
    week        CHAR(8)      NOT NULL COMMENT 'ISO week, e.g. 2026-W48',
    answered_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quick5_user (user_id, answered_at),
    KEY idx_quick5_week (user_id, week),
    CONSTRAINT fk_quick5_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_quick5_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anonymous on purpose: responses carry no user id. Who has answered is kept apart (to ask only once).
CREATE TABLE term_feedback (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    term_id    INT UNSIGNED     NOT NULL,
    form       TINYINT UNSIGNED NULL,
    clarity    TINYINT UNSIGNED NOT NULL COMMENT '1 (hard to follow) to 5 (very clear)',
    pace       VARCHAR(10)      NOT NULL COMMENT 'slow | right | fast',
    helpful    VARCHAR(500)     NULL,
    change_one VARCHAR(500)     NULL,
    created_on DATE             NOT NULL COMMENT 'Day only, so the time cannot identify anyone',
    PRIMARY KEY (id),
    KEY idx_term_feedback_term (term_id),
    CONSTRAINT fk_term_feedback_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE term_feedback_done (
    user_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, term_id),
    CONSTRAINT fk_term_feedback_done_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_term_feedback_done_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
