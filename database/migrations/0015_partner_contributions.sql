-- SMART Circle v2: partner teachers confirm their school's calendar, share resources (uploaded, or
-- sent to the tutor on WhatsApp) and suggest topics missing from the syllabus.

CREATE TABLE calendar_confirmations (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id  INT UNSIGNED NOT NULL,
    year_id    INT UNSIGNED NOT NULL,
    status     VARCHAR(20)  NOT NULL COMMENT 'confirmed | problem',
    note       VARCHAR(1000) NULL COMMENT 'What is wrong (problem)',
    user_id    INT UNSIGNED NULL,
    resolved_at DATETIME    NULL COMMENT 'Tutor fixed a reported problem',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_calendar_confirmations_school (school_id, year_id, created_at),
    CONSTRAINT fk_calendar_confirmations_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_confirmations_year FOREIGN KEY (year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_confirmations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_resources (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    school_id   INT UNSIGNED     NULL,
    user_id     INT UNSIGNED     NULL,
    kind        VARCHAR(20)      NOT NULL COMMENT 'syllabus | scheme_of_work | past_paper | notes | other',
    title       VARCHAR(150)     NOT NULL,
    subject     VARCHAR(20)      NULL,
    form        TINYINT UNSIGNED NULL,
    via         VARCHAR(20)      NOT NULL COMMENT 'upload | whatsapp',
    file_path   VARCHAR(255)     NULL COMMENT 'Relative to storage/; uploads only',
    mime        VARCHAR(100)     NULL,
    size_bytes  INT UNSIGNED     NULL,
    note        VARCHAR(500)     NULL,
    status      VARCHAR(20)      NOT NULL DEFAULT 'new' COMMENT 'new | reviewed',
    reviewed_by INT UNSIGNED     NULL,
    reviewed_at DATETIME         NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_resources_school (school_id, created_at),
    KEY idx_school_resources_status (status, created_at),
    CONSTRAINT fk_school_resources_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE SET NULL,
    CONSTRAINT fk_school_resources_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_school_resources_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topic_suggestions (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED     NULL,
    school_id     INT UNSIGNED     NULL,
    form          TINYINT UNSIGNED NOT NULL,
    subject       VARCHAR(20)      NOT NULL,
    parent_id     INT UNSIGNED     NULL COMMENT 'Suggested as a subtopic of this main topic',
    title         VARCHAR(200)     NOT NULL,
    note          VARCHAR(1000)    NULL COMMENT 'Why / where it appears in the syllabus',
    status        VARCHAR(20)      NOT NULL DEFAULT 'pending' COMMENT 'pending | added | declined',
    topic_id      INT UNSIGNED     NULL COMMENT 'The topic created when added',
    decision_note VARCHAR(500)     NULL,
    decided_by    INT UNSIGNED     NULL,
    decided_at    DATETIME         NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_topic_suggestions_status (status, created_at),
    CONSTRAINT fk_topic_suggestions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_topic_suggestions_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE SET NULL,
    CONSTRAINT fk_topic_suggestions_parent FOREIGN KEY (parent_id) REFERENCES topics (id) ON DELETE SET NULL,
    CONSTRAINT fk_topic_suggestions_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL,
    CONSTRAINT fk_topic_suggestions_decided_by FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
