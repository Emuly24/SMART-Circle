-- SMART Circle v2 — recorded lessons and resumable uploads.

CREATE TABLE lessons (
    id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    title            VARCHAR(200)     NOT NULL,
    form             TINYINT UNSIGNED NULL COMMENT '1-4',
    subject          VARCHAR(20)      NULL COMMENT 'mathematics | physics | chemistry | biology | english',
    summary          TEXT             NULL,
    why_it_matters   TEXT             NULL COMMENT 'Required before publishing',
    where_in_malawi  TEXT             NULL COMMENT 'Required before publishing',
    careers          TEXT             NULL COMMENT 'Required before publishing',
    try_at_home      TEXT             NULL COMMENT 'Required before publishing',
    status           VARCHAR(20)      NOT NULL DEFAULT 'draft' COMMENT 'draft | published',
    duration_ms      INT UNSIGNED     NOT NULL DEFAULT 0,
    size_bytes       INT UNSIGNED     NOT NULL DEFAULT 0,
    sha256           CHAR(64)         NOT NULL,
    parts            SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Number of <=9 MB files in storage/lessons/{id}/',
    has_voice        TINYINT(1)       NOT NULL DEFAULT 0,
    created_by       INT UNSIGNED     NULL,
    published_at     DATETIME         NULL,
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lessons_status (status, published_at),
    KEY idx_lessons_form_subject (form, subject),
    CONSTRAINT fk_lessons_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lesson_uploads (
    id             CHAR(32)         NOT NULL COMMENT 'Random upload id',
    user_id        INT UNSIGNED     NOT NULL,
    client_key     VARCHAR(100)     NOT NULL COMMENT 'The device''s own lesson id, so an interrupted upload resumes',
    title          VARCHAR(200)     NOT NULL,
    size_bytes     INT UNSIGNED     NOT NULL,
    sha256         CHAR(64)         NOT NULL,
    received_bytes INT UNSIGNED     NOT NULL DEFAULT 0,
    status         VARCHAR(20)      NOT NULL DEFAULT 'uploading' COMMENT 'uploading | complete',
    lesson_id      INT UNSIGNED     NULL,
    created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lesson_uploads_client (user_id, client_key, sha256),
    CONSTRAINT fk_lesson_uploads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_uploads_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
