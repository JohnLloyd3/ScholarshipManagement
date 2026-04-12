-- Force password change on first login (for admin-created accounts)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0;

-- Email verifications table (if not already created)
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

-- Fallback verification token column on users
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `verification_token` VARCHAR(64) NULL DEFAULT NULL;
