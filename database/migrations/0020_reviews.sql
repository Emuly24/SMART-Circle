-- SMART Circle v2: qualified teachers check notes. A partner teacher signs once (drawn or a photo of their
-- signature), then approves notes in their subjects or suggests changes. Every decision is kept; an approval
-- shows the teacher's name, school and signature until the note is changed.

ALTER TABLE partner_profiles
    ADD COLUMN signature_path VARCHAR(255) NULL COMMENT 'PNG in storage/uploads/signatures',
    ADD COLUMN signature_at   DATETIME     NULL COMMENT 'When they signed and agreed their name, school and signature may be shown';

ALTER TABLE notes
    ADD COLUMN review_requested_at DATETIME NULL COMMENT 'Sent to partner teachers to check' AFTER in_library;

CREATE TABLE note_reviews (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    note_id        INT UNSIGNED NOT NULL,
    reviewer_id    INT UNSIGNED NULL,
    decision       VARCHAR(10)  NOT NULL COMMENT 'approved | changes',
    comment        TEXT         NULL,
    content_hash   CHAR(64)     NOT NULL COMMENT 'The note as it was checked; a later edit needs a new check',
    reviewer_name  VARCHAR(150) NOT NULL,
    school_name    VARCHAR(150) NULL,
    signature_path VARCHAR(255) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_note_reviews_note (note_id, id),
    CONSTRAINT fk_note_reviews_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE,
    CONSTRAINT fk_note_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
