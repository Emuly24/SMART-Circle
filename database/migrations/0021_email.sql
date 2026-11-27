-- SMART Circle v2: email goes out through Zoho SMTP from a queue, a few at a time on normal page visits
-- (no cron on InfinityFree). Failed sends are retried later; people choose whether to get notifications by email.

ALTER TABLE mail_queue
    ADD COLUMN next_try_at DATETIME NULL COMMENT 'Retry after a failure' AFTER attempts,
    ADD KEY idx_mail_queue_due (status, next_try_at);

ALTER TABLE users
    ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Also send my notifications by email (when I have an email address)' AFTER email;

INSERT INTO scheduled_jobs (name, interval_minutes) VALUES ('mail', 2)
    ON DUPLICATE KEY UPDATE interval_minutes = VALUES(interval_minutes);
