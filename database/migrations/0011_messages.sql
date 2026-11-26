-- SMART Circle v2: in-app messages, announcements and the private support room.
-- Nothing here can be deleted: conversations with young people are kept, which protects them and the tutor.

CREATE TABLE conversations (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind                 VARCHAR(20)  NOT NULL COMMENT 'direct | announcement | support',
    subject              VARCHAR(150) NOT NULL,
    audience             VARCHAR(30)  NULL COMMENT 'announcement: students | form:N | partners',
    student_id           INT UNSIGNED NULL COMMENT 'support: the student the room belongs to',
    opened_by            VARCHAR(10)  NULL COMMENT 'support: student | tutor (invitation)',
    guardian_notified_at DATETIME     NULL COMMENT 'support: tutor told the guardian about the invitation',
    created_by           INT UNSIGNED NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_message_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conversations_kind (kind, last_message_at),
    KEY idx_conversations_student (student_id),
    CONSTRAINT fk_conversations_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_conversations_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversation_members (
    conversation_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    last_read_at    DATETIME     NULL,
    PRIMARY KEY (conversation_id, user_id),
    KEY idx_conversation_members_user (user_id),
    CONSTRAINT fk_conversation_members_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_conversation_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    body            TEXT         NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_conversation (conversation_id, created_at),
    KEY idx_messages_user (user_id, created_at),
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
