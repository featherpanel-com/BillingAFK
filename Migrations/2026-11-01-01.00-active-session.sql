-- Track whether a user has an authorized AFK session started via /start.
-- work() requires session_active so the endpoint cannot be farmed while AFK is
-- disabled or without going through start (and captcha when required).
ALTER TABLE `featherpanel_billingafk_user_stats`
	ADD COLUMN `session_active` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 when user started AFK via /start' AFTER `last_seen_afk`,
	ADD COLUMN `session_started_at` INT(11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp when current AFK session started' AFTER `session_active`;
