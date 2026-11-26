-- SMART Circle v2: who our students are, and the pledge they make.
-- Application questionnaire answers, sign-up details (incl. evening classes), signed pledges.

ALTER TABLE applications
    ADD COLUMN answers TEXT NULL COMMENT 'JSON: questionnaire (reasons for schooling, career, habits, scenarios...)' AFTER motivation;

ALTER TABLE student_profiles
    ADD COLUMN study_mode  VARCHAR(20) NULL COMMENT 'day | boarder | self_boarder | evening' AFTER school_id,
    ADD COLUMN school_area VARCHAR(120) NULL COMMENT 'Area / trading centre / TA of the school' AFTER study_mode,
    ADD COLUMN travel_time VARCHAR(20) NULL COMMENT 'Day/evening students: time to reach school' AFTER school_area,
    ADD COLUMN home_study  VARCHAR(20) NULL COMMENT 'Study time available outside class' AFTER travel_time;

CREATE TABLE pledges (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    version       VARCHAR(20)  NOT NULL COMMENT 'Pledge wording version, e.g. 2026-1',
    period        VARCHAR(40)  NULL COMMENT 'Term it covers (set once the academic calendar exists)',
    statements    TEXT         NOT NULL COMMENT 'JSON snapshot of the vows as shown',
    signed_name   VARCHAR(150) NOT NULL COMMENT 'Name the student typed as a signature',
    guardian_name VARCHAR(150) NULL COMMENT 'Guardian who knows about the pledge',
    document_code VARCHAR(20)  NULL COMMENT 'Printable certificate (issued_documents.code)',
    ip            VARCHAR(45)  NOT NULL DEFAULT '',
    accepted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pledges_user (user_id, accepted_at),
    CONSTRAINT fk_pledges_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
