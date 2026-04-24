<?php
// Database configuration for MySQL
// Default values assume XAMPP (localhost, root, no password)
// Update these values to match your MySQL server configuration

// MySQL Connection Settings.
// Environment variables may override these defaults for testing/deployment.
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'scholarshipmanagement');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// MySQL DSN (Data Source Name) for PDO
if (!defined('DB_DSN')) define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

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
            student_id VARCHAR(50) UNIQUE DEFAULT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            role ENUM('admin','student','staff') DEFAULT 'student',
            active TINYINT(1) DEFAULT 1,
            email_verified TINYINT(1) DEFAULT 1,
            must_change_password TINYINT(1) DEFAULT 0,
            profile_picture VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_active (active),
            INDEX idx_student_id (student_id)
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
            UNIQUE KEY unique_application (user_id, scholarship_id),
            INDEX idx_user_id (user_id),
            INDEX idx_scholarship_id (scholarship_id),
            INDEX idx_status (status),
            INDEX idx_submitted_at (submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Review workflow removed; no reviews table created.

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

        // Ensure disbursements table removed - awards feature disabled

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

        // Ensure audit_logs table exists.
        // Keep a superset of legacy/current columns so older pages and newer helpers both work.
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(255) NOT NULL,
            entity_type VARCHAR(128) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            target_table VARCHAR(128) DEFAULT NULL,
            target_id INT DEFAULT NULL,
            description TEXT DEFAULT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            old_values JSON DEFAULT NULL,
            new_values JSON DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_target (target_table, target_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Backfill missing audit log columns for existing installs.
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN target_table VARCHAR(128) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN target_id INT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN description TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN old_value TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN new_value TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN old_values JSON DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN new_values JSON DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN ip VARCHAR(45) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD COLUMN user_agent TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs MODIFY entity_type VARCHAR(128) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs MODIFY entity_id INT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE audit_logs ADD INDEX idx_target (target_table, target_id)"); } catch (Exception $e) {}

        // Ensure login_attempts table exists using the fields AuthController writes.
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(150) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            success TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username_created (username, created_at),
            INDEX idx_email_created (email, created_at),
            INDEX idx_ip_created (ip_address, created_at),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        try { $pdo->exec("ALTER TABLE login_attempts ADD COLUMN username VARCHAR(150) NOT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE login_attempts ADD COLUMN email VARCHAR(150) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE login_attempts ADD INDEX idx_username_created (username, created_at)"); } catch (Exception $e) {}

        // Ensure fraud_alerts table removed - feature disabled
        // Ensure surveys tables removed - feature disabled

        // Ensure disbursements table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS disbursements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            scholarship_id INT NOT NULL,
            application_id INT DEFAULT NULL,
            amount DECIMAL(12,2) NOT NULL,
            disbursement_date DATE NOT NULL,
            status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
            payment_method VARCHAR(100) NOT NULL DEFAULT 'Cash',
            transaction_reference VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
            INDEX idx_dis_user (user_id),
            INDEX idx_dis_status (status),
            INDEX idx_dis_date (disbursement_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // NEW INTERVIEW SYSTEM TABLES
        // Ensure interview_sessions table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS interview_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scholarship_id INT NOT NULL,
            session_date DATE NOT NULL,
            time_block ENUM('AM','PM') NOT NULL,
            time_start TIME NOT NULL,
            time_end TIME NOT NULL,
            status ENUM('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_session (scholarship_id, session_date, time_block),
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
            INDEX idx_session_date (session_date),
            INDEX idx_time_block (time_block)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure interview_groups table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS interview_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            group_code VARCHAR(10) NOT NULL,
            max_capacity INT DEFAULT 10,
            current_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_group (session_id, group_code),
            FOREIGN KEY (session_id) REFERENCES interview_sessions(id) ON DELETE CASCADE,
            INDEX idx_group_code (group_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure interview_assignments table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS interview_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            group_id INT NOT NULL,
            assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            locked TINYINT(1) DEFAULT 1,
            attendance_status ENUM('pending','present','absent') DEFAULT 'pending',
            orientation_status ENUM('pending','done') DEFAULT 'pending',
            interview_status ENUM('pending','done') DEFAULT 'pending',
            final_status ENUM('pending','completed') DEFAULT 'pending',
            notes TEXT DEFAULT NULL,
            UNIQUE KEY uq_assignment (application_id),
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (group_id) REFERENCES interview_groups(id) ON DELETE CASCADE,
            INDEX idx_group_id (group_id),
            INDEX idx_locked (locked),
            INDEX idx_final_status (final_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure feedback table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            user_id INT NOT NULL,
            scholarship_id INT NOT NULL,
            rating INT NOT NULL,
            comment TEXT DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_feedback_app (application_id),
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
            INDEX idx_fb_user (user_id),
            INDEX idx_fb_scholarship (scholarship_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Add missing columns to applications table
        try { $pdo->exec("ALTER TABLE applications ADD COLUMN family_income DECIMAL(12,2) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE applications ADD COLUMN title VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE applications ADD COLUMN details TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE applications ADD COLUMN waitlisted_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}

        // Add missing columns to documents table
        try { $pdo->exec("ALTER TABLE documents ADD COLUMN file_hash VARCHAR(64) DEFAULT NULL"); } catch (Exception $e) {}

        // Add missing columns to scholarships table
        try { $pdo->exec("ALTER TABLE scholarships ADD COLUMN category VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE scholarships ADD COLUMN gpa_requirement DECIMAL(3,2) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE scholarships ADD COLUMN income_requirement DECIMAL(12,2) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE scholarships ADD COLUMN max_scholars INT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE scholarships ADD COLUMN auto_close TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE scholarships ADD COLUMN archived_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}

        // Add missing columns to users table
        try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(500) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN student_id VARCHAR(50) UNIQUE DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD INDEX idx_student_id (student_id)"); } catch (Exception $e) {}

        return $pdo;
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log('[MySQL Connection Error] ' . $e->getMessage());
        throw $e;
    }
}
