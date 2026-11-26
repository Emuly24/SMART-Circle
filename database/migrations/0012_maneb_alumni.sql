-- SMART Circle v2: MANEB results, alumni, the alumni community and office hours.
-- Revision mode needs no table: it follows the MANEB dates already in calendar_events.

ALTER TABLE users
    MODIFY role VARCHAR(20) NOT NULL COMMENT 'tutor | student | partner | alumni';

CREATE TABLE maneb_results (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NOT NULL,
    exam            VARCHAR(10)      NOT NULL COMMENT 'jce (Form 2, A-F) | msce (Form 4, 1-9)',
    year            SMALLINT UNSIGNED NOT NULL,
    grades          JSON             NOT NULL COMMENT 'subject => grade, for SMART Circle subjects',
    subjects_sat    TINYINT UNSIGNED NULL COMMENT 'All subjects written, not only ours',
    subjects_passed TINYINT UNSIGNED NULL,
    points          TINYINT UNSIGNED NULL COMMENT 'MSCE: aggregate of the best six (6 = best)',
    reflection      TEXT             NULL COMMENT 'What helped / advice for those coming next',
    share           VARCHAR(20)      NOT NULL DEFAULT 'private' COMMENT 'private | summary | named',
    verified_at     DATETIME         NULL COMMENT 'Tutor saw the result slip',
    verified_by     INT UNSIGNED     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_maneb_results (user_id, exam, year),
    KEY idx_maneb_results_year (exam, year),
    CONSTRAINT fk_maneb_results_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_maneb_results_verified_by FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alumni_profiles (
    user_id      INT UNSIGNED NOT NULL,
    left_year    SMALLINT UNSIGNED NOT NULL COMMENT 'Year they finished Form 4',
    now_doing    VARCHAR(20)  NULL COMMENT 'waiting | university | college | tvet | working | repeating | other',
    institution  VARCHAR(150) NULL,
    programme    VARCHAR(150) NULL,
    needs        VARCHAR(200) NULL COMMENT 'Comma list: selection | applications | loans | course_choice | career | mentoring',
    can_mentor   TINYINT(1)   NOT NULL DEFAULT 0,
    mentor_note  VARCHAR(300) NULL,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_alumni_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE community_posts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    kind        VARCHAR(20)  NOT NULL COMMENT 'question | opportunity | story',
    title       VARCHAR(150) NOT NULL,
    body        TEXT         NOT NULL,
    for_students TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'Tutor shared it with Form 3-4 students (read only)',
    status      VARCHAR(20)  NOT NULL DEFAULT 'visible' COMMENT 'visible | hidden (moderated; never deleted)',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_reply_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_community_posts_list (status, last_reply_at),
    CONSTRAINT fk_community_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE community_replies (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    body       TEXT         NOT NULL,
    status     VARCHAR(20)  NOT NULL DEFAULT 'visible' COMMENT 'visible | hidden',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_community_replies_post (post_id, created_at),
    CONSTRAINT fk_community_replies_post FOREIGN KEY (post_id) REFERENCES community_posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_community_replies_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE office_hours (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    starts_at  DATETIME     NOT NULL,
    ends_at    DATETIME     NOT NULL,
    mode       VARCHAR(20)  NOT NULL COMMENT 'phone | whatsapp | messages',
    capacity   TINYINT UNSIGNED NOT NULL DEFAULT 4,
    audience   VARCHAR(20)  NOT NULL DEFAULT 'all' COMMENT 'all | form:N | alumni',
    note       VARCHAR(300) NULL,
    status     VARCHAR(20)  NOT NULL DEFAULT 'open' COMMENT 'open | cancelled',
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_office_hours_start (status, starts_at),
    CONSTRAINT fk_office_hours_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE office_hour_bookings (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slot_id      INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    question     VARCHAR(500) NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'booked' COMMENT 'booked | cancelled | done | missed',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_office_hour_bookings (slot_id, user_id),
    CONSTRAINT fk_office_hour_bookings_slot FOREIGN KEY (slot_id) REFERENCES office_hours (id) ON DELETE CASCADE,
    CONSTRAINT fk_office_hour_bookings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
