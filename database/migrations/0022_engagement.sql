-- SMART Circle v2: keeping seats alive and people informed.
--  * seat_checks: every nudge, pause and released seat for students who have gone quiet (decisions are kept).
--  * notes.published_at: when notes first went out (for the weekly digest).
--  * Two lazy jobs (no cron): daily seat checks, and the weekly digest (Mondays; at least 6 days apart).

CREATE TABLE seat_checks (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    action     VARCHAR(10)  NOT NULL COMMENT 'nudge | pause | released',
    note       VARCHAR(500) NULL,
    until      DATE         NULL COMMENT 'pause: no checks before this day',
    created_by INT UNSIGNED NULL COMMENT 'NULL = automatic',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_seat_checks_user (user_id, created_at),
    CONSTRAINT fk_seat_checks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_seat_checks_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notes
    ADD COLUMN published_at DATETIME NULL AFTER status;

UPDATE notes SET published_at = updated_at WHERE status = 'published' AND published_at IS NULL;

INSERT INTO scheduled_jobs (name, interval_minutes) VALUES ('seat_checks', 1440), ('digest', 8640)
    ON DUPLICATE KEY UPDATE interval_minutes = VALUES(interval_minutes);
