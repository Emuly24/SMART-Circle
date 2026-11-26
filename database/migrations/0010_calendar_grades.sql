-- SMART Circle v2: the academic calendar (measurable terms), grades and term reports.

CREATE TABLE academic_years (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(20)  NOT NULL COMMENT 'e.g. 2026/27',
    starts_on  DATE         NOT NULL,
    ends_on    DATE         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_years_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE terms (
    id        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    year_id   INT UNSIGNED     NOT NULL,
    number    TINYINT UNSIGNED NOT NULL COMMENT '1-3',
    opens_on  DATE             NOT NULL,
    closes_on DATE             NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_terms (year_id, number),
    KEY idx_terms_dates (opens_on, closes_on),
    CONSTRAINT fk_terms_year FOREIGN KEY (year_id) REFERENCES academic_years (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_events (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id  INT UNSIGNED NULL COMMENT 'NULL = national (Ministry / MANEB); otherwise one school''s own dates',
    kind       VARCHAR(20)  NOT NULL COMMENT 'jce | msce | midterm | mock | school_exam | holiday | other',
    title      VARCHAR(150) NOT NULL,
    starts_on  DATE         NOT NULL,
    ends_on    DATE         NOT NULL,
    forms      VARCHAR(20)  NULL COMMENT 'Comma list of forms it concerns, NULL = all',
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_calendar_events_dates (starts_on, ends_on),
    KEY idx_calendar_events_school (school_id),
    CONSTRAINT fk_calendar_events_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_events_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grade_bands (
    scale       VARCHAR(10)      NOT NULL COMMENT 'jce (Forms 1-2, letters) | msce (Forms 3-4, points)',
    grade       VARCHAR(5)       NOT NULL,
    min_percent TINYINT UNSIGNED NOT NULL,
    descriptor  VARCHAR(40)      NOT NULL,
    PRIMARY KEY (scale, grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE term_reports (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    term_id       INT UNSIGNED NOT NULL,
    data          MEDIUMTEXT   NOT NULL COMMENT 'JSON snapshot: subjects, categories, grades, comment',
    comment       TEXT         NULL COMMENT 'Tutor''s overall comment',
    document_code VARCHAR(20)  NULL COMMENT 'Printable, verifiable report (issued_documents.code)',
    issued_by     INT UNSIGNED NULL,
    issued_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_term_reports (user_id, term_id),
    CONSTRAINT fk_term_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_term_reports_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
    CONSTRAINT fk_term_reports_issued_by FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2026/27, as published by the Ministry of Education (harmonised for all institutions).
INSERT INTO academic_years (name, starts_on, ends_on) VALUES ('2026/27', '2026-09-14', '2027-07-16');
SET @year = LAST_INSERT_ID();
INSERT INTO terms (year_id, number, opens_on, closes_on) VALUES
    (@year, 1, '2026-09-14', '2026-12-18'),
    (@year, 2, '2027-01-04', '2027-03-25'),
    (@year, 3, '2027-04-12', '2027-07-16');
INSERT INTO calendar_events (school_id, kind, title, starts_on, ends_on, forms) VALUES
    (NULL, 'jce', 'MANEB Junior Certificate of Education (JCE)', '2027-05-24', '2027-06-02', '2'),
    (NULL, 'msce', 'MANEB Malawi School Certificate of Education (MSCE)', '2027-06-21', '2027-07-12', '4');

-- Default grade bands (editable in Studio > Grades > Settings).
INSERT INTO grade_bands (scale, grade, min_percent, descriptor) VALUES
    ('jce', 'A', 75, 'Excellent'), ('jce', 'B', 65, 'Very good'), ('jce', 'C', 55, 'Good'),
    ('jce', 'D', 40, 'Pass'), ('jce', 'F', 0, 'Not yet a pass'),
    ('msce', '1', 75, 'Distinction'), ('msce', '2', 70, 'Distinction'), ('msce', '3', 65, 'Strong credit'),
    ('msce', '4', 60, 'Credit'), ('msce', '5', 55, 'Credit'), ('msce', '6', 50, 'Credit'),
    ('msce', '7', 45, 'Pass'), ('msce', '8', 40, 'Pass'), ('msce', '9', 0, 'Not yet a pass');

INSERT INTO settings (name, value) VALUES
    ('grade_weights', '{"exercise":15,"assignment":20,"test":25,"exam":40}')
ON DUPLICATE KEY UPDATE name = name;
