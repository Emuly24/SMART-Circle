-- SMART Circle v2 — the complete platform schema (everything beyond accounts and lessons).
-- Tables are grouped by feature. Features not built yet already have their tables so the
-- database never needs restructuring when they arrive.
--
-- Conventions: InnoDB, utf8mb4; status columns are short VARCHARs (values in COMMENTs);
-- subject keys are mathematics | physics | chemistry | biology | english;
-- forms are 1-4; sets of subjects are stored as comma-separated keys.

-- =====================================================================
-- 1. Schools and intakes
-- =====================================================================

CREATE TABLE schools (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150) NOT NULL,
    district   VARCHAR(60)  NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schools_name_district (name, district)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intakes (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100)     NOT NULL,
    opens_at        DATETIME         NOT NULL,
    closes_at       DATETIME         NULL COMMENT 'NULL = open until closed by the tutor',
    seats_per_group TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Seats per form per gender (3 girls + 3 boys)',
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intakes_active (is_active, opens_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. Applications and the entry quiz
-- =====================================================================

CREATE TABLE applications (
    id                    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    intake_id             INT UNSIGNED      NOT NULL,
    reference             VARCHAR(20)       NOT NULL COMMENT 'e.g. SC-27-7KQ4M; shown to the applicant',
    status                VARCHAR(20)       NOT NULL DEFAULT 'submitted' COMMENT 'submitted | shortlisted | admitted | waitlisted | declined | withdrawn',
    full_name             VARCHAR(150)      NOT NULL,
    gender                VARCHAR(10)       NOT NULL COMMENT 'female | male',
    form                  TINYINT UNSIGNED  NOT NULL,
    track                 VARCHAR(20)       NOT NULL COMMENT 'general (Forms 1-2) | science | humanities',
    school_id             INT UNSIGNED      NULL,
    phone                 VARCHAR(20)       NOT NULL COMMENT '+265...; becomes the login if admitted',
    email                 VARCHAR(190)      NULL,
    subjects_taken        VARCHAR(100)      NOT NULL DEFAULT '' COMMENT 'Which of our five subjects they take at school',
    help_subjects         VARCHAR(100)      NOT NULL DEFAULT '' COMMENT 'Subjects they want help with',
    struggling_topics     TEXT              NULL,
    motivation            TEXT              NOT NULL,
    device                VARCHAR(30)       NOT NULL DEFAULT '' COMMENT 'own_phone | shared_phone | computer | none',
    data_access           VARCHAR(30)       NOT NULL DEFAULT '' COMMENT 'daily | weekly | rarely',
    guardian_name         VARCHAR(150)      NOT NULL,
    guardian_phone        VARCHAR(20)       NOT NULL,
    guardian_relationship VARCHAR(40)       NOT NULL DEFAULT '',
    guardian_consent_at   DATETIME          NULL,
    report_path           VARCHAR(255)      NULL COMMENT 'School report file in storage/ (tutor-only)',
    report_mime           VARCHAR(60)       NULL,
    quiz_score            SMALLINT UNSIGNED NULL,
    quiz_total            SMALLINT UNSIGNED NULL,
    quiz_started_at       DATETIME          NULL,
    quiz_finished_at      DATETIME          NULL,
    endorsed_by           INT UNSIGNED      NULL COMMENT 'School partner who recommended the applicant',
    endorsement_note      TEXT              NULL,
    tutor_note            TEXT              NULL,
    decided_by            INT UNSIGNED      NULL,
    decided_at            DATETIME          NULL,
    user_id               INT UNSIGNED      NULL COMMENT 'Student account created on admission',
    source                VARCHAR(20)       NOT NULL DEFAULT 'online' COMMENT 'online | paper (typed in by the tutor)',
    ip                    VARCHAR(45)       NOT NULL DEFAULT '',
    created_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_applications_reference (reference),
    KEY idx_applications_seat (intake_id, form, gender, status),
    KEY idx_applications_phone (phone),
    KEY idx_applications_ip (ip, created_at),
    CONSTRAINT fk_applications_intake FOREIGN KEY (intake_id) REFERENCES intakes (id),
    CONSTRAINT fk_applications_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE SET NULL,
    CONSTRAINT fk_applications_endorsed_by FOREIGN KEY (endorsed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_applications_decided_by FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_applications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entry_quiz_questions (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    form        TINYINT UNSIGNED NOT NULL,
    subject     VARCHAR(20)      NOT NULL,
    question    TEXT             NOT NULL,
    option_a    VARCHAR(255)     NOT NULL,
    option_b    VARCHAR(255)     NOT NULL,
    option_c    VARCHAR(255)     NOT NULL,
    option_d    VARCHAR(255)     NOT NULL,
    correct     CHAR(1)          NOT NULL COMMENT 'a | b | c | d',
    explanation TEXT             NULL,
    is_active   TINYINT(1)       NOT NULL DEFAULT 1,
    sort_order  SMALLINT         NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_entry_quiz_form (form, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entry_quiz_answers (
    application_id INT UNSIGNED NOT NULL,
    question_id    INT UNSIGNED NOT NULL,
    chosen         CHAR(1)      NULL,
    is_correct     TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (application_id, question_id),
    CONSTRAINT fk_entry_quiz_answers_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE,
    CONSTRAINT fk_entry_quiz_answers_question FOREIGN KEY (question_id) REFERENCES entry_quiz_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. Students, school partners, account codes, issued documents
-- =====================================================================

CREATE TABLE student_profiles (
    user_id               INT UNSIGNED     NOT NULL,
    application_id        INT UNSIGNED     NULL,
    intake_id             INT UNSIGNED     NULL,
    form                  TINYINT UNSIGNED NOT NULL,
    track                 VARCHAR(20)      NOT NULL COMMENT 'general | science | humanities',
    school_id             INT UNSIGNED     NULL,
    guardian_name         VARCHAR(150)     NULL,
    guardian_phone        VARCHAR(20)      NULL,
    agreement_accepted_at DATETIME         NULL COMMENT 'Class agreement accepted during onboarding',
    guardian_confirmed_at DATETIME         NULL,
    status                VARCHAR(20)      NOT NULL DEFAULT 'active' COMMENT 'active | left | graduated',
    created_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_student_profiles_form (form, status),
    CONSTRAINT fk_student_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_student_profiles_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE SET NULL,
    CONSTRAINT fk_student_profiles_intake FOREIGN KEY (intake_id) REFERENCES intakes (id) ON DELETE SET NULL,
    CONSTRAINT fk_student_profiles_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partner_profiles (
    user_id     INT UNSIGNED NOT NULL,
    school_id   INT UNSIGNED NULL,
    subjects    VARCHAR(100) NOT NULL DEFAULT '',
    verified_at DATETIME     NULL COMMENT 'Tutor confirmed by phone or visit',
    verified_by INT UNSIGNED NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_partner_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_partner_profiles_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE SET NULL,
    CONSTRAINT fk_partner_profiles_verified_by FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partner_invites (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64)     NOT NULL COMMENT 'SHA-256 of the single-use invite token',
    full_name  VARCHAR(150) NOT NULL,
    phone      VARCHAR(20)  NULL,
    school_id  INT UNSIGNED NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    user_id    INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partner_invites_token (token_hash),
    CONSTRAINT fk_partner_invites_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE SET NULL,
    CONSTRAINT fk_partner_invites_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_partner_invites_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_codes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    purpose    VARCHAR(20)  NOT NULL COMMENT 'setup (new student) | reset (forgotten password)',
    code_hash  VARCHAR(255) NOT NULL COMMENT 'password_hash() of the code given out by the tutor',
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_account_codes_user (user_id, purpose, used_at),
    CONSTRAINT fk_account_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_account_codes_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE issued_documents (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(20)  NOT NULL COMMENT 'Printed on the document; checked on /verify',
    type         VARCHAR(40)  NOT NULL COMMENT 'admission_letter | waitlist_letter | decline_letter | certificate | partner_invite | ...',
    subject_type VARCHAR(20)  NOT NULL COMMENT 'application | user',
    subject_id   INT UNSIGNED NOT NULL,
    title        VARCHAR(200) NOT NULL,
    data         TEXT         NULL COMMENT 'JSON snapshot of what the document said',
    issued_by    INT UNSIGNED NULL,
    issued_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at   DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_issued_documents_code (code),
    KEY idx_issued_documents_subject (subject_type, subject_id),
    CONSTRAINT fk_issued_documents_issued_by FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. Curriculum: topics, school coverage, textbooks
-- =====================================================================

CREATE TABLE topics (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    form       TINYINT UNSIGNED NOT NULL,
    subject    VARCHAR(20)      NOT NULL,
    parent_id  INT UNSIGNED     NULL COMMENT 'Set for subtopics',
    unit       VARCHAR(150)     NULL,
    title      VARCHAR(200)     NOT NULL,
    objectives TEXT             NULL COMMENT 'Learning objectives from the syllabus',
    sort_order SMALLINT         NOT NULL DEFAULT 0,
    is_active  TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_topics_form_subject (form, subject, sort_order),
    CONSTRAINT fk_topics_parent FOREIGN KEY (parent_id) REFERENCES topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topic_coverage (
    school_id   INT UNSIGNED NOT NULL,
    topic_id    INT UNSIGNED NOT NULL,
    status      VARCHAR(20)  NOT NULL COMMENT 'covered | covering | next',
    reported_by INT UNSIGNED NULL,
    reported_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (school_id, topic_id),
    KEY idx_topic_coverage_topic (topic_id),
    CONSTRAINT fk_topic_coverage_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_coverage_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_coverage_reported_by FOREIGN KEY (reported_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE textbooks (
    id        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    subject   VARCHAR(20)      NOT NULL,
    form      TINYINT UNSIGNED NOT NULL,
    title     VARCHAR(200)     NOT NULL,
    edition   VARCHAR(60)      NOT NULL DEFAULT '',
    publisher VARCHAR(120)     NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_textbooks (subject, form, title, edition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_textbooks (
    school_id   INT UNSIGNED NOT NULL,
    textbook_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (school_id, textbook_id),
    CONSTRAINT fk_school_textbooks_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
    CONSTRAINT fk_school_textbooks_textbook FOREIGN KEY (textbook_id) REFERENCES textbooks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE textbook_topic_refs (
    textbook_id INT UNSIGNED NOT NULL,
    topic_id    INT UNSIGNED NOT NULL,
    chapter     VARCHAR(60)  NOT NULL DEFAULT '',
    pages       VARCHAR(60)  NOT NULL DEFAULT '',
    PRIMARY KEY (textbook_id, topic_id),
    CONSTRAINT fk_textbook_topic_refs_textbook FOREIGN KEY (textbook_id) REFERENCES textbooks (id) ON DELETE CASCADE,
    CONSTRAINT fk_textbook_topic_refs_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lessons
    ADD COLUMN topic_id INT UNSIGNED NULL AFTER subject,
    ADD KEY idx_lessons_topic (topic_id),
    ADD CONSTRAINT fk_lessons_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL;

-- =====================================================================
-- 5. Topic requests
-- =====================================================================

CREATE TABLE topic_requests (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    topic_id         INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL COMMENT 'The student who requested it first',
    struggle_type    VARCHAR(20)  NOT NULL COMMENT 'idea | calculations | real_life | exam | practical',
    details          TEXT         NOT NULL COMMENT 'Where exactly they get lost',
    photo_path       VARCHAR(255) NULL,
    is_exception     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Topic not covered at their school',
    exception_reason TEXT         NULL,
    exception_status VARCHAR(20)  NULL COMMENT 'pending | approved | declined',
    status           VARCHAR(20)  NOT NULL DEFAULT 'open' COMMENT 'open | planned | recorded | solved | closed',
    lesson_id        INT UNSIGNED NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_topic_requests_topic (topic_id) COMMENT 'One request per topic; others add "me too" votes',
    KEY idx_topic_requests_status (status, created_at),
    CONSTRAINT fk_topic_requests_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_requests_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topic_request_votes (
    request_id    INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    struggle_type VARCHAR(20)  NOT NULL,
    details       TEXT         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, user_id),
    CONSTRAINT fk_topic_request_votes_request FOREIGN KEY (request_id) REFERENCES topic_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_request_votes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. Assessment: question bank, exams, assignments, in-lesson checks
-- =====================================================================

CREATE TABLE questions (
    id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    form           TINYINT UNSIGNED  NOT NULL,
    subject        VARCHAR(20)       NOT NULL,
    topic_id       INT UNSIGNED      NULL,
    type           VARCHAR(20)       NOT NULL COMMENT 'mcq | numeric | expression | chem_equation | label_diagram | drawing | short_text | grammar_fill',
    prompt         MEDIUMTEXT        NOT NULL COMMENT 'Rich text with equations/diagrams',
    data           MEDIUMTEXT        NULL COMMENT 'JSON: options, answers, tolerance, units, labels',
    marks          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_application TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Real-life application question',
    created_by     INT UNSIGNED      NULL,
    created_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_questions_form_subject (form, subject),
    KEY idx_questions_topic (topic_id),
    CONSTRAINT fk_questions_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL,
    CONSTRAINT fk_questions_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assessments (
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    kind         VARCHAR(20)       NOT NULL COMMENT 'exam | assignment | lesson_check | exit_ticket | weekly_check',
    title        VARCHAR(200)      NOT NULL,
    form         TINYINT UNSIGNED  NULL,
    subject      VARCHAR(20)       NULL,
    lesson_id    INT UNSIGNED      NULL COMMENT 'For questions inside a replay',
    instructions TEXT              NULL,
    opens_at     DATETIME          NULL,
    closes_at    DATETIME          NULL,
    duration_min SMALLINT UNSIGNED NULL COMMENT 'Timed exams (server-side timer)',
    status       VARCHAR(20)       NOT NULL DEFAULT 'draft' COMMENT 'draft | published | closed',
    created_by   INT UNSIGNED      NULL,
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assessments_form (form, subject, status),
    KEY idx_assessments_lesson (lesson_id),
    CONSTRAINT fk_assessments_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
    CONSTRAINT fk_assessments_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assessment_questions (
    assessment_id  INT UNSIGNED      NOT NULL,
    question_id    INT UNSIGNED      NOT NULL,
    sort_order     SMALLINT          NOT NULL DEFAULT 0,
    marks_override SMALLINT UNSIGNED NULL,
    lesson_time_ms INT UNSIGNED      NULL COMMENT 'Pause the replay here and ask',
    PRIMARY KEY (assessment_id, question_id),
    CONSTRAINT fk_assessment_questions_assessment FOREIGN KEY (assessment_id) REFERENCES assessments (id) ON DELETE CASCADE,
    CONSTRAINT fk_assessment_questions_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submissions (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    assessment_id INT UNSIGNED  NOT NULL,
    user_id       INT UNSIGNED  NOT NULL,
    started_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at  DATETIME      NULL,
    status        VARCHAR(20)   NOT NULL DEFAULT 'in_progress' COMMENT 'in_progress | submitted | marked',
    score         DECIMAL(7,2)  NULL,
    max_score     DECIMAL(7,2)  NULL,
    feedback      TEXT          NULL,
    marked_by     INT UNSIGNED  NULL,
    marked_at     DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submissions (assessment_id, user_id),
    KEY idx_submissions_user (user_id, status),
    CONSTRAINT fk_submissions_assessment FOREIGN KEY (assessment_id) REFERENCES assessments (id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_marked_by FOREIGN KEY (marked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submission_answers (
    submission_id   INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    answer          MEDIUMTEXT   NULL COMMENT 'JSON: typed answer, working, drawing strokes',
    file_path       VARCHAR(255) NULL COMMENT 'Photo of written working',
    auto_score      DECIMAL(6,2) NULL,
    manual_score    DECIMAL(6,2) NULL,
    feedback        TEXT         NULL,
    annotation_path VARCHAR(255) NULL COMMENT 'Tutor Pencil marking (board strokes)',
    PRIMARY KEY (submission_id, question_id),
    CONSTRAINT fk_submission_answers_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_answers_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. Community: comments, mentions, tags, follows, reactions, reports
-- =====================================================================

CREATE TABLE comments (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type    VARCHAR(20)  NOT NULL COMMENT 'lesson | topic | challenge | wall_post | resource',
    target_id      INT UNSIGNED NOT NULL,
    parent_id      INT UNSIGNED NULL COMMENT 'Reply to another comment',
    user_id        INT UNSIGNED NOT NULL,
    body           TEXT         NOT NULL,
    lesson_time_ms INT UNSIGNED NULL COMMENT 'Timestamped comment on a replay',
    reply_lesson_id INT UNSIGNED NULL COMMENT 'Tutor video reply (a short board recording)',
    is_approved_answer TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Marked "Tutor approved"',
    status         VARCHAR(20)  NOT NULL DEFAULT 'visible' COMMENT 'visible | hidden',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_target (target_type, target_id, created_at),
    KEY idx_comments_parent (parent_id),
    KEY idx_comments_user (user_id),
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_reply_lesson FOREIGN KEY (reply_lesson_id) REFERENCES lessons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mentions (
    comment_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (comment_id, user_id),
    KEY idx_mentions_user (user_id),
    CONSTRAINT fk_mentions_comment FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT fk_mentions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50)  NOT NULL COMMENT 'Lowercase, without the #',
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE taggings (
    tag_id      INT UNSIGNED NOT NULL,
    target_type VARCHAR(20)  NOT NULL COMMENT 'lesson | comment | resource | question | topic',
    target_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (tag_id, target_type, target_id),
    KEY idx_taggings_target (target_type, target_id),
    CONSTRAINT fk_taggings_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE follows (
    user_id     INT UNSIGNED NOT NULL,
    target_type VARCHAR(20)  NOT NULL COMMENT 'subject | topic | comment (thread) | lesson',
    target_id   VARCHAR(40)  NOT NULL COMMENT 'Numeric id, or a subject key',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, target_type, target_id),
    KEY idx_follows_target (target_type, target_id),
    CONSTRAINT fk_follows_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reactions (
    comment_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    kind       VARCHAR(20)  NOT NULL COMMENT 'like | same_question | thanks',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, user_id, kind),
    CONSTRAINT fk_reactions_comment FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT fk_reactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_reports (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type VARCHAR(20)  NOT NULL COMMENT 'comment | wall_post | resource',
    target_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL COMMENT 'Who reported it',
    reason      VARCHAR(255) NOT NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'open' COMMENT 'open | resolved',
    resolved_by INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_reports_status (status, created_at),
    CONSTRAINT fk_content_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_content_reports_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE challenges (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    form            TINYINT UNSIGNED NOT NULL,
    subject         VARCHAR(20)      NOT NULL,
    topic_id        INT UNSIGNED     NULL,
    title           VARCHAR(200)     NOT NULL,
    body            TEXT             NOT NULL COMMENT 'The real-life problem of the week',
    opens_at        DATETIME         NOT NULL,
    closes_at       DATETIME         NOT NULL,
    answer_lesson_id INT UNSIGNED    NULL COMMENT 'Explanation posted at the end of the week',
    created_by      INT UNSIGNED     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_challenges_form (form, opens_at),
    CONSTRAINT fk_challenges_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL,
    CONSTRAINT fk_challenges_answer_lesson FOREIGN KEY (answer_lesson_id) REFERENCES lessons (id) ON DELETE SET NULL,
    CONSTRAINT fk_challenges_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wall_posts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    topic_id   INT UNSIGNED NULL,
    caption    VARCHAR(500) NOT NULL COMMENT '"Spotted in real life"',
    photo_path VARCHAR(255) NULL,
    status     VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending | visible | hidden',
    featured   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wall_posts_status (status, created_at),
    CONSTRAINT fk_wall_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_wall_posts_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. Library resources (public handouts and shared material)
-- =====================================================================

CREATE TABLE resources (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    title        VARCHAR(200)     NOT NULL,
    kind         VARCHAR(20)      NOT NULL COMMENT 'handout | slides | notes | open_textbook | worksheet',
    form         TINYINT UNSIGNED NULL,
    subject      VARCHAR(20)      NULL,
    topic_id     INT UNSIGNED     NULL,
    lesson_id    INT UNSIGNED     NULL COMMENT 'Handout generated from this lesson',
    file_path    VARCHAR(255)     NULL,
    file_size    INT UNSIGNED     NULL,
    mime         VARCHAR(80)      NULL,
    external_url VARCHAR(500)     NULL,
    licence_note VARCHAR(255)     NULL COMMENT 'Own work / permission / open licence + credit',
    visibility   VARCHAR(20)      NOT NULL DEFAULT 'public' COMMENT 'public | students',
    status       VARCHAR(20)      NOT NULL DEFAULT 'pending' COMMENT 'pending | published | rejected',
    submitted_by INT UNSIGNED     NULL,
    reviewed_by  INT UNSIGNED     NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME         NULL,
    PRIMARY KEY (id),
    KEY idx_resources_public (status, visibility, form, subject),
    CONSTRAINT fk_resources_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL,
    CONSTRAINT fk_resources_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE SET NULL,
    CONSTRAINT fk_resources_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_resources_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. Progress and motivation
-- =====================================================================

CREATE TABLE lesson_views (
    user_id         INT UNSIGNED NOT NULL,
    lesson_id       INT UNSIGNED NOT NULL,
    watched_ms      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Furthest point reached',
    completed_at    DATETIME     NULL,
    last_watched_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, lesson_id),
    KEY idx_lesson_views_lesson (lesson_id),
    CONSTRAINT fk_lesson_views_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_views_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exit_tickets (
    lesson_id     INT UNSIGNED     NOT NULL,
    user_id       INT UNSIGNED     NOT NULL,
    understanding TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    real_life_use TEXT             NULL COMMENT '"Give one real-life use"',
    still_unclear TEXT             NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (lesson_id, user_id),
    CONSTRAINT fk_exit_tickets_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
    CONSTRAINT fk_exit_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE achievements (
    user_id   INT UNSIGNED NOT NULL,
    code      VARCHAR(40)  NOT NULL COMMENT 'e.g. watch_streak_7, helped_5, perfect_exit_ticket',
    earned_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, code),
    CONSTRAINT fk_achievements_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. Notifications, email and templates
-- =====================================================================

CREATE TABLE notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED    NOT NULL,
    type       VARCHAR(40)     NOT NULL COMMENT 'lesson_published | mention | reply | request_planned | marked | digest | ...',
    title      VARCHAR(200)    NOT NULL,
    body       VARCHAR(500)    NULL,
    link       VARCHAR(255)    NULL,
    read_at    DATETIME        NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_queue (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    to_email     VARCHAR(190)     NOT NULL,
    to_name      VARCHAR(150)     NOT NULL DEFAULT '',
    subject      VARCHAR(200)     NOT NULL,
    html_body    MEDIUMTEXT       NOT NULL,
    text_body    MEDIUMTEXT       NOT NULL,
    template_key VARCHAR(60)      NULL,
    related_type VARCHAR(20)      NULL,
    related_id   INT UNSIGNED     NULL,
    status       VARCHAR(20)      NOT NULL DEFAULT 'queued' COMMENT 'queued | sent | failed',
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error   VARCHAR(500)     NULL,
    created_by   INT UNSIGNED     NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at      DATETIME         NULL,
    PRIMARY KEY (id),
    KEY idx_mail_queue_status (status, created_at),
    CONSTRAINT fk_mail_queue_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_type VARCHAR(20)  NOT NULL COMMENT 'application | user | school',
    person_id   INT UNSIGNED NOT NULL,
    channel     VARCHAR(20)  NOT NULL COMMENT 'email | whatsapp | phone | visit | letter',
    direction   VARCHAR(10)  NOT NULL COMMENT 'out | in (e.g. a reply read in Zoho)',
    summary     VARCHAR(500) NOT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_log_person (person_type, person_id, created_at),
    CONSTRAINT fk_contact_log_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_templates (
    template_key VARCHAR(60)  NOT NULL,
    name         VARCHAR(150) NOT NULL,
    channel      VARCHAR(20)  NOT NULL COMMENT 'letter | email | whatsapp',
    subject      VARCHAR(200) NULL,
    body         MEDIUMTEXT   NOT NULL COMMENT 'With {{placeholders}}',
    updated_by   INT UNSIGNED NULL,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (template_key),
    CONSTRAINT fk_document_templates_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11. System: lazy scheduler (no cron on InfinityFree) and backups
-- =====================================================================

CREATE TABLE scheduled_jobs (
    name             VARCHAR(60)       NOT NULL,
    interval_minutes SMALLINT UNSIGNED NOT NULL,
    last_run_at      DATETIME          NULL,
    last_result      VARCHAR(500)      NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backups (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_name  VARCHAR(150) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_backups_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
