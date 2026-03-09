-- Interview Scheduling System Tables
CREATE TABLE IF NOT EXISTS interview_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scholarship_id INT,
    interview_date DATE NOT NULL,
    interview_time TIME NOT NULL,
    duration_minutes INT DEFAULT 30,
    location VARCHAR(255),
    interview_type ENUM('in-person', 'online', 'phone') DEFAULT 'online',
    meeting_link VARCHAR(500),
    max_applicants INT DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date_time (interview_date, interview_time),
    INDEX idx_scholarship (scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interview_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_id INT NOT NULL,
    application_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
    notes TEXT,
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    UNIQUE KEY unique_booking (slot_id, application_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_slot (slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fraud Detection System Tables
CREATE TABLE IF NOT EXISTS fraud_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('duplicate_application', 'duplicate_document', 'suspicious_income', 'multiple_accounts', 'other') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    user_id INT,
    application_id INT,
    document_id INT,
    description TEXT NOT NULL,
    evidence JSON,
    status ENUM('pending', 'investigating', 'resolved', 'false_positive') DEFAULT 'pending',
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add document hash column for duplicate detection
ALTER TABLE documents 
ADD COLUMN IF NOT EXISTS file_hash VARCHAR(64) AFTER file_path,
ADD INDEX idx_file_hash (file_hash);

-- Add fraud score to applications
ALTER TABLE applications
ADD COLUMN IF NOT EXISTS fraud_score DECIMAL(5,2) DEFAULT 0.00 AFTER status,
ADD COLUMN IF NOT EXISTS fraud_checked_at TIMESTAMP NULL AFTER fraud_score,
ADD INDEX idx_fraud_score (fraud_score);
