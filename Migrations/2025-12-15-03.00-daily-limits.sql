CREATE TABLE
	IF NOT EXISTS `featherpanel_billingafk_daily_usage` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`date` DATE NOT NULL,
		`credits_earned` INT (11) NOT NULL DEFAULT 0 COMMENT 'Credits earned on this date',
		`sessions_count` INT (11) NOT NULL DEFAULT 0 COMMENT 'Number of sessions on this date',
		`time_seconds` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total AFK time in seconds on this date',
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `billingafk_daily_usage_user_date_unique` (`user_id`, `date`),
		KEY `idx_date` (`date`),
		KEY `idx_user_id` (`user_id`),
		CONSTRAINT `billingafk_daily_usage_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;