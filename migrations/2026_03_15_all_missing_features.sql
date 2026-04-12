-- ============================================================
-- Migration: All Missing Features
-- Date: 2026-03-15
-- Covers: Financial Tracking, Feedback/Surveys, Login Rate Limiting
-- ============================================================

-- --------------------------------------------------------
-- 1. FINANCIAL TRACKING: Extend disbursements table
-- --------------------------------------------------------
ALTER TABLE `disbursements`
  ADD COLUMN IF NOT EXISTS `application_id` INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `scholarship_id` INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `notes`          TEXT     DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `created_by`     INT(11)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at`     DATETIME DEFAULT NULL;

-- Ensure status enum is correct
ALTER TABLE `disbursements`
  MODIFY COLUMN `status` ENUM('pending','processed','completed','failed') DEFAULT 'pending';

-- Add indexes if missing
ALTER TABLE `disbursements`
  ADD INDEX IF NOT EXISTS `idx_status`  (`status`),
  ADD INDEX IF NOT EXISTS `idx_deleted` (`deleted_at`),
  ADD INDEX IF NOT EXISTS `idx_date`    (`disbursement_date`);

-- --------------------------------------------------------
-- 2. FEEDBACK TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `user_id`        INT NOT NULL,
  `scholarship_id` INT NOT NULL,
  `rating`         TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment`        TEXT DEFAULT NULL,
  `submitted_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_application` (`application_id`),
  INDEX `idx_scholarship` (`scholarship_id`),
  INDEX `idx_user` (`user_id`),
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)        REFERENCES `users`(`id`)        ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. SURVEYS TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `surveys` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `title`          VARCHAR(255) NOT NULL,
  `description`    TEXT DEFAULT NULL,
  `scholarship_id` INT DEFAULT NULL,
  `cycle_label`    VARCHAR(100) DEFAULT NULL,
  `status`         ENUM('draft','active','closed') DEFAULT 'draft',
  `created_by`     INT NOT NULL,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_scholarship` (`scholarship_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`)        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. SURVEY QUESTIONS TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_questions` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `survey_id`  INT NOT NULL,
  `question`   TEXT NOT NULL,
  `type`       ENUM('multiple_choice','text','rating_scale') NOT NULL,
  `options`    JSON DEFAULT NULL,
  `sort_order` TINYINT DEFAULT 0,
  `required`   TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_survey` (`survey_id`),
  FOREIGN KEY (`survey_id`) REFERENCES `surveys`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. SURVEY RESPONSES TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_responses` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `survey_id`      INT NOT NULL,
  `user_id`        INT NOT NULL,
  `application_id` INT NOT NULL,
  `submitted_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_survey_user` (`survey_id`, `user_id`),
  INDEX `idx_survey` (`survey_id`),
  FOREIGN KEY (`survey_id`)      REFERENCES `surveys`(`id`)      ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)        REFERENCES `users`(`id`)        ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. SURVEY ANSWERS TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `survey_answers` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `response_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `answer`      TEXT DEFAULT NULL,
  INDEX `idx_response` (`response_id`),
  INDEX `idx_question` (`question_id`),
  FOREIGN KEY (`response_id`) REFERENCES `survey_responses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. LOGIN ATTEMPTS TABLE (ensure correct schema)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(150) NOT NULL,
  `email`      VARCHAR(150) DEFAULT NULL,
  `ip_address` VARCHAR(45)  NOT NULL,
  `success`    TINYINT(1)   DEFAULT 0,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_username_created` (`username`, `created_at`),
  INDEX `idx_email_created`    (`email`, `created_at`),
  INDEX `idx_ip_created`       (`ip_address`, `created_at`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
