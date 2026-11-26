-- SMART Circle v2: notes and slides with checkpoint exercises, encouraging feedback, re-checks.

CREATE TABLE notes (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    kind       VARCHAR(20)      NOT NULL DEFAULT 'notes' COMMENT 'notes | slides',
    title      VARCHAR(200)     NOT NULL,
    form       TINYINT UNSIGNED NOT NULL,
    subject    VARCHAR(20)      NOT NULL,
    topic_id   INT UNSIGNED     NOT NULL COMMENT 'Honesty rule: opens when the student''s school has taught it',
    summary    TEXT             NULL,
    status     VARCHAR(20)      NOT NULL DEFAULT 'draft' COMMENT 'draft | published',
    created_by INT UNSIGNED     NULL,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_form (form, subject, status),
    CONSTRAINT fk_notes_topic FOREIGN KEY (topic_id) REFERENCES topics (id),
    CONSTRAINT fk_notes_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE note_blocks (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    note_id       INT UNSIGNED  NOT NULL,
    sort_order    SMALLINT      NOT NULL DEFAULT 0,
    type          VARCHAR(20)   NOT NULL COMMENT 'text | image | checkpoint',
    body          MEDIUMTEXT    NULL COMMENT 'text: simple markup; image: caption',
    image_path    VARCHAR(255)  NULL COMMENT 'In storage/, served only through the access check',
    assessment_id INT UNSIGNED  NULL COMMENT 'checkpoint: the exercise (assessments.kind = note_check)',
    is_final      TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'End-of-unit exercise',
    PRIMARY KEY (id),
    KEY idx_note_blocks_note (note_id, sort_order),
    CONSTRAINT fk_note_blocks_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE,
    CONSTRAINT fk_note_blocks_assessment FOREIGN KEY (assessment_id) REFERENCES assessments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assessments
    ADD COLUMN note_id INT UNSIGNED NULL COMMENT 'Checkpoint exercises inside notes' AFTER lesson_id,
    ADD KEY idx_assessments_note (note_id),
    ADD CONSTRAINT fk_assessments_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE;

-- Encouraging feedback: what went well, where it went off track, and the next step.
ALTER TABLE submission_answers
    ADD COLUMN did_well  TEXT NULL AFTER feedback,
    ADD COLUMN off_track TEXT NULL AFTER did_well,
    ADD COLUMN next_step TEXT NULL AFTER off_track;

CREATE TABLE recheck_requests (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id INT UNSIGNED NOT NULL,
    question_id   INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    reason        TEXT         NOT NULL COMMENT 'Why the student believes the answer deserves more marks',
    status        VARCHAR(20)  NOT NULL DEFAULT 'open' COMMENT 'open | upheld | changed',
    old_score     DECIMAL(6,2) NULL,
    new_score     DECIMAL(6,2) NULL,
    tutor_reply   TEXT         NULL,
    resolved_by   INT UNSIGNED NULL,
    resolved_at   DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recheck_status (status, created_at),
    KEY idx_recheck_submission (submission_id, question_id),
    CONSTRAINT fk_recheck_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE,
    CONSTRAINT fk_recheck_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE,
    CONSTRAINT fk_recheck_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_recheck_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
