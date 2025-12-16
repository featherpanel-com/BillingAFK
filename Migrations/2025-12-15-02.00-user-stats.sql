-- Create user stats table (like MythicalDash - no sessions, just minutes_afk and last_seen_afk)
CREATE TABLE
	IF NOT EXISTS `featherpanel_billingafk_user_stats` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`minutes_afk` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total minutes user has been AFK',
		`last_seen_afk` INT (11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp of last AFK activity',
		`total_time_seconds` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total AFK time in seconds',
		`total_credits_earned` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total credits earned from AFK',
		`sessions_count` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total number of AFK sessions (legacy, not used)',
		`last_session_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last session timestamp (legacy, not used)',
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `billingafk_user_stats_user_id_unique` (`user_id`),
		KEY `idx_minutes_afk` (`minutes_afk`),
		KEY `idx_last_seen_afk` (`last_seen_afk`),
		CONSTRAINT `billingafk_user_stats_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

