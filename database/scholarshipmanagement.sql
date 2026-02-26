-- Scholarship Management System Database Schema
-- Original schema structure - no sample data

-- ==========================================
-- 1. USERS TABLE (Core user management)
-- ==========================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(50),
  address TEXT,
  role ENUM('admin','reviewer','student','staff') DEFAULT 'student',
  active TINYINT(1) DEFAULT 1,
  email_verified TINYINT(1) DEFAULT 0,
  secret_question VARCHAR(255),
  secret_answer_hash VARCHAR(255),
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
  gpa DECIMAL(3,2),
  university VARCHAR(150),
  course VARCHAR(150),
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
  description TEXT,
  organization VARCHAR(150),
  requirements TEXT,
  amount DECIMAL(12,2),
  deadline DATE,
  status ENUM('open','closed','cancelled') DEFAULT 'open',
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_scholarship (title, organization, deadline),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
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
  title VARCHAR(255),
  details TEXT,
  motivational_letter TEXT,
  gpa DECIMAL(3,2),
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
  document_type VARCHAR(100),
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size INT,
  mime_type VARCHAR(100),
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
  rating INT DEFAULT 0,
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
  award_amount DECIMAL(12,2),
  award_date DATE,
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
  amount DECIMAL(12,2) NOT NULL,
  disbursement_date DATE,
  payment_method VARCHAR(50),
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
  title VARCHAR(255),
  message TEXT,
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
  email VARCHAR(150),
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
  user_id INT,
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
  action VARCHAR(255),
  entity_type VARCHAR(100),
  entity_id INT,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  user_agent VARCHAR(500),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 16. LOGIN ATTEMPTS TABLE (Security)
-- ==========================================
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45),
  success TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
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

-- Sample Admin User
INSERT IGNORE INTO users (id, username, password, first_name, last_name, email, phone, role, active, email_verified, created_at) 
VALUES (1, 'admin', '$2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W', 'System', 'Admin', 'admin@scholarships.com', '+63900000001', 'admin', 1, 1, NOW());

-- Sample Staff User
INSERT IGNORE INTO users (id, username, password, first_name, last_name, email, phone, role, active, email_verified, created_at) 
VALUES (2, 'staff1', '$2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W', 'John', 'Staff', 'staff@scholarships.com', '+63900000002', 'staff', 1, 1, NOW());

-- Sample Reviewer User
INSERT IGNORE INTO users (id, username, password, first_name, last_name, email, phone, role, active, email_verified, created_at) 
VALUES (3, 'reviewer1', '$2y$10$Ue5kqmNfp1NTkIo5LZfx9exVfBo/7r5K3dZLW.d7K4KLNjDnZdU6W', 'Jane', 'Reviewer', 'reviewer@scholarships.com', '+63900000003', 'reviewer', 1, 1, NOW());

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

-- Sample Announcement
INSERT IGNORE INTO announcements (id, title, message, type, created_by, published) 
VALUES 
(1, 'Scholarship Portal Opened', 'Welcome to our Scholarship Management System! Applications are now open for the 2026 academic year.', 'success', 1, 1);


