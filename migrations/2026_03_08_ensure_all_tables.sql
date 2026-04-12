-- Ensure all required tables and columns exist for full feature implementation

-- Ensure deadline_reminders table exists
CREATE TABLE IF NOT EXISTS `deadline_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `scholarship_id` int(11) NOT NULL,
  `reminder_type` enum('7_days','1_day','deadline') NOT NULL,
  `sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `scholarship_id` (`scholarship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure documents table has all verification columns
ALTER TABLE `documents` 
ADD COLUMN IF NOT EXISTS `verified_by` int(11) DEFAULT NULL AFTER `verification_status`,
ADD COLUMN IF NOT EXISTS `verified_at` datetime DEFAULT NULL AFTER `verified_by`,
ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL AFTER `verified_at`;

-- Ensure email_logs table exists with all columns
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `body` text NOT NULL,
  `status` enum('queued','sent','failed') DEFAULT 'queued',
  `attempts` int(11) DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure cron_runs table exists
CREATE TABLE IF NOT EXISTS `cron_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `script` varchar(255) NOT NULL,
  `ran_at` datetime DEFAULT current_timestamp(),
  `status` varchar(32) DEFAULT 'pending',
  `output` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `script` (`script`),
  KEY `ran_at` (`ran_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure audit_logs table exists with proper schema
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(128) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `target_table` varchar(128) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_target` (`target_table`, `target_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
