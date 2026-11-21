-- SMART Circle v2 — core tables: accounts, sessions, login protection, settings, audit trail.
-- Applied by the web installer (/install) or the admin "Database updates" page.

CREATE TABLE users (
    id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    role                 VARCHAR(20)   NOT NULL COMMENT 'tutor | student | partner',
    full_name            VARCHAR(150)  NOT NULL,
    phone                VARCHAR(20)   NOT NULL COMMENT 'Canonical +265XXXXXXXXX; used to log in',
    email                VARCHAR(190)  NULL,
    gender               VARCHAR(10)   NULL COMMENT 'female | male',
    password_hash        VARCHAR(255)  NOT NULL,
    status               VARCHAR(20)   NOT NULL DEFAULT 'active' COMMENT 'active | disabled',
    must_change_password TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Set after a tutor-issued reset code',
    last_login_at        DATETIME      NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_phone (phone),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id            VARCHAR(128)  NOT NULL,
    user_id       INT UNSIGNED  NULL,
    data          MEDIUMTEXT    NOT NULL,
    last_activity INT UNSIGNED  NOT NULL COMMENT 'Unix time',
    PRIMARY KEY (id),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone      VARCHAR(20)     NOT NULL,
    ip         VARCHAR(45)     NOT NULL,
    succeeded  TINYINT(1)      NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_phone (phone, created_at),
    KEY idx_login_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    name       VARCHAR(100) NOT NULL,
    value      TEXT         NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED    NULL,
    action     VARCHAR(100)    NOT NULL,
    details    TEXT            NULL,
    ip         VARCHAR(45)     NOT NULL DEFAULT '',
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_log_user (user_id, created_at),
    KEY idx_audit_log_action (action, created_at),
    CONSTRAINT fk_audit_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
