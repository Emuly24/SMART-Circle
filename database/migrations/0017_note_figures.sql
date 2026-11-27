-- SMART Circle v2: pictures and drawings in notes can sit left, centre or right.

ALTER TABLE note_blocks
    ADD COLUMN align VARCHAR(10) NOT NULL DEFAULT 'center' COMMENT 'image: left | center | right' AFTER image_path;
