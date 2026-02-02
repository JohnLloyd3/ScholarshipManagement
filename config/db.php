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
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            email VARCHAR(150) UNIQUE,
            phone VARCHAR(50),
            address TEXT,
            role ENUM('admin','reviewer','student','staff') DEFAULT 'student',
            active TINYINT(1) DEFAULT 1,
            email_verified TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Add active and email_verified columns if they don't exist
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) DEFAULT 1");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','reviewer','student','staff') DEFAULT 'student'");
        } catch (Exception $e) {}

        // Ensure applications table exists for admin flows
        $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            student_id INT NULL,
            scholarship_id INT NULL,
            title VARCHAR(255) NOT NULL,
            details TEXT,
            status ENUM('submitted','pending','approved','rejected') DEFAULT 'submitted',
            reviewer_id INT NULL,
            document VARCHAR(255) NULL,
            email VARCHAR(150) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_scholarship_id (scholarship_id),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Ensure reviews table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            reviewer_id INT NULL,
            student_id INT NULL,
            comments TEXT,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
            INDEX idx_application_id (application_id),
            INDEX idx_reviewer_id (reviewer_id),
            INDEX idx_student_id (student_id)
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
            description TEXT,
            organization VARCHAR(150) NULL,
            status ENUM('open','closed') DEFAULT 'open',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS eligibility_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scholarship_id INT NOT NULL,
            requirement VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Prevent duplicate scholarship titles per organization
        try {
            $pdo->exec("ALTER TABLE scholarships ADD UNIQUE KEY unique_scholarship (title, organization)");
        } catch (Exception $e) {
            // ignore if already exists
        }

        // Ensure students table exists (links to users)
        $pdo->exec("CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            student_number VARCHAR(50) UNIQUE,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            email VARCHAR(150) UNIQUE,
            phone VARCHAR(50),
            address TEXT,
            gpa DECIMAL(3,2),
            enrollment_status ENUM('full-time','part-time','graduated') DEFAULT 'full-time',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure documents table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            application_id INT NULL,
            document_type VARCHAR(100),
            file_name VARCHAR(255),
            file_path VARCHAR(500),
            file_size INT,
            mime_type VARCHAR(100),
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_application_id (application_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure notifications table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            type ENUM('info','success','warning','error') DEFAULT 'info',
            seen TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_seen (seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure awards table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS awards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            user_id INT NULL,
            scholarship_id INT NULL,
            award_amount DECIMAL(10,2),
            award_date DATE,
            status ENUM('pending','approved','disbursed','cancelled') DEFAULT 'pending',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_application_id (application_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure disbursements table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS disbursements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            award_id INT NOT NULL,
            user_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            disbursement_date DATE,
            payment_method VARCHAR(50),
            transaction_reference VARCHAR(255),
            status ENUM('pending','processed','completed','failed') DEFAULT 'pending',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (award_id) REFERENCES awards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_award_id (award_id),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed demo scholarships and eligibility rules if none exist
        $scount = $pdo->query('SELECT COUNT(*) FROM scholarships')->fetchColumn();
        if (!$scount) {
            $stmt = $pdo->prepare('INSERT INTO scholarships (title, description, organization, status) VALUES (:t, :d, :o, :s)');
            $stmt->execute([':t' => 'Academic Excellence Scholarship', ':d' => 'For top performing students with GPA above 3.5', ':o' => 'University Fund', ':s' => 'open']);
            $rid1 = $pdo->lastInsertId();
            $stmt->execute([':t' => 'STEM Innovators Grant', ':d' => 'Supporting STEM research and innovation', ':o' => 'Tech Foundation', ':s' => 'open']);
            $rid2 = $pdo->lastInsertId();

            $stmt2 = $pdo->prepare('INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)');
            $stmt2->execute([':sid' => $rid1, ':req' => 'GPA >= 3.5']);
            $stmt2->execute([':sid' => $rid1, ':req' => 'Enrolled full-time']);
            $stmt2->execute([':sid' => $rid2, ':req' => 'Pursuing degree in STEM']);
        }

        // Seed an admin and reviewer for testing if they do not exist
        $check = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','reviewer')")->fetchColumn();
        if (!$check) {
            $pwAdmin = password_hash('admin123', PASSWORD_DEFAULT);
            $pwReviewer = password_hash('reviewer123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, role, active, email_verified) VALUES (:u, :p, :f, :l, :e, :r, :a, 1)');
            $stmt->execute([':u' => 'admin', ':p' => $pwAdmin, ':f' => 'Admin', ':l' => 'User', ':e' => 'admin@example.com', ':r' => 'admin', ':a' => 1]);
            $stmt->execute([':u' => 'reviewer', ':p' => $pwReviewer, ':f' => 'Review', ':l' => 'User', ':e' => 'reviewer@example.com', ':r' => 'reviewer', ':a' => 1]);
        }

        return $pdo;
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log('[MySQL Connection Error] ' . $e->getMessage());
        throw $e;
    }
}
