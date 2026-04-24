-- ============================================
-- SCHOLARHUB COMPLETE DATABASE SETUP
-- This file creates ALL tables with ALL fixes applied
-- Use this file ONLY - no other database files needed
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================
-- DROP EXISTING TABLES (if they exist)
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activations`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `deadline_reminders`;
DROP TABLE IF EXISTS `disbursements`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `email_queue`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `interview_assignments`;
DROP TABLE IF EXISTS `interview_groups`;
DROP TABLE IF EXISTS `interview_sessions`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `scholarships`;
DROP TABLE IF EXISTS `scholarship_documents`;
DROP TABLE IF EXISTS `student_profiles`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- CREATE TABLES WITH ALL FIXES APPLIED
-- ============================================

-- Table: users (FIXED - added username, last_activity)
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(50) DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `role` ENUM('student','staff','admin') DEFAULT 'student',
  `email_verified` TINYINT(1) DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `must_change_password` TINYINT(1) DEFAULT 0,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `last_activity` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `student_id` (`student_id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: student_profiles (FIXED - added university, enrollment_status, student_number)
CREATE TABLE `student_profiles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `gender` VARCHAR(20) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `province` VARCHAR(100) DEFAULT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `gpa` DECIMAL(3,2) DEFAULT NULL,
  `year_level` VARCHAR(50) DEFAULT NULL,
  `course` VARCHAR(255) DEFAULT NULL,
  `university` VARCHAR(255) DEFAULT NULL,
  `enrollment_status` ENUM('enrolled','not_enrolled','graduated','dropped') DEFAULT 'enrolled',
  `student_number` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  INDEX `idx_student_number` (`student_number`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: scholarships (FIXED - added category, gpa_requirement, income_requirement, max_scholars, auto_close, archived_at)
CREATE TABLE `scholarships` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `organization` VARCHAR(255) DEFAULT NULL,
  `eligibility_requirements` TEXT DEFAULT NULL,
  `renewal_requirements` TEXT DEFAULT NULL,
  `gpa_requirement` DECIMAL(3,2) DEFAULT NULL,
  `income_requirement` DECIMAL(10,2) DEFAULT NULL,
  `max_scholars` INT(11) DEFAULT NULL,
  `amount` DECIMAL(10,2) DEFAULT 0.00,
  `deadline` DATE DEFAULT NULL,
  `auto_close` TINYINT(1) DEFAULT 0,
  `status` ENUM('open','closed','cancelled') DEFAULT 'open',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_deadline` (`deadline`),
  INDEX `idx_category` (`category`),
  INDEX `created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: applications (FIXED - added details, family_income, title, waitlisted_at)
CREATE TABLE `applications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `scholarship_id` INT(11) NOT NULL,
  `status` ENUM('draft','submitted','under_review','pending','approved','rejected','waitlisted') DEFAULT 'draft',
  `details` LONGTEXT NULL COMMENT 'JSON data of application form',
  `family_income` DECIMAL(10,2) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `waitlisted_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_scholarship` (`user_id`, `scholarship_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_submitted_at` (`submitted_at`),
  INDEX `user_id` (`user_id`),
  INDEX `scholarship_id` (`scholarship_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: documents (FIXED - added file_hash, user_id, file_size, mime_type, verification fields)
CREATE TABLE `documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) DEFAULT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_hash` VARCHAR(64) DEFAULT NULL COMMENT 'MD5 hash for duplicate detection',
  `file_size` INT(11) DEFAULT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `verification_status` ENUM('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` INT(11) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_file_hash` (`file_hash`),
  INDEX `idx_verification_status` (`verification_status`),
  INDEX `application_id` (`application_id`),
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: disbursements
CREATE TABLE `disbursements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) DEFAULT NULL,
  `user_id` INT(11) NOT NULL,
  `scholarship_id` INT(11) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `disbursement_date` DATE NOT NULL,
  `payment_method` VARCHAR(100) NOT NULL DEFAULT 'Cash',
  `transaction_reference` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','processing','completed','failed') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `application_id` (`application_id`),
  INDEX `user_id` (`user_id`),
  INDEX `scholarship_id` (`scholarship_id`),
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: interview_sessions (FIXED - renamed columns to match code)
CREATE TABLE `interview_sessions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `session_date` DATE NOT NULL,
  `time_block` ENUM('AM','PM') NOT NULL,
  `time_start` TIME NOT NULL,
  `time_end` TIME NOT NULL,
  `max_capacity` INT(11) DEFAULT 20,
  `current_count` INT(11) DEFAULT 0,
  `status` ENUM('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_date_period` (`session_date`,`time_block`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: interview_groups (FIXED - renamed capacity to max_capacity, added current_count)
CREATE TABLE `interview_groups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `session_id` INT(11) NOT NULL,
  `group_code` VARCHAR(10) NOT NULL,
  `max_capacity` INT(11) DEFAULT 10,
  `current_count` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_group` (`session_id`,`group_code`),
  INDEX `idx_session_id` (`session_id`),
  FOREIGN KEY (`session_id`) REFERENCES `interview_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: interview_assignments (FIXED - renamed columns to match code)
CREATE TABLE `interview_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `session_id` INT(11) NOT NULL,
  `group_id` INT(11) NOT NULL,
  `assignment_number` INT(11) NOT NULL,
  `attendance_status` ENUM('pending','present','absent') DEFAULT 'pending',
  `orientation_status` ENUM('pending','completed','skipped') DEFAULT 'pending',
  `interview_status` ENUM('pending','completed','cancelled','no_show') DEFAULT 'pending',
  `final_status` ENUM('pending','completed','no_show') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  INDEX `idx_session_id` (`session_id`),
  INDEX `idx_application_id` (`application_id`),
  INDEX `user_id` (`user_id`),
  INDEX `group_id` (`group_id`),
  FOREIGN KEY (`session_id`) REFERENCES `interview_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: feedback
CREATE TABLE `feedback` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `scholarship_id` INT(11) DEFAULT NULL,
  `category` VARCHAR(50) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('pending','reviewed','resolved') DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_scholarship_id` (`scholarship_id`),
  INDEX `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notifications
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT 'info',
  `related_application_id` INT(11) DEFAULT NULL,
  `related_scholarship_id` INT(11) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `seen` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: announcements
CREATE TABLE `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','urgent') DEFAULT 'info',
  `created_by` INT(11) DEFAULT NULL,
  `published` TINYINT(1) DEFAULT 1,
  `published_at` DATETIME DEFAULT NULL,
  `expires_at` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: scholarship_documents
CREATE TABLE `scholarship_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `scholarship_id` INT(11) NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `scholarship_id` (`scholarship_id`),
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: deadline_reminders
CREATE TABLE `deadline_reminders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `scholarship_id` INT(11) NOT NULL,
  `sent_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `scholarship_id` (`scholarship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: activations
CREATE TABLE `activations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `user_id` (`user_id`),
  INDEX `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: password_resets (ENHANCED - added expiration and usage tracking)
CREATE TABLE `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_expires_at` (`expires_at`),
  INDEX `email` (`email`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: audit_logs (NEW - for comprehensive audit trail)
CREATE TABLE `audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(100) DEFAULT NULL,
  `record_id` INT(11) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: email_queue (NEW - for asynchronous email sending)
CREATE TABLE `email_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `status` ENUM('pending','sent','failed') DEFAULT 'pending',
  `attempts` INT(11) DEFAULT 0,
  `last_attempt` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rate_limits (NEW - for API rate limiting)
CREATE TABLE `rate_limits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(255) NOT NULL COMMENT 'IP address or user ID',
  `action` VARCHAR(100) NOT NULL COMMENT 'Action being rate limited',
  `attempts` INT(11) DEFAULT 1,
  `window_start` DATETIME NOT NULL,
  `blocked_until` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idx_identifier_action` (`identifier`, `action`),
  INDEX `idx_window_start` (`window_start`),
  INDEX `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: login_attempts (for tracking failed login attempts)
CREATE TABLE `login_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identifier_type` ENUM('username','ip_address') NOT NULL,
  `identifier_value` VARCHAR(255) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_identifier` (`identifier_type`, `identifier_value`),
  INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT ADMIN USER
-- ============================================

INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `email_verified`, `active`, `created_at`, `updated_at`)
VALUES (
    'admin',
    'admin@scholarhub.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Admin',
    'User',
    'admin',
    1,
    1,
    NOW(),
    NOW()
);

-- ============================================
-- COMMIT TRANSACTION
-- ============================================

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================
-- SETUP COMPLETE!
-- ============================================
-- Default Admin Login:
-- Username: admin
-- Password: password
-- 
-- IMPORTANT: Change this password immediately after first login!
-- ============================================
