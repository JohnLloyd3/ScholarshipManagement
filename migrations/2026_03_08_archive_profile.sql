-- Migration: Add scholarship_archive table and ensure student_profiles exists

CREATE TABLE IF NOT EXISTS scholarship_archive (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scholarship_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  organization VARCHAR(150),
  amount DECIMAL(12,2),
  deadline DATE,
  archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  archived_by INT DEFAULT NULL,
  original_status ENUM('open','closed','cancelled') DEFAULT 'closed',
  INDEX idx_scholarship_id (scholarship_id),
  FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure student_profiles exists (safe-create duplicate)
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
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
