-- Fraud Detection Migration
-- Reconciles the existing fraud_alerts table (created in 2026_03_09_interview_fraud_system.sql)
-- with the column names and ENUM values expected by FraudDetectionHelper.php

-- Step 1: Ensure the table exists (safe no-op if already created)
CREATE TABLE IF NOT EXISTS fraud_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('duplicate_application', 'duplicate_document', 'suspicious_income', 'multiple_accounts', 'other') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    user_id INT,
    application_id INT,
    document_id INT,
    description TEXT NOT NULL,
    evidence JSON,
    status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_application_id (application_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_type (alert_type),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 2: If the table already exists with the old schema, alter it to match
-- Modify status ENUM to use reviewed/dismissed instead of investigating/resolved/false_positive
ALTER TABLE fraud_alerts
    MODIFY COLUMN status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending';

-- Step 3: Rename resolved_by -> reviewed_by (if old column exists)
-- Use a safe approach: add new column, copy data, drop old column
ALTER TABLE fraud_alerts
    ADD COLUMN IF NOT EXISTS reviewed_by INT DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by;

-- Migrate data from old columns if they exist
UPDATE fraud_alerts SET reviewed_by = resolved_by WHERE resolved_by IS NOT NULL AND reviewed_by IS NULL;
UPDATE fraud_alerts SET reviewed_at = resolved_at WHERE resolved_at IS NOT NULL AND reviewed_at IS NULL;

-- Map old status values to new ones
UPDATE fraud_alerts SET status = 'reviewed' WHERE status IN ('resolved', 'investigating');
UPDATE fraud_alerts SET status = 'dismissed' WHERE status = 'false_positive';

-- Step 4: Drop old columns if they exist (safe with IF EXISTS in MySQL 8+)
ALTER TABLE fraud_alerts
    DROP COLUMN IF EXISTS resolved_by,
    DROP COLUMN IF EXISTS resolved_at,
    DROP COLUMN IF EXISTS resolution_notes;

-- Step 5: Ensure fraud_score and fraud_checked_at columns exist on applications
ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS fraud_score DECIMAL(5,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS fraud_checked_at TIMESTAMP NULL DEFAULT NULL;

-- Step 6: Ensure file_hash column exists on documents
ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS file_hash VARCHAR(64) DEFAULT NULL;
