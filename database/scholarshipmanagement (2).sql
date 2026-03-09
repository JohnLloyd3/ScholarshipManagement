-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 09, 2026 at 02:46 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scholarshipmanagement`
--

-- --------------------------------------------------------

--
-- Table structure for table `activations`
--

CREATE TABLE `activations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(200) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','urgent') DEFAULT 'info',
  `created_by` int(11) NOT NULL,
  `published` tinyint(1) DEFAULT 1,
  `published_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `type`, `created_by`, `published`, `published_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'Scholarship Portal Opened', 'Welcome to our Scholarship Management System! Applications are now open for the 2026 academic year.', 'success', 1, 1, '2026-02-27 15:49:48', NULL, '2026-02-27 15:49:48', '2026-02-27 15:49:48'),
(2, 'New Scholarships Available', 'Check out our newly added scholarship opportunities from leading organizations.', 'info', 1, 1, '2026-02-27 15:49:48', NULL, '2026-02-27 15:49:48', '2026-02-27 15:49:48'),
(3, 'Application Deadline Reminder', 'Remember to submit your applications before the deadline to be considered for the scholarships.', 'warning', 1, 1, '2026-02-27 15:49:48', NULL, '2026-02-27 15:49:48', '2026-02-27 15:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scholarship_id` int(11) NOT NULL,
  `motivational_letter` text NOT NULL,
  `gpa` decimal(3,2) DEFAULT NULL CHECK (`gpa` >= 0 and `gpa` <= 4.0),
  `status` enum('draft','submitted','under_review','pending','approved','rejected','withdrawn') DEFAULT 'submitted',
  `fraud_score` decimal(5,2) DEFAULT 0.00,
  `fraud_checked_at` timestamp NULL DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `user_id`, `scholarship_id`, `motivational_letter`, `gpa`, `status`, `fraud_score`, `fraud_checked_at`, `submitted_at`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 4, 1, '{\"full_name\":\"Leonelen\",\"sex\":\"Female\",\"dob\":\"2004-09-13\",\"age\":\"21\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"mobile\":\"09063212440\",\"email\":\"leonelencarmen@gmail.com\",\"home_address\":\"CADULAWAN MINGLANILLA, CEBU\",\"shs_name\":\"St. Cecilia\'s College-Cebu, Inc.\",\"shs_address\":\"Natalio Bacalso Avenue, Minglanilla, Cebu\",\"strand\":\"STEM\",\"gwa\":\"1.5\",\"year_graduated\":\"2025\",\"intended_college\":\"St. Cecilia\'s College-Cebu, Inc.\",\"course_program\":\"BSIT\",\"institution_type\":\"Private\",\"admission_letter\":\"Yes\",\"enrollment_date\":\"2025-02-15\",\"father_name\":\"Glen Carmen\",\"father_occupation\":\"none\",\"father_income\":\"9000\",\"mother_name\":\"Leony Carmen\",\"mother_occupation\":\"none\",\"mother_income\":\"10000\",\"guardian\":\"Leony Carmen\",\"total_income\":\"19000\",\"family_members\":\"8\",\"scholarship_title\":\"Academic Excellence Award\",\"receiving_other\":\"No\",\"other_scholarship_details\":\"\",\"docs_checklist\":[\"Grade 12 Report Card\",\"Certificate of Graduation\",\"Admission Letter\",\"Proof of Income\",\"Valid ID\",\"2x2 ID Picture\"],\"applicant_signature\":\"Frans Ababa\",\"applicant_date\":\"2026-02-27\"}', 1.50, 'approved', 0.00, NULL, '2026-02-27 16:18:22', '2026-03-09 08:19:14', '2026-02-27 16:18:22', '2026-03-09 08:19:14');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 4, 'APPLICATION_SUBMITTED', 'applications', 1, NULL, '{\"note\":\"Initial status: submitted\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-27 16:18:22'),
(2, 1, 'ANNOUNCEMENT_CREATED', 'announcements', 4, NULL, '{\"title\":\"Test Audit Announcement\",\"message\":\"This is a test announcement for audit logging.\",\"type\":\"info\",\"published\":1,\"expires_at\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7920', '2026-03-07 23:56:37'),
(3, 1, 'ANNOUNCEMENT_UNPUBLISHED', 'announcements', 4, '{\"id\":4,\"title\":\"Test Audit Announcement\",\"message\":\"This is a test announcement for audit logging.\",\"type\":\"info\",\"created_by\":1,\"published\":1,\"published_at\":\"2026-03-07 23:56:37\",\"expires_at\":null,\"created_at\":\"2026-03-07 23:56:37\",\"updated_at\":\"2026-03-07 23:56:37\"}', '{\"published\":0}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7920', '2026-03-07 23:56:59'),
(4, 1, 'ANNOUNCEMENT_DELETED', 'announcements', 4, '{\"id\":4,\"title\":\"Test Audit Announcement\",\"message\":\"This is a test announcement for audit logging.\",\"type\":\"info\",\"created_by\":1,\"published\":0,\"published_at\":\"2026-03-07 23:56:37\",\"expires_at\":null,\"created_at\":\"2026-03-07 23:56:37\",\"updated_at\":\"2026-03-07 23:56:59\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7920', '2026-03-07 23:57:09');

-- --------------------------------------------------------

--
-- Table structure for table `awards`
--

CREATE TABLE `awards` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scholarship_id` int(11) NOT NULL,
  `award_amount` decimal(12,2) NOT NULL CHECK (`award_amount` > 0),
  `award_date` date NOT NULL,
  `status` enum('pending','approved','disbursed','cancelled','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cron_runs`
--

CREATE TABLE `cron_runs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `script` varchar(255) DEFAULT NULL,
  `ran_at` datetime DEFAULT current_timestamp(),
  `status` varchar(32) DEFAULT NULL,
  `output` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cron_runs`
--

INSERT INTO `cron_runs` (`id`, `name`, `script`, `ran_at`, `status`, `output`) VALUES
(1, 'auto_archive_scholarships.php', 'auto_archive_scholarships.php', '2026-03-08 23:50:58', 'ok', '[Sun Mar 08 23:50:58.627019 2026] [mpm_winnt:crit] [pid 17212:tid 476] AH02965: Child: Unable to retrieve my generation from the parent\n'),
(2, 'auto_archive_scholarships.php', 'auto_archive_scholarships.php', '2026-03-08 23:51:10', 'ok', '[Sun Mar 08 23:51:10.152132 2026] [mpm_winnt:crit] [pid 21152:tid 440] AH02965: Child: Unable to retrieve my generation from the parent\n'),
(3, 'process_email_queue.php', 'process_email_queue.php', '2026-03-09 00:04:50', 'ok', '[Mon Mar 09 00:04:50.025445 2026] [mpm_winnt:crit] [pid 14592:tid 440] AH02965: Child: Unable to retrieve my generation from the parent\n'),
(4, 'process_email_queue.php', 'process_email_queue.php', '2026-03-09 09:29:30', 'ok', '[Mon Mar 09 09:29:30.305524 2026] [mpm_winnt:crit] [pid 17440:tid 444] AH02965: Child: Unable to retrieve my generation from the parent\n');

-- --------------------------------------------------------

--
-- Table structure for table `deadline_reminders`
--

CREATE TABLE `deadline_reminders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scholarship_id` int(11) NOT NULL,
  `reminder_type` enum('7_days','1_day','deadline') DEFAULT '7_days',
  `sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disbursements`
--

CREATE TABLE `disbursements` (
  `id` int(11) NOT NULL,
  `award_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL CHECK (`amount` > 0),
  `disbursement_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_reference` varchar(255) DEFAULT NULL,
  `status` enum('pending','processed','completed','failed') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `file_size` int(11) NOT NULL CHECK (`file_size` > 0),
  `mime_type` varchar(100) NOT NULL,
  `verification_status` enum('pending','verified','rejected','needs_resubmission') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `eligibility_requirements`
--

CREATE TABLE `eligibility_requirements` (
  `id` int(11) NOT NULL,
  `scholarship_id` int(11) NOT NULL,
  `requirement` varchar(255) DEFAULT NULL,
  `requirement_type` enum('gpa','enrollment','field','documents') DEFAULT 'documents',
  `value` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eligibility_requirements`
--

INSERT INTO `eligibility_requirements` (`id`, `scholarship_id`, `requirement`, `requirement_type`, `value`, `created_at`) VALUES
(1, 1, 'GPA >= 3.5', 'gpa', '3.5', '2026-02-27 15:49:48'),
(2, 1, 'Enrolled full-time', 'enrollment', 'full-time', '2026-02-27 15:49:48'),
(3, 2, 'Studying STEM field', 'field', 'STEM', '2026-03-07 21:45:11'),
(4, 2, 'GPA >= 3.0', 'gpa', '3.0', '2026-03-07 21:45:11'),
(5, 3, 'Completed volunteer hours', 'documents', '100', '2026-03-07 21:45:11'),
(6, 1, 'Minimum GPA', 'gpa', '3.5', '2026-03-07 21:50:18'),
(7, 1, 'Annual Income', '', '50000', '2026-03-07 21:50:18'),
(8, 2, 'STEM Major', '', 'Computer Science,Engineering,Mathematics,Physics', '2026-03-07 21:50:18'),
(9, 2, 'Project Proposal', '', 'required', '2026-03-07 21:50:18'),
(10, 3, 'Community Hours', '', '50', '2026-03-07 21:50:18'),
(11, 3, 'Recommendation Letter', '', 'required', '2026-03-07 21:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('queued','sent','failed') DEFAULT 'queued',
  `attempts` int(11) DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `user_id`, `email`, `subject`, `body`, `status`, `attempts`, `last_attempt_at`, `created_at`) VALUES
(2, NULL, 'racazajohnlloyd18@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>476291</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 19:41:40', '2026-03-08 12:29:30'),
(3, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>459850</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 19:41:44', '2026-03-08 12:30:00'),
(4, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>248097</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 19:41:49', '2026-03-08 13:00:34'),
(5, NULL, 'racazajohnlloyd18@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>277128</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 19:41:54', '2026-03-08 13:06:08'),
(6, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>135661</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 19:41:59', '2026-03-08 13:11:54'),
(7, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>227625</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 19:42:03', '2026-03-08 17:47:24'),
(8, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>197928</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 2, '2026-03-08 19:42:07', '2026-03-08 18:00:22'),
(9, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>839282</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 2, '2026-03-08 19:42:11', '2026-03-08 18:05:14'),
(10, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>759851</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 2, '2026-03-08 19:42:16', '2026-03-08 18:10:29'),
(11, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>530303</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 2, '2026-03-08 19:42:25', '2026-03-08 18:16:00'),
(12, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>385270</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 2, '2026-03-08 19:42:28', '2026-03-08 18:20:54'),
(13, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>903449</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 2, '2026-03-08 19:42:33', '2026-03-08 18:27:42'),
(14, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>766325</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 2, '2026-03-08 19:42:37', '2026-03-08 18:28:11'),
(15, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>639913</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 18:30:49', '2026-03-08 18:30:44'),
(16, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>466319</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-08 18:33:05', '2026-03-08 18:32:59'),
(17, 1, 'johnlloydracaza88@gmail.com', 'Password Reset Code', '\r\n    <!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\'UTF-8\'>\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }\r\n            .footer { margin-top: 30px; font-size: 12px; color: #666; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\'container\'>\r\n            <h2>Password Reset Code</h2>\r\n            <p>You have requested to reset your password. Please use the following code to proceed:</p>\r\n            <div class=\'code-box\'>615605</div>\r\n            <p>This code will expire in 15 minutes.</p>\r\n            <p>If you did not request a password reset, please ignore this email and secure your account.</p>\r\n            <div class=\'footer\'>\r\n                <p>© 2026 Scholarship Management System</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>\r\n    ', 'sent', 1, '2026-03-09 00:07:01', '2026-03-09 00:06:57'),
(18, 4, 'leonelencarmen@gmail.com', 'Application Status Update - Academic Excellence Award', '<h2>Application Status Update</h2><p>Dear Leonelen,</p><p>Your application for <strong>Academic Excellence Award</strong> has been updated.</p><p><strong>New Status:</strong> <span style=\"color: #c41e3a; font-weight: bold;\">Rejected</span></p><p>Please log in to your account to view full details.</p><p>Best regards,<br>ScholarHub Team</p>', 'sent', 1, '2026-03-09 08:19:00', '2026-03-09 08:18:54'),
(19, 4, 'leonelencarmen@gmail.com', 'Application Status Update - Academic Excellence Award', '<h2>Application Status Update</h2><p>Dear Leonelen,</p><p>Your application for <strong>Academic Excellence Award</strong> has been updated.</p><p><strong>New Status:</strong> <span style=\"color: #c41e3a; font-weight: bold;\">Approved</span></p><p>Please log in to your account to view full details.</p><p>Best regards,<br>ScholarHub Team</p>', 'sent', 1, '2026-03-09 08:19:19', '2026-03-09 08:19:14');

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_codes`
--

CREATE TABLE `email_verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `code` varchar(10) NOT NULL,
  `type` enum('verification','login','password_reset') DEFAULT 'verification',
  `used` tinyint(1) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification_codes`
--

INSERT INTO `email_verification_codes` (`id`, `user_id`, `email`, `code`, `type`, `used`, `expires_at`, `created_at`) VALUES
(2, 1, 'johnlloydracaza88@gmail.com', '459850', 'password_reset', 0, '2026-03-08 05:45:00', '2026-03-08 12:30:00'),
(4, 1, 'johnlloydracaza88@gmail.com', '248097', 'password_reset', 0, '2026-03-08 06:15:34', '2026-03-08 13:00:34'),
(8, 1, 'johnlloydracaza88@gmail.com', '135661', 'password_reset', 0, '2026-03-08 06:26:54', '2026-03-08 13:11:54'),
(9, 1, 'johnlloydracaza88@gmail.com', '227625', 'password_reset', 0, '2026-03-08 11:02:24', '2026-03-08 17:47:24'),
(10, 1, 'johnlloydracaza88@gmail.com', '197928', 'password_reset', 0, '2026-03-08 11:15:22', '2026-03-08 18:00:22');

-- --------------------------------------------------------

--
-- Table structure for table `fraud_alerts`
--

CREATE TABLE `fraud_alerts` (
  `id` int(11) NOT NULL,
  `alert_type` enum('duplicate_application','duplicate_document','suspicious_income','multiple_accounts','other') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `user_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),
  `status` enum('pending','investigating','resolved','false_positive') DEFAULT 'pending',
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_bookings`
--

CREATE TABLE `interview_bookings` (
  `id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('scheduled','confirmed','completed','cancelled','no-show') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `booked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interview_bookings`
--

INSERT INTO `interview_bookings` (`id`, `slot_id`, `application_id`, `user_id`, `status`, `notes`, `booked_at`, `confirmed_at`, `cancelled_at`) VALUES
(1, 1, 1, 4, 'scheduled', NULL, '2026-03-09 01:26:50', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `interview_slots`
--

CREATE TABLE `interview_slots` (
  `id` int(11) NOT NULL,
  `scholarship_id` int(11) DEFAULT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time NOT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `location` varchar(255) DEFAULT NULL,
  `interview_type` enum('in-person','online','phone') DEFAULT 'online',
  `meeting_link` varchar(500) DEFAULT NULL,
  `max_applicants` int(11) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interview_slots`
--

INSERT INTO `interview_slots` (`id`, `scholarship_id`, `interview_date`, `interview_time`, `duration_minutes`, `location`, `interview_type`, `meeting_link`, `max_applicants`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-03-10', '10:00:00', 30, 'Room 205, Main Building', 'in-person', '', 1, 2, '2026-03-09 01:26:50', '2026-03-09 01:26:50');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','application','deadline') DEFAULT 'info',
  `related_application_id` int(11) DEFAULT NULL,
  `related_scholarship_id` int(11) DEFAULT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `seen_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_application_id`, `related_scholarship_id`, `seen`, `seen_at`, `created_at`) VALUES
(1, 4, 'Application Approved', 'Congratulations! Your application for Academic Excellence Award has been approved.', 'success', 1, NULL, 1, '2026-03-09 09:40:02', '2026-02-27 16:19:19'),
(2, 4, 'Application Status Updated', 'Your application for \"Academic Excellence Award\" status has been updated to: Rejected', 'application', 1, 1, 1, '2026-03-09 09:40:02', '2026-03-09 00:14:45'),
(3, 4, 'Application Status Updated', 'Your application for \"Academic Excellence Award\" status has been updated to: Rejected', 'application', 1, 1, 1, '2026-03-09 09:40:02', '2026-03-09 00:14:49'),
(4, 4, 'Application Status Updated', 'Your application for \"Academic Excellence Award\" status has been updated to: Approved', 'application', 1, 1, 1, '2026-03-09 09:40:02', '2026-03-09 00:14:53'),
(5, 4, 'Application Status Updated', 'Your application for \"Academic Excellence Award\" status has been updated to: Rejected', 'application', 1, 1, 1, '2026-03-09 09:40:02', '2026-03-09 08:18:54'),
(6, 4, 'Application Status Updated', 'Your application for \"Academic Excellence Award\" status has been updated to: Approved', 'application', 1, 1, 1, '2026-03-09 09:40:02', '2026-03-09 08:19:14'),
(7, 4, 'Interview Scheduled', 'Your interview has been scheduled for Mar 10, 2026 10:00 AM', 'application', 1, NULL, 1, '2026-03-09 09:40:02', '2026-03-09 09:26:50');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(200) NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarships`
--

CREATE TABLE `scholarships` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `organization` varchar(150) NOT NULL,
  `eligibility_requirements` text DEFAULT NULL,
  `renewal_requirements` text DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL CHECK (`amount` > 0),
  `deadline` date NOT NULL,
  `status` enum('open','closed','cancelled') DEFAULT 'open',
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholarships`
--

INSERT INTO `scholarships` (`id`, `title`, `description`, `organization`, `eligibility_requirements`, `renewal_requirements`, `amount`, `deadline`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Academic Excellence Award', 'For students maintaining GPA above 3.5', 'University Foundation', NULL, NULL, 50000.00, '2026-04-28', 'open', 1, '2026-02-27 15:49:48', '2026-02-27 15:49:48'),
(2, 'STEM Innovation Grant', 'Supporting STEM research and innovation', 'Tech Academy', NULL, NULL, 75000.00, '2026-04-21', 'open', 1, '2026-03-07 21:45:11', '2026-03-07 21:45:11'),
(3, 'Community Service Scholarship', 'For students with strong community involvement', 'Local Foundation', NULL, NULL, 30000.00, '2026-04-06', 'open', 1, '2026-03-07 21:45:11', '2026-03-07 21:45:11'),
(4, 'Academic Excellence Scholarship', 'Awarded to students with outstanding academic performance. Requirements: GPA 3.5+, Income under $50,000', 'University Foundation', 'GPA 3.5+, Annual income under $50,000, Full-time enrollment', '', 5000.00, '2026-12-31', 'open', 1, '2026-03-07 21:50:18', '2026-03-09 09:19:04'),
(5, 'STEM Innovation Grant', 'For students pursuing STEM fields with innovative projects. Requirements: STEM major, Project proposal', 'Tech Corp Foundation', 'STEM major, Project proposal required, GPA 3.0+', NULL, 7500.00, '2026-11-15', 'open', 1, '2026-03-07 21:50:18', '2026-03-07 21:50:18'),
(6, 'Community Service Award', 'Recognizing students with significant community involvement. Requirements: 50+ community hours', 'Community Foundation', '50+ community service hours, Recommendation letter', NULL, 3000.00, '2026-10-30', 'open', 1, '2026-03-07 21:50:18', '2026-03-07 21:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_archive`
--

CREATE TABLE `scholarship_archive` (
  `id` int(11) NOT NULL,
  `scholarship_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `archived_at` datetime DEFAULT current_timestamp(),
  `archived_by` int(11) DEFAULT NULL,
  `original_status` enum('open','closed','cancelled') DEFAULT 'closed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_documents`
--

CREATE TABLE `scholarship_documents` (
  `id` int(11) NOT NULL,
  `scholarship_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholarship_documents`
--

INSERT INTO `scholarship_documents` (`id`, `scholarship_id`, `document_name`, `created_at`) VALUES
(1, 1, 'Transcript', '2026-03-07 21:50:18'),
(2, 1, 'Recommendation Letter', '2026-03-07 21:50:18'),
(3, 2, 'Project Proposal', '2026-03-07 21:50:18'),
(4, 2, 'Resume', '2026-03-07 21:50:18'),
(5, 3, 'Community Service Log', '2026-03-07 21:50:18'),
(6, 3, 'Recommendation Letter', '2026-03-07 21:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL CHECK (`gpa` >= 0 and `gpa` <= 4.0),
  `university` varchar(150) NOT NULL,
  `course` varchar(150) NOT NULL,
  `enrollment_status` enum('full-time','part-time','graduated') DEFAULT 'full-time',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`id`, `user_id`, `student_number`, `gpa`, `university`, `course`, `enrollment_status`, `created_at`, `updated_at`) VALUES
(1, 4, 'STU001', 3.75, 'State University', 'Computer Science', 'full-time', '2026-02-27 15:49:48', '2026-02-27 15:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','student','staff') DEFAULT 'student',
  `active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `secret_question` varchar(255) DEFAULT NULL,
  `secret_answer_hash` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `phone`, `address`, `role`, `active`, `email_verified`, `created_at`, `updated_at`, `secret_question`, `secret_answer_hash`, `profile_picture`) VALUES
(1, 'johnlloyd', '$2y$10$tJzMZy1y1U3kgsoUwAAnq.3qvYinFgA25hoEaEw7U3Kg/acT.Og3u', 'John Lloyd', 'Admin', 'johnlloydracaza88@gmail.com', '+639951954551', 'LOWER TUNGHAAN MINGLANILLA, CEBU', 'admin', 1, 1, '2026-02-27 15:49:48', '2026-03-09 00:07:24', NULL, NULL, 'uploads/profiles/profile_1_1772981550.jpg'),
(2, 'jaylester', '$2y$10$/xlVra5wnLw2V.qBNEsPUudvg986495hu0QAaNz26puVHz6XaGWA6', 'Jay Lester', 'Staff', 'jaylesterracaza@gmail.com', '+63900000002', NULL, 'staff', 1, 1, '2026-02-27 15:49:48', '2026-03-08 23:11:39', NULL, NULL, NULL),
(4, 'leonelen', '$2y$10$/xlVra5wnLw2V.qBNEsPUudvg986495hu0QAaNz26puVHz6XaGWA6', 'Leonelen', 'Student', 'leonelencarmen@gmail.com', '+63900000004', NULL, 'student', 1, 1, '2026-02-27 15:49:48', '2026-02-27 16:15:25', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activations`
--
ALTER TABLE `activations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_published` (`published`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_application` (`user_id`,`scholarship_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_scholarship_id` (`scholarship_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted_at` (`submitted_at`),
  ADD KEY `idx_fraud_score` (`fraud_score`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `awards`
--
ALTER TABLE `awards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_scholarship_id` (`scholarship_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `cron_runs`
--
ALTER TABLE `cron_runs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deadline_reminders`
--
ALTER TABLE `deadline_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `scholarship_id` (`scholarship_id`),
  ADD KEY `idx_sent` (`sent`),
  ADD KEY `idx_user_scholarship` (`user_id`,`scholarship_id`);

--
-- Indexes for table `disbursements`
--
ALTER TABLE `disbursements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_award_id` (`award_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `idx_verification_status` (`verification_status`),
  ADD KEY `idx_file_hash` (`file_hash`);

--
-- Indexes for table `eligibility_requirements`
--
ALTER TABLE `eligibility_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scholarship_id` (`scholarship_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_code_email` (`code`,`email`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `fraud_alerts`
--
ALTER TABLE `fraud_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`alert_type`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `interview_bookings`
--
ALTER TABLE `interview_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking` (`slot_id`,`application_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slot` (`slot_id`);

--
-- Indexes for table `interview_slots`
--
ALTER TABLE `interview_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date_time` (`interview_date`,`interview_time`),
  ADD KEY `idx_scholarship` (`scholarship_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_created` (`email`,`created_at`),
  ADD KEY `idx_ip_created` (`ip_address`,`created_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_application_id` (`related_application_id`),
  ADD KEY `related_scholarship_id` (`related_scholarship_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_seen` (`seen`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `scholarships`
--
ALTER TABLE `scholarships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_scholarship` (`title`,`organization`,`deadline`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_deadline` (`deadline`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `scholarship_archive`
--
ALTER TABLE `scholarship_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scholarship_id` (`scholarship_id`),
  ADD KEY `archived_by` (`archived_by`);

--
-- Indexes for table `scholarship_documents`
--
ALTER TABLE `scholarship_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scholarship_id` (`scholarship_id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `idx_gpa` (`gpa`),
  ADD KEY `idx_enrollment_status` (`enrollment_status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`),
  ADD UNIQUE KEY `email_3` (`email`),
  ADD UNIQUE KEY `email_4` (`email`),
  ADD UNIQUE KEY `email_5` (`email`),
  ADD UNIQUE KEY `email_6` (`email`),
  ADD UNIQUE KEY `email_7` (`email`),
  ADD UNIQUE KEY `email_8` (`email`),
  ADD UNIQUE KEY `email_9` (`email`),
  ADD UNIQUE KEY `email_10` (`email`),
  ADD UNIQUE KEY `email_11` (`email`),
  ADD UNIQUE KEY `email_12` (`email`),
  ADD UNIQUE KEY `email_13` (`email`),
  ADD UNIQUE KEY `email_14` (`email`),
  ADD UNIQUE KEY `email_15` (`email`),
  ADD UNIQUE KEY `email_16` (`email`),
  ADD UNIQUE KEY `email_17` (`email`),
  ADD UNIQUE KEY `email_18` (`email`),
  ADD UNIQUE KEY `email_19` (`email`),
  ADD UNIQUE KEY `email_20` (`email`),
  ADD UNIQUE KEY `email_21` (`email`),
  ADD UNIQUE KEY `email_22` (`email`),
  ADD UNIQUE KEY `email_23` (`email`),
  ADD UNIQUE KEY `email_24` (`email`),
  ADD UNIQUE KEY `email_25` (`email`),
  ADD UNIQUE KEY `email_26` (`email`),
  ADD UNIQUE KEY `email_27` (`email`),
  ADD UNIQUE KEY `email_28` (`email`),
  ADD UNIQUE KEY `email_29` (`email`),
  ADD UNIQUE KEY `email_30` (`email`),
  ADD UNIQUE KEY `email_31` (`email`),
  ADD UNIQUE KEY `email_32` (`email`),
  ADD UNIQUE KEY `email_33` (`email`),
  ADD UNIQUE KEY `email_34` (`email`),
  ADD UNIQUE KEY `email_35` (`email`),
  ADD UNIQUE KEY `email_36` (`email`),
  ADD UNIQUE KEY `email_37` (`email`),
  ADD UNIQUE KEY `email_38` (`email`),
  ADD UNIQUE KEY `email_39` (`email`),
  ADD UNIQUE KEY `email_40` (`email`),
  ADD UNIQUE KEY `email_41` (`email`),
  ADD UNIQUE KEY `email_42` (`email`),
  ADD UNIQUE KEY `email_43` (`email`),
  ADD UNIQUE KEY `email_44` (`email`),
  ADD UNIQUE KEY `email_45` (`email`),
  ADD UNIQUE KEY `email_46` (`email`),
  ADD UNIQUE KEY `email_47` (`email`),
  ADD UNIQUE KEY `email_48` (`email`),
  ADD UNIQUE KEY `email_49` (`email`),
  ADD UNIQUE KEY `email_50` (`email`),
  ADD UNIQUE KEY `email_51` (`email`),
  ADD UNIQUE KEY `email_52` (`email`),
  ADD UNIQUE KEY `email_53` (`email`),
  ADD UNIQUE KEY `email_54` (`email`),
  ADD UNIQUE KEY `email_55` (`email`),
  ADD UNIQUE KEY `email_56` (`email`),
  ADD UNIQUE KEY `email_57` (`email`),
  ADD UNIQUE KEY `email_58` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activations`
--
ALTER TABLE `activations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cron_runs`
--
ALTER TABLE `cron_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deadline_reminders`
--
ALTER TABLE `deadline_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disbursements`
--
ALTER TABLE `disbursements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eligibility_requirements`
--
ALTER TABLE `eligibility_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `fraud_alerts`
--
ALTER TABLE `fraud_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interview_bookings`
--
ALTER TABLE `interview_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `interview_slots`
--
ALTER TABLE `interview_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scholarships`
--
ALTER TABLE `scholarships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `scholarship_archive`
--
ALTER TABLE `scholarship_archive`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scholarship_documents`
--
ALTER TABLE `scholarship_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activations`
--
ALTER TABLE `activations`
  ADD CONSTRAINT `activations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `awards`
--
ALTER TABLE `awards`
  ADD CONSTRAINT `awards_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `awards_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `awards_ibfk_3` FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deadline_reminders`
--
ALTER TABLE `deadline_reminders`
  ADD CONSTRAINT `deadline_reminders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deadline_reminders_ibfk_2` FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `disbursements`
--
ALTER TABLE `disbursements`
  ADD CONSTRAINT `disbursements_ibfk_1` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disbursements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `eligibility_requirements`
--
ALTER TABLE `eligibility_requirements`
  ADD CONSTRAINT `eligibility_requirements_ibfk_1` FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD CONSTRAINT `email_verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`related_application_id`) REFERENCES `applications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`related_scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarships`
--
ALTER TABLE `scholarships`
  ADD CONSTRAINT `scholarships_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_archive`
--
ALTER TABLE `scholarship_archive`
  ADD CONSTRAINT `scholarship_archive_ibfk_1` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `scholarship_documents`
--
ALTER TABLE `scholarship_documents`
  ADD CONSTRAINT `scholarship_documents_ibfk_1` FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `student_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
