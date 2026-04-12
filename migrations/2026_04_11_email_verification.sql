-- Email verification tokens table
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`),
  UNIQUE KEY `uq_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fallback: add verification_token column to users if email_verifications table is not used
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `verification_token` VARCHAR(64) NULL DEFAULT NULL;
