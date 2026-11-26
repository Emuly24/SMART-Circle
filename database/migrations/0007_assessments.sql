-- SMART Circle v2: exams and assignments.
-- Server-side exam timer and when students may see their marks.

ALTER TABLE assessments
    ADD COLUMN results_at DATETIME NULL COMMENT 'When the tutor released marks to students (NULL = not yet)' AFTER status;

ALTER TABLE submissions
    ADD COLUMN deadline_at DATETIME NULL COMMENT 'Timed exams: started_at + duration; answers after this (plus grace) are refused' AFTER started_at;
