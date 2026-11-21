-- SMART Circle v2: teach only what each student's school has taught.
-- Who reported coverage, students' own reports, per-student early access, and why each view was allowed.

ALTER TABLE topic_coverage
    ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'partner' COMMENT 'partner | tutor (after a phone call)' AFTER status;

ALTER TABLE schools
    ADD COLUMN coverage_checked_at DATETIME NULL COMMENT 'Partner confirmed "nothing changed since my last report"';

CREATE TABLE student_topic_reports (
    user_id     INT UNSIGNED NOT NULL,
    topic_id    INT UNSIGNED NOT NULL,
    status      VARCHAR(20)  NOT NULL COMMENT 'covered (my class has) | not_yet (my class has not, overrides the school)',
    review      VARCHAR(20)  NULL COMMENT 'NULL = not checked | confirmed | rejected (tutor)',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, topic_id),
    KEY idx_student_topic_reports_review (status, review),
    CONSTRAINT fk_student_topic_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_student_topic_reports_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE,
    CONSTRAINT fk_student_topic_reports_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topic_exceptions (
    user_id    INT UNSIGNED NOT NULL,
    topic_id   INT UNSIGNED NOT NULL,
    reason     TEXT         NOT NULL COMMENT 'Why the student needs it before the school teaches it',
    status     VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | declined',
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, topic_id),
    KEY idx_topic_exceptions_status (status, created_at),
    CONSTRAINT fk_topic_exceptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_exceptions_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_exceptions_decided_by FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lesson_views
    ADD COLUMN access_basis VARCHAR(20) NULL COMMENT 'school | self | exception: why the student could watch' AFTER completed_at;
