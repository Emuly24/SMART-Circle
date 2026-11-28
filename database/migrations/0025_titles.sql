-- SMART Circle v2: how to address teachers and tutors (Mr, Mrs, Ms, Miss, Dr, Prof). Named "honorific"
-- (not "title") so it never clashes with the many title columns of notes, lessons, topics ... Used by the
-- one-click name tags in messages and emails ("Title + surname" → "Mr Banda").

ALTER TABLE users
    ADD COLUMN honorific VARCHAR(10) NULL COMMENT 'Mr | Mrs | Ms | Miss | Dr | Prof (teachers and tutors)' AFTER full_name;
