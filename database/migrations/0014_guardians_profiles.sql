-- SMART Circle v2: guardians are contacted after admission (they need not be present when the student
-- applies), in the language they understand; guardians confirm with a link; profile pictures.

ALTER TABLE applications
    ADD COLUMN guardian_whatsapp VARCHAR(20)  NULL COMMENT 'If different from guardian_phone' AFTER guardian_phone,
    ADD COLUMN guardian_email    VARCHAR(190) NULL AFTER guardian_whatsapp,
    ADD COLUMN guardian_language VARCHAR(5)   NOT NULL DEFAULT 'en' COMMENT 'ny (Chichewa) | en (English)' AFTER guardian_email;

ALTER TABLE student_profiles
    ADD COLUMN guardian_relationship VARCHAR(40)  NULL AFTER guardian_phone,
    ADD COLUMN guardian_whatsapp     VARCHAR(20)  NULL AFTER guardian_relationship,
    ADD COLUMN guardian_email        VARCHAR(190) NULL AFTER guardian_whatsapp,
    ADD COLUMN guardian_language     VARCHAR(5)   NOT NULL DEFAULT 'en' COMMENT 'ny (Chichewa) | en (English)' AFTER guardian_email,
    ADD COLUMN guardian_token        VARCHAR(40)  NULL COMMENT 'For the guardian''s confirmation link' AFTER guardian_language,
    ADD COLUMN guardian_confirmed_via VARCHAR(20) NULL COMMENT 'link | call | onboarding (before 2026-12)' AFTER guardian_confirmed_at,
    ADD COLUMN guardian_declined_at  DATETIME     NULL COMMENT 'Guardian said they do not know the student' AFTER guardian_confirmed_via,
    ADD UNIQUE KEY uq_student_profiles_guardian_token (guardian_token);

-- Confirmations recorded by the old onboarding checkbox keep counting.
UPDATE student_profiles SET guardian_confirmed_via = 'onboarding' WHERE guardian_confirmed_at IS NOT NULL;

ALTER TABLE users
    ADD COLUMN avatar_at DATETIME NULL COMMENT 'Profile picture saved at (file in storage/avatars)' AFTER email;
