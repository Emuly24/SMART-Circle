-- SMART Circle v2: the SMART Circle Team (volunteer tutors), practice accounts for the
-- "Try as" test mode, seats per form and gender, and programme settings.

ALTER TABLE users
    MODIFY role VARCHAR(20) NOT NULL COMMENT 'tutor (admin) | team | student | partner | alumni',
    ADD COLUMN is_practice TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Practice account for the admin''s "Try as" test mode' AFTER status;

ALTER TABLE schools
    ADD COLUMN is_practice TINYINT(1) NOT NULL DEFAULT 0 AFTER district;

ALTER TABLE intakes
    ADD COLUMN seat_plan JSON NULL COMMENT '{"1":{"female":3,"male":3},...}; NULL = seats_per_group everywhere' AFTER seats_per_group;

CREATE TABLE team_profiles (
    user_id           INT UNSIGNED NOT NULL,
    subjects          VARCHAR(100) NOT NULL COMMENT 'Comma list of subjects they teach',
    status            VARCHAR(20)  NOT NULL DEFAULT 'invited' COMMENT 'invited | active | declined | removed',
    agreement_version VARCHAR(20)  NULL,
    agreed_at         DATETIME     NULL,
    declined_at       DATETIME     NULL,
    invited_by        INT UNSIGNED NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_team_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_team_profiles_invited_by FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (name, value) VALUES ('tutor_whatsapp', '+265881193707'), ('whatsapp_community', '');
