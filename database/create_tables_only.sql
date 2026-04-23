-- ============================================
-- SCHOLARSHIPMANAGEMENT DATABASE
-- Clean Table Creation Script (No Data)
-- ============================================

DROP DATABASE IF EXISTS `scholarshipmanagement`;
CREATE DATABASE `scholarshipmanagement` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `scholarshipmanagement`;

-- ============================================
-- CORE TABLES
-- ============================================

-- Users table (Central hub)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `student_id` VARCHAR(50) UNIQUE DEFAULT NULL COMMENT 'Student ID number - used as username for students',
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(50),
  `address` TEXT,
  `profile_picture` VARCHAR(500) DEFAULT NULL,
  `role` ENUM('admin','student','staff') DEFAULT 'student',
  `active` TINYINT(1) DEFAULT 1,
  `email_verified` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_active` (`active`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scholarships table
CREATE TABLE `scholarships` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `organization` VARCHAR(150) NOT NULL,
  `eligibility_requirements` TEXT,
  `renewal_requirements` TEXT,
  `amount` DECIMAL(12,2),
  `deadline` DATE,
  `status` ENUM('open','closed','cancelled') DEFAULT 'open',
  `category` VARCHAR(100) DEFAULT NULL,
  `gpa_requirement` DECIMAL(3,2) DEFAULT NULL,
  `income_requirement` DECIMAL(12,2) DEFAULT NULL,
  `max_scholars` INT DEFAULT NULL,
  `auto_close` TINYINT(1) DEFAULT 0,
  `archived_at` DATETIME DEFAULT NULL,
  `created_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_deadline` (`deadline`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Applications table (Junction between users and scholarships)
CREATE TABLE `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `scholarship_id` INT NOT NULL,
  `motivational_letter` TEXT NOT NULL,
  `gpa` DECIMAL(3,2),
  `family_income` DECIMAL(12,2) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `status` ENUM('draft','submitted','under_review','pending','approved','rejected','withdrawn') DEFAULT 'submitted',
  `submitted_at` DATETIME,
  `reviewed_at` DATETIME,
  `waitlisted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_application` (`user_id`, `scholarship_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_scholarship_id` (`scholarship_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- STUDENT PROFILE
-- ============================================

CREATE TABLE `student_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `student_number` VARCHAR(50) UNIQUE,
  `gpa` DECIMAL(3,2),
  `university` VARCHAR(150),
  `course` VARCHAR(150),
  `enrollment_status` ENUM('full-time','part-time','graduated') DEFAULT 'full-time',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_gpa` (`gpa`),
  INDEX `idx_enrollment_status` (`enrollment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DOCUMENTS
-- ============================================

CREATE TABLE `documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT NOT NULL,
  `file_hash` VARCHAR(64) DEFAULT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `verification_status` ENUM('pending','verified','rejected','needs_resubmission') DEFAULT 'pending',
  `verified_by` INT,
  `verified_at` DATETIME,
  `notes` TEXT,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_application_id` (`application_id`),
  INDEX `idx_verification_status` (`verification_status`),
  INDEX `idx_file_hash` (`file_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scholarship_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scholarship_id` INT NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  INDEX `idx_scholarship_id` (`scholarship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ELIGIBILITY
-- ============================================

CREATE TABLE `eligibility_requirements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scholarship_id` INT NOT NULL,
  `requirement` VARCHAR(255),
  `requirement_type` ENUM('gpa','enrollment','field','documents') DEFAULT 'documents',
  `value` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  INDEX `idx_scholarship_id` (`scholarship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DISBURSEMENTS
-- ============================================

CREATE TABLE `disbursements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `scholarship_id` INT NOT NULL,
  `application_id` INT DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `disbursement_date` DATE NOT NULL,
  `status` ENUM('pending','processing','completed','failed') DEFAULT 'pending',
  `payment_method` VARCHAR(100) NOT NULL DEFAULT 'Cash',
  `transaction_reference` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_dis_user` (`user_id`),
  INDEX `idx_dis_status` (`status`),
  INDEX `idx_dis_date` (`disbursement_date`),
  INDEX `idx_dis_application` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INTERVIEWS
-- ============================================

CREATE TABLE `interview_slots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scholarship_id` INT NOT NULL,
  `interview_date` DATE NOT NULL,
  `interview_time` TIME NOT NULL,
  `duration_minutes` INT DEFAULT 30,
  `interview_type` ENUM('in-person','online','phone') DEFAULT 'in-person',
  `location` VARCHAR(255) DEFAULT NULL,
  `meeting_link` VARCHAR(500) DEFAULT NULL,
  `max_applicants` INT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  INDEX `idx_is_scholarship` (`scholarship_id`),
  INDEX `idx_is_date` (`interview_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `interview_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slot_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `status` ENUM('scheduled','confirmed','completed','cancelled','no-show') DEFAULT 'scheduled',
  `booked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_ib_slot_app` (`slot_id`, `application_id`),
  FOREIGN KEY (`slot_id`) REFERENCES `interview_slots`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_ib_user` (`user_id`),
  INDEX `idx_ib_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FEEDBACK
-- ============================================

CREATE TABLE `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `scholarship_id` INT NOT NULL,
  `rating` INT NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_feedback_app` (`application_id`),
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  INDEX `idx_fb_user` (`user_id`),
  INDEX `idx_fb_scholarship` (`scholarship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTIFICATIONS & ANNOUNCEMENTS
-- ============================================

CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','error','application','deadline') DEFAULT 'info',
  `related_application_id` INT,
  `related_scholarship_id` INT,
  `seen` TINYINT(1) DEFAULT 0,
  `seen_at` DATETIME,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_application_id`) REFERENCES `applications`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`related_scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_seen` (`seen`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','urgent') DEFAULT 'info',
  `created_by` INT NOT NULL,
  `published` TINYINT(1) DEFAULT 1,
  `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_published` (`published`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `deadline_reminders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `scholarship_id` INT NOT NULL,
  `reminder_type` ENUM('7_days','1_day','deadline') DEFAULT '7_days',
  `sent` TINYINT(1) DEFAULT 0,
  `sent_at` DATETIME,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  INDEX `idx_sent` (`sent`),
  INDEX `idx_user_scholarship` (`user_id`, `scholarship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUTHENTICATION & SECURITY
-- ============================================

CREATE TABLE `activations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(200) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `email` VARCHAR(150) NULL,
  `token` VARCHAR(200) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_verification_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `email` VARCHAR(150) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `type` ENUM('verification','login','password_reset') DEFAULT 'verification',
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_code_email` (`code`, `email`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_username_created` (`username`, `created_at`),
  INDEX `idx_email_created` (`email`, `created_at`),
  INDEX `idx_ip_created` (`ip_address`, `created_at`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT & LOGGING
-- ============================================

CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(128) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `target_table` VARCHAR(128) DEFAULT NULL,
  `target_id` INT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_target` (`target_table`, `target_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('queued','sent','failed') DEFAULT 'queued',
  `attempts` INT DEFAULT 0,
  `last_attempt_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ARCHIVE
-- ============================================

CREATE TABLE `scholarship_archive` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scholarship_id` INT NOT NULL,
  `archived_by` INT,
  `archived_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `reason` TEXT,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`archived_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_sa_scholarship` (`scholarship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUCCESS MESSAGE
-- ============================================

SELECT 'Database created successfully! All tables are connected with foreign keys.' AS Status;
