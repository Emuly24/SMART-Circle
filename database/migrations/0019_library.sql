-- SMART Circle v2: the free public library. Notes the tutor chooses are shown to everyone as branded,
-- printable handouts (never their exercises), and lessons can carry a PDF handout made elsewhere.

ALTER TABLE notes
    ADD COLUMN in_library TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Shown in the public library (text and pictures only)' AFTER status;

ALTER TABLE lessons
    ADD COLUMN handout_path   VARCHAR(255) NULL COMMENT 'PDF handout, relative to storage/' AFTER try_at_home,
    ADD COLUMN handout_bytes  INT UNSIGNED NULL AFTER handout_path,
    ADD COLUMN handout_public TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Shown in the public library' AFTER handout_bytes;
