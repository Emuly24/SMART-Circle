-- SMART Circle v2: a reply can quote an earlier message in the same conversation.

ALTER TABLE messages
    ADD COLUMN quote_id INT UNSIGNED NULL COMMENT 'The earlier message this one quotes (same conversation)' AFTER body,
    ADD CONSTRAINT fk_messages_quote FOREIGN KEY (quote_id) REFERENCES messages (id) ON DELETE SET NULL;
