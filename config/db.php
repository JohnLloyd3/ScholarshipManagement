<?php
// Database configuration for MySQL
// Default values assume XAMPP (localhost, root, no password)
// Update these values to match your MySQL server configuration

// MySQL Connection Settings
define('DB_HOST', '127.0.0.1');        // MySQL host (use 'localhost' or '127.0.0.1')
define('DB_NAME', 'scholarshipmanagement');  // Database name
define('DB_USER', 'root');              // MySQL username
define('DB_PASS', '');                  // MySQL password (empty for default XAMPP)

// MySQL DSN (Data Source Name) for PDO
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

/**
 * Get PDO connection to MySQL database
 * Automatically creates database and tables if they don't exist
 * @return PDO MySQL database connection
 */
function getPDO()
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        // First, try to connect to MySQL server without specifying database
        $dsn_no_db = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
        $pdo_temp = new PDO($dsn_no_db, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Check if database exists, create if it doesn't
        $stmt = $pdo_temp->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname");
        $stmt->execute([':dbname' => DB_NAME]);
        if (!$stmt->fetch()) {
            $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $pdo_temp = null;
        
        // Now connect to the specific MySQL database
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Ensure users table exists (non-destructive)
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Add missing columns if they don't exist
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE users MODIFY first_name VARCHAR(100) NOT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE users MODIFY last_name VARCHAR(100) NOT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE users MODIFY email VARCHAR(150) NOT NULL UNIQUE");
        } catch (Exception $e) {}
        // Add secret question/answer columns used by auth flows
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN secret_question VARCHAR(255) DEFAULT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN secret_answer_hash VARCHAR(255) DEFAULT NULL");
        } catch (Exception $e) {}

        // Ensure applications table exists for admin flows
        $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            scholarship_id INT NOT NULL,
            motivational_letter TEXT NOT NULL,
            gpa DECIMAL(3,2),
            status ENUM('draft','submitted','under_review','pending','approved','rejected','withdrawn') DEFAULT 'submitted',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Ensure reviews table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            reviewer_id INT NULL,
            comments TEXT,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_application_id (application_id),
            INDEX idx_reviewer_id (reviewer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure password_resets table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            email VARCHAR(150) NULL,
            token VARCHAR(200) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure activations table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS activations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(200) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Ensure email_verification_codes table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_verification_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            email VARCHAR(150) NOT NULL,
            code VARCHAR(10) NOT NULL,
            type ENUM('verification','login','password_reset') DEFAULT 'verification',
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_code_email (code, email),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure scholarships and eligibility tables exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS scholarships (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            organization VARCHAR(150) NOT NULL,
            eligibility_requirements TEXT,
            renewal_requirements TEXT,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Add missing columns to scholarships table if they don't exist
        try {
            $pdo->exec("ALTER TABLE scholarships ADD COLUMN eligibility_requirements TEXT");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE scholarships ADD COLUMN renewal_requirements TEXT");
        } catch (Exception $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS eligibility_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scholarship_id INT NOT NULL,
            requirement VARCHAR(255),
            requirement_type ENUM('gpa','enrollment','field','documents') DEFAULT 'documents',
            value VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
            INDEX idx_scholarship_id (scholarship_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure student_profiles table exists (links to users)
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_profiles (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure documents table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            user_id INT NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure scholarship_documents table exists (required-documents per scholarship)
        $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scholarship_id INT NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
            INDEX idx_scholarship_id (scholarship_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure email_logs table exists (queued emails for processing)
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('queued','sent','failed') DEFAULT 'queued',
            attempts INT DEFAULT 0,
            last_attempt_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure notifications table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure awards table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS awards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            user_id INT NOT NULL,
            scholarship_id INT NOT NULL,
            award_amount DECIMAL(12,2) NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure disbursements table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS disbursements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            award_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure announcements table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure deadline_reminders table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS deadline_reminders (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure audit_logs table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure login_attempts table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            success TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_created (email, created_at),
            INDEX idx_ip_created (ip_address, created_at),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        return $pdo;
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log('[MySQL Connection Error] ' . $e->getMessage());
        throw $e;
    }
}
