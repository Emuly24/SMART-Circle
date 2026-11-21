-- =====================================================================
-- SMART Circle — remove the OLD site's tables (before installing v2).
--
-- !!! READ FIRST !!!
-- 1. EXPORT A BACKUP of the old database first (phpMyAdmin > Export > Quick > SQL)
--    and keep the file somewhere safe. This script deletes data permanently.
-- 2. Only use this if you want v2 in the SAME database as the old site.
--    The simpler, safer option is a NEW empty database (see docs/database.md).
-- 3. NEVER run this after v2 is installed: the old site used some of the same
--    table names as v2 (users, sessions, applications, topic_requests), so it
--    would delete v2 data too. The guard below stops the script if v2's
--    schema_migrations table exists.
-- =====================================================================

-- Guard: fails with "Table 'schema_migrations' already exists" if v2 is installed,
-- which stops phpMyAdmin before anything is dropped.
CREATE TABLE schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY);
DROP TABLE schema_migrations;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
    activity_log, admin_messages, admin_research_notes, admin_settings, applications,
    assignment_submissions, assignments, attendance, book_annotations, book_questions,
    book_reviews, books, diagrams_library, discipline_log, equations_library,
    exam_answers, exam_questions, exam_submissions, exams, exercise_attempts,
    group_content_locks, group_meetings, group_members, `groups`, message_templates,
    note_drafts, note_exercises, note_sections, notes, quiz_answers, quiz_attempts,
    quiz_questions, quizzes, self_quiz_attempts, self_quizzes, sessions,
    student_messages, student_reports, student_resources, subject_questions,
    testimonials, topic_requests, topics_covered, universities, users;

SET FOREIGN_KEY_CHECKS = 1;
