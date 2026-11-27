-- SMART Circle v2: the community features use the tables planned in 0003 (comments, mentions, reactions,
-- follows, tags, content_reports, challenges, wall_posts, achievements, exit_tickets). This adds the tutor's
-- written explanation for a weekly challenge, shown to everyone when it closes.

ALTER TABLE challenges
    ADD COLUMN explanation TEXT NULL COMMENT 'Shown with everyone''s answers when the challenge closes' AFTER body;
