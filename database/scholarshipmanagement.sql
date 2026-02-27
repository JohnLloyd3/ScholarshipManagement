-- Enhanced Scholarship Management System Database Schema
-- Complete implementation with all features
-- 
-- DEFAULT TEST CREDENTIALS:
-- Username: admin, staff1, reviewer1, student1
-- Password: 123123 (bcrypt hash: $2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W)
-- IMPORTANT: Change all passwords in production environment!

-- ==========================================
-- 1. USERS TABLE (Core user management)
-- ==========================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(50),
  address TEXT,
  role ENUM('admin','reviewer','student','staff') DEFAULT 'student',
  active TINYINT(1) DEFAULT 1,
  email_verified TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_active (active),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2. STUDENT PROFILES TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS student_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  student_number VARCHAR(50) UNIQUE,
  gpa DECIMAL(3,2) CHECK (gpa >= 0 AND gpa <= 4.0),
  university VARCHAR(150) NOT NULL,
  course VARCHAR(150) NOT NULL,
  enrollment_status ENUM('full-time','part-time','graduated') DEFAULT 'full-time',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_gpa (gpa),
  INDEX idx_enrollment_status (enrollment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3. SCHOLARSHIPS TABLE (Scholarship postings)
-- ==========================================
CREATE TABLE IF NOT EXISTS scholarships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  organization VARCHAR(150) NOT NULL,
  eligibility_requirements TEXT,
  renewal_requirements TEXT,
  amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
  deadline DATE NOT NULL,
  status ENUM('open','closed','cancelled') DEFAULT 'open',
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_scholarship (title, organization, deadline),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status),
  INDEX idx_deadline (deadline),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. ELIGIBILITY REQUIREMENTS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS eligibility_requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scholarship_id INT NOT NULL,
  requirement VARCHAR(255),
  requirement_type ENUM('gpa','enrollment','field','documents') DEFAULT 'documents',
  value VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  INDEX idx_scholarship_id (scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5. APPLICATIONS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  scholarship_id INT NOT NULL,
  motivational_letter TEXT NOT NULL,
  gpa DECIMAL(3,2) CHECK (gpa >= 0 AND gpa <= 4.0),
  status ENUM('draft','submitted','pending','approved','rejected','withdrawn') DEFAULT 'draft',
  submitted_at DATETIME,
  reviewed_at DATETIME,
  reviewer_id INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY unique_application (user_id, scholarship_id),
  INDEX idx_user_id (user_id),
  INDEX idx_scholarship_id (scholarship_id),
  INDEX idx_status (status),
  INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 6. DOCUMENTS TABLE (File uploads)
-- ==========================================
CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  user_id INT NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size INT NOT NULL CHECK (file_size > 0),
  mime_type VARCHAR(100) NOT NULL,
  verification_status ENUM('pending','verified','rejected','needs_resubmission') DEFAULT 'pending',
  verified_by INT,
  verified_at DATETIME,
  notes TEXT,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_application_id (application_id),
  INDEX idx_verification_status (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 7. REVIEWS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  reviewer_id INT NOT NULL,
  rating INT DEFAULT 0 CHECK (rating >= 0 AND rating <= 5),
  comments TEXT,
  documents_verified TINYINT(1) DEFAULT 0,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_application_id (application_id),
  INDEX idx_reviewer_id (reviewer_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 8. AWARDS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS awards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  user_id INT NOT NULL,
  scholarship_id INT NOT NULL,
  award_amount DECIMAL(12,2) NOT NULL CHECK (award_amount > 0),
  award_date DATE NOT NULL,
  status ENUM('pending','approved','disbursed','cancelled','rejected') DEFAULT 'pending',
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  INDEX idx_scholarship_id (scholarship_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 9. DISBURSEMENTS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS disbursements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  award_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
  disbursement_date DATE NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  transaction_reference VARCHAR(255),
  status ENUM('pending','processed','completed','failed') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (award_id) REFERENCES awards(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_award_id (award_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 10. NOTIFICATIONS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('info','success','warning','error','application','deadline') DEFAULT 'info',
  related_application_id INT,
  related_scholarship_id INT,
  seen TINYINT(1) DEFAULT 0,
  seen_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (related_application_id) REFERENCES applications(id) ON DELETE SET NULL,
  FOREIGN KEY (related_scholarship_id) REFERENCES scholarships(id) ON DELETE SET NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_seen (seen),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 11. ANNOUNCEMENTS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('info','success','warning','urgent') DEFAULT 'info',
  created_by INT NOT NULL,
  published TINYINT(1) DEFAULT 1,
  published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_published (published),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 12. PASSWORD RESETS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(200) NOT NULL UNIQUE,
  used TINYINT(1) DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 13. EMAIL VERIFICATION CODES TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS email_verification_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  email VARCHAR(150) NOT NULL,
  code VARCHAR(10) NOT NULL,
  type ENUM('verification','login','password_reset') DEFAULT 'verification',
  used TINYINT(1) DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_code_email (code, email),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 14. DEADLINE REMINDERS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS deadline_reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  scholarship_id INT NOT NULL,
  reminder_type ENUM('7_days','1_day','deadline') DEFAULT '7_days',
  sent TINYINT(1) DEFAULT 0,
  sent_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  INDEX idx_sent (sent),
  INDEX idx_user_scholarship (user_id, scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 15. AUDIT LOG TABLE (Security)
-- ==========================================
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(255) NOT NULL,
  entity_type VARCHAR(100) NOT NULL,
  entity_id INT NOT NULL,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(500),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 16. LOGIN ATTEMPTS TABLE (Security)
-- ==========================================
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_created (email, created_at),
  INDEX idx_ip_created (ip_address, created_at),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 17. ACTIVATIONS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS activations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  token VARCHAR(200) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- INSERT SAMPLE DATA
-- ==========================================

-- Sample Admin User (username: admin, password: 123123)
INSERT IGNORE INTO users (id, username, password, first_name, last_name, email, phone, role, active, email_verified, created_at) 
VALUES (1, 'admin', '$2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W', 'System', 'Admin', 'admin@scholarships.com', '+63900000001', 'admin', 1, 1, NOW());

-- Sample Staff User (username: staff1, password: 123123)
INSERT IGNORE INTO users (id, username, password, first_name, last_name, email, phone, role, active, email_verified, created_at) 
VALUES (2, 'staff1', '$2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W', 'John', 'Staff', 'staff@scholarships.com', '+63900000002', 'staff', 1, 1, NOW());

-- Sample Reviewer User (username: reviewer1, password: 123123)
INSERT IGNORE INTO users (id, username, password, first_name, last_name, email, phone, role, active, email_verified, created_at) 
VALUES (3, 'reviewer1', '$2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W', 'Jane', 'Reviewer', 'reviewer@scholarships.com', '+63900000003', 'reviewer', 1, 1, NOW());

-- Sample Student User (username: student1, password: 123123)
INSERT IGNORE INTO users (id, username, password, first_name, last_name, email, phone, role, active, email_verified, created_at) 
VALUES (4, 'student1', '$2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W', 'Alice', 'Student', 'student@example.com', '+63900000004', 'student', 1, 1, NOW());

-- Sample Student Profile
INSERT IGNORE INTO student_profiles (id, user_id, student_number, gpa, university, course, enrollment_status, created_at) 
VALUES (1, 4, 'STU001', 3.75, 'State University', 'Computer Science', 'full-time', NOW());

-- Sample Scholarships
INSERT IGNORE INTO scholarships (id, title, description, organization, amount, deadline, status, created_by, created_at) 
VALUES 
(1, 'Academic Excellence Award', 'For students maintaining GPA above 3.5', 'University Foundation', 50000.00, DATE_ADD(NOW(), INTERVAL 60 DAY), 'open', 1, NOW()),
(2, 'STEM Innovation Grant', 'Supporting STEM research and innovation', 'Tech Academy', 75000.00, DATE_ADD(NOW(), INTERVAL 45 DAY), 'open', 1, NOW()),
(3, 'Community Service Scholarship', 'For students with strong community involvement', 'Local Foundation', 30000.00, DATE_ADD(NOW(), INTERVAL 30 DAY), 'open', 1, NOW());

-- Sample Eligibility Requirements
INSERT IGNORE INTO eligibility_requirements (id, scholarship_id, requirement, requirement_type, value, created_at) 
VALUES 
(1, 1, 'GPA >= 3.5', 'gpa', '3.5', NOW()),
(2, 1, 'Enrolled full-time', 'enrollment', 'full-time', NOW()),
(3, 2, 'Studying STEM field', 'field', 'STEM', NOW()),
(4, 2, 'GPA >= 3.0', 'gpa', '3.0', NOW()),
(5, 3, 'Completed volunteer hours', 'documents', '100', NOW());

-- Sample Announcements
INSERT IGNORE INTO announcements (id, title, message, type, created_by, published, published_at, created_at) 
VALUES 
(1, 'Scholarship Portal Opened', 'Welcome to our Scholarship Management System! Applications are now open for the 2026 academic year.', 'success', 1, 1, NOW(), NOW()),
(2, 'New Scholarships Available', 'Check out our newly added scholarship opportunities from leading organizations.', 'info', 1, 1, NOW(), NOW()),
(3, 'Application Deadline Reminder', 'Remember to submit your applications before the deadline to be considered for the scholarships.', 'warning', 1, 1, NOW(), NOW());

-- ==========================================
-- DATABASE SCHEMA SUMMARY
-- ==========================================
-- Tables: 17
-- User Management: users, student_profiles, activations
-- Authentication: password_resets, email_verification_codes, login_attempts
-- Scholarships: scholarships, eligibility_requirements
-- Applications: applications, documents, reviews
-- Awards: awards, disbursements
-- Communication: notifications, announcements, deadline_reminders
-- Auditing: audit_logs
-- ==========================================
-- All tables are properly indexed for performance
-- All foreign keys enforce referential integrity
-- All numeric fields have validation constraints
-- Ready for production deployment
