-- SMART Circle v2: more ways for qualified teachers to help.
--  * Question Contributor: partner teachers suggest questions; the tutor accepts them into the bank (credited) or declines.
--  * Guest Teacher: the tutor lets a partner teacher record lessons in their subjects; the tutor publishes them.
--  * Teachers check lessons (and their PDF handouts) as they check notes; every decision is kept.

CREATE TABLE question_suggestions (
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    partner_id   INT UNSIGNED      NULL,
    form         TINYINT UNSIGNED  NOT NULL,
    subject      VARCHAR(20)       NOT NULL,
    topic_id     INT UNSIGNED      NULL,
    type         VARCHAR(20)       NOT NULL,
    prompt       MEDIUMTEXT        NOT NULL,
    data         MEDIUMTEXT        NULL COMMENT 'JSON, as questions.data',
    marks        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    status       VARCHAR(10)       NOT NULL DEFAULT 'pending' COMMENT 'pending | accepted | declined',
    tutor_note   VARCHAR(500)      NULL,
    question_id  INT UNSIGNED      NULL COMMENT 'The question created when accepted',
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at   DATETIME          NULL,
    PRIMARY KEY (id),
    KEY idx_question_suggestions_status (status, created_at),
    KEY idx_question_suggestions_partner (partner_id),
    CONSTRAINT fk_question_suggestions_partner FOREIGN KEY (partner_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_question_suggestions_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL,
    CONSTRAINT fk_question_suggestions_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE partner_profiles
    ADD COLUMN guest_teacher TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'May record lessons in their subjects (the tutor publishes)',
    ADD COLUMN guest_since   DATETIME   NULL;

ALTER TABLE lessons
    ADD COLUMN guest_submitted_at  DATETIME NULL COMMENT 'A guest teacher asked the tutor to publish',
    ADD COLUMN review_requested_at DATETIME NULL COMMENT 'Sent to partner teachers to check';

CREATE TABLE lesson_reviews (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lesson_id      INT UNSIGNED NOT NULL,
    reviewer_id    INT UNSIGNED NULL,
    decision       VARCHAR(10)  NOT NULL COMMENT 'approved | changes',
    comment        TEXT         NULL,
    content_hash   CHAR(64)     NOT NULL COMMENT 'The lesson (recording, details, handout) as it was checked',
    reviewer_name  VARCHAR(150) NOT NULL,
    school_name    VARCHAR(150) NULL,
    signature_path VARCHAR(255) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lesson_reviews_lesson (lesson_id, id),
    CONSTRAINT fk_lesson_reviews_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
