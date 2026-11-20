-- =============================================================================
-- SMART Circle — database schema (reconstructed from the PHP code)
-- =============================================================================
--
-- STATUS: RECONSTRUCTED, NOT EXPORTED.
--   The live database could not be reached, so every table, column and type
--   below was inferred from how the PHP code reads and writes it (audit §2.2.2).
--   When the live DB is reachable again, export it (SHOW CREATE TABLE ...) and
--   compare it with this file before running anything against production.
--   Do NOT run this file against the live database without that comparison.
--
-- TARGET: MariaDB 10.4+ / MySQL 8.0+, InnoDB, utf8mb4 (matches
--   src/Data/Database.php: SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci).
--
-- CONVENTIONS
--   * Every table has an INT UNSIGNED AUTO_INCREMENT `id` primary key,
--     except `sessions` (string id) and `admin_settings` (key/value).
--   * Status-like columns are VARCHAR (not ENUM) so unexpected legacy values
--     never make an INSERT fail; the values the code uses are listed in COMMENTs.
--   * `groups` is a reserved word in MySQL 8.0.2+, so it is always quoted.
--   * Rows owned by a user are deleted with that user (ON DELETE CASCADE),
--     because the admin pages delete users without cleaning up child rows.
--   * Lines marked "ASSUMPTION" are guesses the code does not settle
--     (types, defaults, uniqueness). Check them against the live DB.
--
-- The file is idempotent (CREATE TABLE IF NOT EXISTS), and tables are created
-- in foreign-key dependency order.
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Users, applications, sessions, settings
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username                 VARCHAR(50)   NOT NULL,
    fullname                 VARCHAR(150)  NOT NULL,
    email                    VARCHAR(190)  NOT NULL DEFAULT '' COMMENT 'Optional at signup: stored as empty string, so NOT unique',
    phone                    VARCHAR(20)   NOT NULL,
    parent_phone             VARCHAR(20)   NULL,
    password                 VARCHAR(255)  NOT NULL COMMENT 'password_hash() output',
    role                     VARCHAR(20)   NOT NULL DEFAULT 'student' COMMENT 'student | admin',
    approved                 TINYINT(1)    NOT NULL DEFAULT 0,
    consent_signed           TINYINT(1)    NOT NULL DEFAULT 0,
    consent_signed_at        DATETIME      NULL,
    status                   VARCHAR(20)   NOT NULL DEFAULT 'active' COMMENT 'active | suspended | dismissed',
    suspension_end           DATETIME      NULL COMMENT 'Set with DATE_ADD(NOW(), ...) and compared with CURDATE()',
    class_level              VARCHAR(10)   NULL COMMENT 'Form 3 | Form 4',
    route                    VARCHAR(50)   NULL COMMENT 'Subject route chosen in ApplyController::determineRoute()',
    gender                   VARCHAR(10)   NULL COMMENT 'Male | Female',
    dob                      DATE          NULL,
    school                   VARCHAR(150)  NULL,
    subjects                 TEXT          NULL COMMENT 'Subjects taken (comma-separated string)',
    address                  VARCHAR(255)  NULL,
    profile_pic              VARCHAR(255)  NULL,
    testimonial_prompt_shown TINYINT(1)    NOT NULL DEFAULT 0,
    created_at               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_phone (phone),
    KEY idx_users_email (email),
    KEY idx_users_approved_status (approved, status),
    KEY idx_users_class_route (class_level, route)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS universities (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_universities_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED     NOT NULL,
    ambition            VARCHAR(150)     NULL,
    career_reason       TEXT             NULL,
    university          VARCHAR(190)     NULL COMMENT 'Free text (name), not an FK to universities',
    why_join            TEXT             NULL,
    subject_assist      TEXT             NULL COMMENT 'Subjects needing help (comma-separated string)',
    target_points       TINYINT UNSIGNED NULL COMMENT 'Validated 0-20 in ApplyController',
    seriousness_answers TEXT             NULL COMMENT 'JSON',
    status              VARCHAR(20)      NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
    admin_notes         TEXT             NULL,
    submitted_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_applications_user (user_id) COMMENT 'One application per user (code updates it in place)',
    KEY idx_applications_status (status, submitted_at),
    CONSTRAINT fk_applications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database session store used by config.php (sess_read / sess_write / sess_gc).
CREATE TABLE IF NOT EXISTS sessions (
    id            VARCHAR(128) NOT NULL,
    data          MEDIUMTEXT   NOT NULL,
    last_accessed DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sessions_last_accessed (last_accessed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED    NULL COMMENT 'NULL after the user is deleted (keeps the audit trail)',
    action     VARCHAR(100)    NOT NULL,
    details    TEXT            NULL,
    ip_address VARCHAR(45)     NOT NULL DEFAULT '' COMMENT 'IPv4 or IPv6',
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_log_user_created (user_id, created_at),
    KEY idx_activity_log_action (action),
    KEY idx_activity_log_created (created_at),
    CONSTRAINT fk_activity_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_settings (
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT         NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Groups and content locks
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `groups` (
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    class_level  VARCHAR(10)       NOT NULL COMMENT 'Form 3 | Form 4',
    route        VARCHAR(50)       NOT NULL DEFAULT '',
    group_number SMALLINT UNSIGNED NOT NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_groups_class_route_number (class_level, route, group_number),
    KEY idx_groups_route (route)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_members (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id   INT UNSIGNED NOT NULL,
    group_id  INT UNSIGNED NOT NULL,
    joined_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_group_members_user (user_id) COMMENT 'A student belongs to one group (code reads a single group_id per user)',
    KEY idx_group_members_group (group_id),
    CONSTRAINT fk_group_members_user  FOREIGN KEY (user_id)  REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Polymorphic lock: content_id points at notes / quizzes / exams / assignments
-- depending on content_type, so it cannot have a foreign key.
CREATE TABLE IF NOT EXISTS group_content_locks (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id     INT UNSIGNED NOT NULL,
    content_type VARCHAR(20)  NOT NULL COMMENT 'note | quiz | exam | assignment',
    content_id   INT UNSIGNED NOT NULL,
    is_locked    TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_group_content_locks (group_id, content_type, content_id) COMMENT 'Required by ON DUPLICATE KEY UPDATE in admin pages',
    KEY idx_group_content_locks_content (content_type, content_id),
    CONSTRAINT fk_group_content_locks_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_meetings (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id     INT UNSIGNED NOT NULL,
    meeting_date DATE         NOT NULL,
    start_time   TIME         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_group_meetings_group_date (group_id, meeting_date),
    CONSTRAINT fk_group_meetings_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Notes and note exercises
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS notes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(255) NOT NULL,
    subject     VARCHAR(100) NOT NULL,
    class_level VARCHAR(10)  NOT NULL,
    content     LONGTEXT     NULL COMMENT 'Rich-text HTML from the note editor',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_class_subject (class_level, subject),
    KEY idx_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS note_exercises (
    id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    note_id    INT UNSIGNED      NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    question   TEXT              NOT NULL,
    points     SMALLINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'ASSUMPTION: default; the code never inserts it',
    PRIMARY KEY (id),
    UNIQUE KEY uq_note_exercises_note_order (note_id, sort_order) COMMENT 'admin_note_editor looks exercises up by (note_id, sort_order)',
    CONSTRAINT fk_note_exercises_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS note_sections (
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    note_id      INT UNSIGNED      NOT NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    section_type VARCHAR(20)       NOT NULL COMMENT 'introduction | exercise',
    content      LONGTEXT          NULL,
    exercise_id  INT UNSIGNED      NULL,
    PRIMARY KEY (id),
    KEY idx_note_sections_note_order (note_id, sort_order),
    KEY idx_note_sections_exercise (exercise_id),
    CONSTRAINT fk_note_sections_note     FOREIGN KEY (note_id)     REFERENCES notes (id)          ON DELETE CASCADE,
    CONSTRAINT fk_note_sections_exercise FOREIGN KEY (exercise_id) REFERENCES note_exercises (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single auto-save draft for the admin note editor (the code DELETEs all rows, then inserts one).
CREATE TABLE IF NOT EXISTS note_drafts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(255) NULL,
    subject     VARCHAR(100) NULL,
    class_level VARCHAR(10)  NULL,
    content     LONGTEXT     NULL,
    saved_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: no code in the repo INSERTs into this table; it is only read and updated.
-- The page that creates attempts appears to be missing.
CREATE TABLE IF NOT EXISTS exercise_attempts (
    id                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id                INT UNSIGNED  NOT NULL,
    exercise_id            INT UNSIGNED  NOT NULL,
    answer_text            TEXT          NULL,
    answer_file_path       VARCHAR(255)  NULL,
    status                 VARCHAR(20)   NOT NULL DEFAULT 'submitted' COMMENT 'submitted | paper_pending | marked',
    marks_awarded          DECIMAL(6,2)  NULL,
    feedback               TEXT          NULL,
    promised_at            DATETIME      NULL COMMENT 'When the student promised a paper submission',
    reminder_sent          TINYINT(1)    NOT NULL DEFAULT 0,
    warning_sent           TINYINT(1)    NOT NULL DEFAULT 0,
    suspended_for_exercise TINYINT(1)    NOT NULL DEFAULT 0,
    created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exercise_attempts_user_exercise (user_id, exercise_id),
    KEY idx_exercise_attempts_exercise (exercise_id),
    KEY idx_exercise_attempts_status_promised (status, promised_at),
    CONSTRAINT fk_exercise_attempts_user     FOREIGN KEY (user_id)     REFERENCES users (id)          ON DELETE CASCADE,
    CONSTRAINT fk_exercise_attempts_exercise FOREIGN KEY (exercise_id) REFERENCES note_exercises (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Quizzes (attached to notes)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS quizzes (
    id                 INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    note_id            INT UNSIGNED      NOT NULL,
    title              VARCHAR(255)      NOT NULL,
    description        TEXT              NULL,
    time_limit         SMALLINT UNSIGNED NULL COMMENT 'Minutes',
    passing_percentage TINYINT UNSIGNED  NOT NULL DEFAULT 50,
    created_at         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quizzes_note (note_id),
    CONSTRAINT fk_quizzes_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_questions (
    id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    quiz_id        INT UNSIGNED      NOT NULL,
    question_text  TEXT              NOT NULL,
    question_type  VARCHAR(20)       NOT NULL DEFAULT 'multiple_choice' COMMENT 'multiple_choice | true_false | short_answer',
    options        TEXT              NULL COMMENT 'JSON array of choices',
    correct_answer TEXT              NULL,
    points         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_quiz_questions_quiz_order (quiz_id, sort_order),
    CONSTRAINT fk_quiz_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    quiz_id      INT UNSIGNED NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'in_progress' COMMENT 'in_progress | submitted | marked (ASSUMPTION: in_progress default)',
    score        DECIMAL(7,2) NULL,
    started_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME     NULL COMMENT 'Used by admin_get_groups.php for 30-day quiz averages',
    PRIMARY KEY (id),
    UNIQUE KEY uq_quiz_attempts_user_quiz (user_id, quiz_id) COMMENT 'take_quiz.php keeps one attempt per user per quiz',
    KEY idx_quiz_attempts_quiz (quiz_id),
    CONSTRAINT fk_quiz_attempts_user FOREIGN KEY (user_id) REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT fk_quiz_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: no code in the repo INSERTs into this table; it is only read and updated.
CREATE TABLE IF NOT EXISTS quiz_answers (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id     INT UNSIGNED NOT NULL,
    question_id    INT UNSIGNED NOT NULL,
    user_answer    TEXT         NULL,
    is_correct     TINYINT(1)   NOT NULL DEFAULT 0,
    points_awarded DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'admin_mark_quiz finds unmarked short answers by points_awarded = 0',
    PRIMARY KEY (id),
    UNIQUE KEY uq_quiz_answers_attempt_question (attempt_id, question_id),
    KEY idx_quiz_answers_question (question_id),
    CONSTRAINT fk_quiz_answers_attempt  FOREIGN KEY (attempt_id)  REFERENCES quiz_attempts (id)  ON DELETE CASCADE,
    CONSTRAINT fk_quiz_answers_question FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Exams
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS exams (
    id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    title            VARCHAR(255)      NOT NULL,
    subject          VARCHAR(100)      NOT NULL,
    class_level      VARCHAR(10)       NOT NULL,
    description      TEXT              NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exams_class_subject (class_level, subject),
    KEY idx_exams_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_questions (
    id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    exam_id        INT UNSIGNED      NOT NULL,
    question_text  TEXT              NOT NULL,
    question_type  VARCHAR(20)       NOT NULL DEFAULT 'multiple_choice' COMMENT 'multiple_choice | true_false | short_answer | essay',
    options        TEXT              NULL COMMENT 'JSON array of choices',
    correct_answer TEXT              NULL,
    points         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_exam_questions_exam_order (exam_id, sort_order),
    CONSTRAINT fk_exam_questions_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_submissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'in_progress' COMMENT 'in_progress | submitted | marked (ASSUMPTION: in_progress default)',
    start_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time    DATETIME     NULL,
    total_score DECIMAL(7,2) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_submissions_exam_user (exam_id, user_id),
    KEY idx_exam_submissions_user (user_id),
    KEY idx_exam_submissions_status (status),
    CONSTRAINT fk_exam_submissions_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
    CONSTRAINT fk_exam_submissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_answers (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id          INT UNSIGNED NOT NULL,
    question_id      INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    answer_text      TEXT         NULL,
    answer_file_path VARCHAR(255) NULL,
    marks_awarded    DECIMAL(6,2) NULL,
    feedback         TEXT         NULL,
    marked_by_admin  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_answers_exam_question_user (exam_id, question_id, user_id),
    KEY idx_exam_answers_user (user_id),
    KEY idx_exam_answers_question (question_id),
    CONSTRAINT fk_exam_answers_exam     FOREIGN KEY (exam_id)     REFERENCES exams (id)          ON DELETE CASCADE,
    CONSTRAINT fk_exam_answers_question FOREIGN KEY (question_id) REFERENCES exam_questions (id) ON DELETE CASCADE,
    CONSTRAINT fk_exam_answers_user     FOREIGN KEY (user_id)     REFERENCES users (id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Assignments
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS assignments (
    id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    title                VARCHAR(255)      NOT NULL,
    description          TEXT              NULL,
    attachment_file_path VARCHAR(255)      NULL,
    subject              VARCHAR(100)      NOT NULL,
    class_level          VARCHAR(10)       NOT NULL,
    due_date             DATETIME          NULL COMMENT 'NULL for assignments extracted from note exercises',
    note_id              INT UNSIGNED      NULL COMMENT 'Set when created from a note exercise',
    exercise_sort_order  SMALLINT UNSIGNED NULL,
    created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assignments_class_subject (class_level, subject),
    KEY idx_assignments_due (due_date),
    KEY idx_assignments_note (note_id),
    CONSTRAINT fk_assignments_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_submissions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id   INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    submission_text TEXT         NULL,
    file_path       VARCHAR(255) NULL,
    marks           DECIMAL(6,2) NULL,
    feedback        TEXT         NULL,
    marked_by_admin TINYINT(1)   NOT NULL DEFAULT 0,
    submitted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assignment_submissions_assignment_user (assignment_id, user_id),
    KEY idx_assignment_submissions_user (user_id),
    CONSTRAINT fk_assignment_submissions_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_assignment_submissions_user       FOREIGN KEY (user_id)       REFERENCES users (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Library (books, annotations, reviews, questions)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS books (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(255) NOT NULL,
    subject     VARCHAR(100) NULL,
    class_level VARCHAR(10)  NULL,
    file_path   VARCHAR(500) NOT NULL COMMENT 'Local upload path or external URL (admin_resources.php stores links here)',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_books_class_subject (class_level, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_annotations (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED      NOT NULL,
    book_id         INT UNSIGNED      NOT NULL,
    page_number     SMALLINT UNSIGNED NOT NULL,
    annotation_type VARCHAR(20)       NOT NULL COMMENT 'e.g. highlight | note',
    content         TEXT              NULL,
    position        TEXT              NULL COMMENT 'JSON coordinates',
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_book_annotations_user_book_page (user_id, book_id, page_number),
    KEY idx_book_annotations_book (book_id),
    CONSTRAINT fk_book_annotations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_book_annotations_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_reviews (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED     NOT NULL,
    book_id    INT UNSIGNED     NOT NULL,
    rating     TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    review     TEXT             NULL,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_book_reviews_user_book (user_id, book_id) COMMENT 'Required by ON DUPLICATE KEY UPDATE in submit_book_review.php',
    KEY idx_book_reviews_book (book_id),
    CONSTRAINT fk_book_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_book_reviews_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_questions (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED      NOT NULL,
    book_id       INT UNSIGNED      NULL COMMENT 'book_title is copied so the question survives the book being deleted',
    book_title    VARCHAR(255)      NULL,
    page_number   SMALLINT UNSIGNED NULL,
    selected_text TEXT              NULL,
    question      TEXT              NOT NULL,
    answer        TEXT              NULL,
    status        VARCHAR(20)       NOT NULL DEFAULT 'pending' COMMENT 'pending | answered',
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_book_questions_user (user_id),
    KEY idx_book_questions_book (book_id),
    KEY idx_book_questions_status_created (status, created_at),
    CONSTRAINT fk_book_questions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_book_questions_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Messaging, feedback and requests
-- -----------------------------------------------------------------------------

-- Admin → student in-app notifications (there is no email on InfinityFree).
CREATE TABLE IF NOT EXISTS admin_messages (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL COMMENT 'Recipient',
    message        TEXT         NOT NULL,
    recipient_type VARCHAR(20)  NOT NULL DEFAULT 'student',
    sent_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at        DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_admin_messages_user_read (user_id, read_at),
    KEY idx_admin_messages_sent (sent_at),
    CONSTRAINT fk_admin_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student → admin messages.
CREATE TABLE IF NOT EXISTS student_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    message    TEXT         NOT NULL,
    status     VARCHAR(20)  NOT NULL DEFAULT 'unread' COMMENT 'unread | read (ASSUMPTION: unread default)',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_messages_user (user_id),
    KEY idx_student_messages_created (created_at),
    CONSTRAINT fk_student_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_templates (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(255) NOT NULL,
    category   VARCHAR(100) NOT NULL,
    message    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_templates_title_category (title, category) COMMENT 'Import checks (title, category) before inserting',
    KEY idx_message_templates_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED     NOT NULL,
    fullname    VARCHAR(150)     NOT NULL COMMENT 'Copied from users at submission time',
    class_level VARCHAR(10)      NULL,
    testimonial TEXT             NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    status      VARCHAR(20)      NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME         NULL,
    PRIMARY KEY (id),
    KEY idx_testimonials_user (user_id),
    KEY idx_testimonials_status_approved (status, approved_at),
    CONSTRAINT fk_testimonials_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topic_requests (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    subject     VARCHAR(100) NOT NULL,
    topic       VARCHAR(255) NOT NULL,
    class_level VARCHAR(10)  NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_topic_requests_user_subject (user_id, subject),
    KEY idx_topic_requests_class_subject_topic (class_level, subject, topic),
    CONSTRAINT fk_topic_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topics_covered (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject      VARCHAR(100) NOT NULL,
    topic        VARCHAR(255) NOT NULL,
    class_level  VARCHAR(10)  NOT NULL,
    covered_date DATE         NOT NULL,
    PRIMARY KEY (id),
    KEY idx_topics_covered_class_subject_topic (class_level, subject, topic),
    KEY idx_topics_covered_date (covered_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_resources (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    subject       VARCHAR(100) NULL,
    title         VARCHAR(255) NOT NULL,
    description   TEXT         NULL,
    file_paths    TEXT         NULL COMMENT 'JSON array of uploaded file paths',
    external_url  VARCHAR(500) NULL,
    resource_type VARCHAR(30)  NULL COMMENT 'e.g. book | past_paper | note (admin_resources.php wrongly reads it as `type`)',
    status        VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
    is_essential  TINYINT(1)   NOT NULL DEFAULT 0,
    admin_notes   TEXT         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at   DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_student_resources_user (user_id),
    KEY idx_student_resources_status (status, created_at),
    CONSTRAINT fk_student_resources_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_reports (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    report_type    VARCHAR(50)  NOT NULL,
    description    TEXT         NOT NULL,
    incident_date  DATE         NULL,
    status         VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'Set by admin_reports.php',
    admin_response TEXT         NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_reports_user (user_id),
    KEY idx_student_reports_created (created_at),
    CONSTRAINT fk_student_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subject_questions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    subject     VARCHAR(100) NOT NULL,
    question    TEXT         NOT NULL,
    answer      TEXT         NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending | answered',
    answered_by INT UNSIGNED NULL COMMENT 'Hard-coded to 1 by admin_subject_questions.php; no FK (admin is not always a users row)',
    answered_at DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_subject_questions_user (user_id),
    KEY idx_subject_questions_status_created (status, created_at),
    CONSTRAINT fk_subject_questions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Attendance and discipline
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS attendance (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    class_level     VARCHAR(10)  NULL,
    date            DATE         NOT NULL,
    status          VARCHAR(20)  NOT NULL COMMENT 'on_time | late | absent (dashboard.php also counts legacy "present")',
    arrival_time    TIME         NULL,
    remarks         VARCHAR(255) NULL,
    late_reason     TEXT         NULL,
    marked_by_admin TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_user_date (user_id, date) COMMENT 'One record per student per day',
    KEY idx_attendance_date_status (date, status),
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discipline_log (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    action         VARCHAR(30)  NOT NULL COMMENT 'e.g. warning | suspension | dismissal | reinstatement',
    reason         TEXT         NULL,
    suspension_end DATE         NULL,
    admin_notes    TEXT         NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_discipline_log_user_created (user_id, created_at),
    CONSTRAINT fk_discipline_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Self-assessment quizzes
-- -----------------------------------------------------------------------------

-- NOTE: no admin page INSERTs into this table; questions must be seeded by SQL.
CREATE TABLE IF NOT EXISTS self_quizzes (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject        VARCHAR(100) NOT NULL,
    question       TEXT         NOT NULL,
    option_a       VARCHAR(255) NULL,
    option_b       VARCHAR(255) NULL,
    option_c       VARCHAR(255) NULL,
    option_d       VARCHAR(255) NULL,
    correct_answer CHAR(1)      NOT NULL COMMENT 'A | B | C | D',
    explanation    TEXT         NULL,
    PRIMARY KEY (id),
    KEY idx_self_quizzes_subject (subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS self_quiz_attempts (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED      NOT NULL,
    subject         VARCHAR(100)      NOT NULL,
    score           SMALLINT UNSIGNED NOT NULL,
    total_questions SMALLINT UNSIGNED NOT NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_self_quiz_attempts_user_subject (user_id, subject),
    CONSTRAINT fk_self_quiz_attempts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Admin note-editor libraries
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS equations_library (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(255) NOT NULL,
    latex      TEXT         NOT NULL,
    category   VARCHAR(100) NOT NULL DEFAULT 'General',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_equations_library_category_title (category, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diagrams_library (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(255) NOT NULL,
    file_path  VARCHAR(255) NOT NULL,
    category   VARCHAR(100) NOT NULL DEFAULT 'General',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diagrams_library_category_title (category, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_research_notes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    note_text  TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_research_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- End of schema: 45 tables.
-- The admin password is NOT seeded here: config.php getAdminHash() creates the
-- `admin_hash` row on first use. Set a real password via admin_settings.php
-- (and see secrets.example.php) before going live.
-- =============================================================================
