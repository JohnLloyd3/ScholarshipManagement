<?php
/**
 * Create New Tables: Interview Scheduling & Fraud Detection
 * Run this once to add the 3 new tables to your database
 */

require_once __DIR__ . '/../config/db.php';

echo "<h2>Creating New Tables...</h2>";

try {
    $pdo = getPDO();
    
    // 1. Create interview_slots table
    echo "<p>Creating <strong>interview_slots</strong> table...</p>";
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color: green;'>✅ interview_slots table created successfully!</p>";
    
    // 2. Create interview_bookings table
    echo "<p>Creating <strong>interview_bookings</strong> table...</p>";
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color: green;'>✅ interview_bookings table created successfully!</p>";
    
    // 3. Create fraud_alerts table
    echo "<p>Creating <strong>fraud_alerts</strong> table...</p>";
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color: green;'>✅ fraud_alerts table created successfully!</p>";
    
    // 4. Add file_hash column to documents table
    echo "<p>Adding <strong>file_hash</strong> column to documents table...</p>";
    try {
        $pdo->exec("ALTER TABLE documents ADD COLUMN file_hash VARCHAR(64) AFTER file_path");
        $pdo->exec("ALTER TABLE documents ADD INDEX idx_file_hash (file_hash)");
        echo "<p style='color: green;'>✅ file_hash column added to documents table!</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠️ file_hash column already exists in documents table</p>";
        } else {
            throw $e;
        }
    }
    
    // 5. Add fraud_score columns to applications table
    echo "<p>Adding <strong>fraud_score</strong> columns to applications table...</p>";
    try {
        $pdo->exec("ALTER TABLE applications ADD COLUMN fraud_score DECIMAL(5,2) DEFAULT 0.00 AFTER status");
        $pdo->exec("ALTER TABLE applications ADD COLUMN fraud_checked_at TIMESTAMP NULL AFTER fraud_score");
        $pdo->exec("ALTER TABLE applications ADD INDEX idx_fraud_score (fraud_score)");
        echo "<p style='color: green;'>✅ fraud_score columns added to applications table!</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠️ fraud_score columns already exist in applications table</p>";
        } else {
            throw $e;
        }
    }
    
    echo "<hr>";
    echo "<h3 style='color: green;'>🎉 All tables created successfully!</h3>";
    echo "<p><strong>New tables added:</strong></p>";
    echo "<ul>";
    echo "<li>✅ interview_slots</li>";
    echo "<li>✅ interview_bookings</li>";
    echo "<li>✅ fraud_alerts</li>";
    echo "</ul>";
    echo "<p><strong>New columns added:</strong></p>";
    echo "<ul>";
    echo "<li>✅ documents.file_hash</li>";
    echo "<li>✅ applications.fraud_score</li>";
    echo "<li>✅ applications.fraud_checked_at</li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='../admin/interview_slots.php'>Admin → Interview Slots</a> to create interview slots</li>";
    echo "<li>Go to <a href='../admin/fraud_detection.php'>Admin → Fraud Detection</a> to run fraud checks</li>";
    echo "<li>Students can book interviews at <a href='../member/interview_booking.php'>Interview Booking</a></li>";
    echo "</ol>";
    
    echo "<p><a href='../admin/dashboard.php' style='display: inline-block; padding: 10px 20px; background: #c41e3a; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Go to Admin Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please make sure MySQL is running in XAMPP.</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}
h2, h3 {
    color: #c41e3a;
}
p {
    line-height: 1.6;
}
ul, ol {
    line-height: 1.8;
}
</style>
