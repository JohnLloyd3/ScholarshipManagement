-- ============================================
-- NEW INTERVIEW SYSTEM MIGRATION
-- Based on Group Assignment System
-- ============================================

USE `scholarshipmanagement`;

-- Drop old interview tables
DROP TABLE IF EXISTS `interview_bookings`;
DROP TABLE IF EXISTS `interview_slots`;

-- ============================================
-- NEW INTERVIEW TABLES
-- ============================================

-- Interview Sessions Table
-- Each scholarship has 2 sessions per day (AM/PM)
CREATE TABLE `interview_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scholarship_id` INT NOT NULL,
  `session_date` DATE NOT NULL,
  `time_block` ENUM('AM','PM') NOT NULL,
  `time_start` TIME NOT NULL COMMENT '8:00 AM for AM, 1:00 PM for PM',
  `time_end` TIME NOT NULL COMMENT '11:30 AM for AM, 4:00 PM for PM',
  `status` ENUM('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_session` (`scholarship_id`, `session_date`, `time_block`),
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  INDEX `idx_session_date` (`session_date`),
  INDEX `idx_time_block` (`time_block`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Interview Groups Table
-- Each session has 2 groups (A1, A2 for AM; B1, B2 for PM)
-- Each group can hold max 10 applicants
CREATE TABLE `interview_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `group_code` VARCHAR(10) NOT NULL COMMENT 'A1, A2, B1, B2',
  `max_capacity` INT DEFAULT 10,
  `current_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_group` (`session_id`, `group_code`),
  FOREIGN KEY (`session_id`) REFERENCES `interview_sessions`(`id`) ON DELETE CASCADE,
  INDEX `idx_group_code` (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group Assignment Table
-- Links applicants to their assigned groups
-- Once assigned, locked = TRUE (cannot change)
CREATE TABLE `interview_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `locked` TINYINT(1) DEFAULT 1 COMMENT 'TRUE = cannot reschedule or change group',
  `attendance_status` ENUM('pending','present','absent') DEFAULT 'pending',
  `orientation_status` ENUM('pending','done') DEFAULT 'pending',
  `interview_status` ENUM('pending','done') DEFAULT 'pending',
  `final_status` ENUM('pending','completed') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  UNIQUE KEY `uq_assignment` (`application_id`),
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `interview_groups`(`id`) ON DELETE CASCADE,
  INDEX `idx_group_id` (`group_id`),
  INDEX `idx_locked` (`locked`),
  INDEX `idx_final_status` (`final_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUCCESS MESSAGE
-- ============================================

SELECT 'New interview system tables created successfully!' AS Status;
