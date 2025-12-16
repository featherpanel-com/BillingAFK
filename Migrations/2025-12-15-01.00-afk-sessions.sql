CREATE TABLE
	IF NOT EXISTS `featherpanel_billingafk_sessions` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`stopped_at` TIMESTAMP NULL DEFAULT NULL,
		`credits_earned` INT (11) NOT NULL DEFAULT 0,
		`credits_claimed` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total credits already claimed from this session',
		`time_elapsed` INT (11) NOT NULL DEFAULT 0 COMMENT 'Time in seconds',
		`last_claim_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time credits were claimed',
		`is_active` TINYINT (1) NOT NULL DEFAULT 1,
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		KEY `idx_user_id` (`user_id`),
		KEY `idx_is_active` (`is_active`),
		KEY `idx_started_at` (`started_at`),
		CONSTRAINT `billingafk_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
	IF NOT EXISTS `featherpanel_billingafk_user_stats` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`total_time_seconds` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total AFK time in seconds',
		`total_credits_earned` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total credits earned from AFK',
		`sessions_count` INT (11) NOT NULL DEFAULT 0 COMMENT 'Total number of AFK sessions',
		`last_session_at` TIMESTAMP NULL DEFAULT NULL,
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `billingafk_user_stats_user_id_unique` (`user_id`),
		CONSTRAINT `billingafk_user_stats_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;