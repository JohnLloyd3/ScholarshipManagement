<?php
/**
 * Database Setup and Connection Test
 * Run this file in your browser to check and set up your database
 */

require_once __DIR__ . '/config/db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Scholarship Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #17a2b8;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .step h3 {
            margin-top: 0;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 Database Setup & Connection Test</h1>
        
        <?php
        echo '<div class="info">';
        echo '<strong>Configuration:</strong><br>';
        echo 'Host: ' . htmlspecialchars(DB_HOST) . '<br>';
        echo 'Database: ' . htmlspecialchars(DB_NAME) . '<br>';
        echo 'User: ' . htmlspecialchars(DB_USER) . '<br>';
        echo 'Password: ' . (DB_PASS ? '***' : '(empty)') . '<br>';
        echo '</div>';

        try {
            $pdo = getPDO();
            
            echo '<div class="success">';
            echo '<strong>✅ SUCCESS!</strong><br>';
            echo 'Database connection established successfully!<br>';
            echo 'Database "' . htmlspecialchars(DB_NAME) . '" is ready to use.';
            echo '</div>';

            // Show tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo '<div class="step">';
            echo '<h3>Database Tables (' . count($tables) . ')</h3>';
            if (count($tables) > 0) {
                echo '<ul>';
                foreach ($tables as $table) {
                    echo '<li>' . htmlspecialchars($table) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No tables found. Tables will be created automatically when you use the application.</p>';
            }
            echo '</div>';

            // Check for admin user
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $adminCount = $stmt->fetchColumn();
                if ($adminCount > 0) {
                    echo '<div class="info">';
                    echo '<strong>Admin Account:</strong> An admin account exists in the database.<br>';
                    echo 'Default credentials: username: <code>admin</code>, password: <code>admin123</code>';
                    echo '</div>';
                }
            } catch (Exception $e) {
                // Table might not exist yet, that's okay
            }

            echo '<div class="success">';
            echo '<strong>Next Steps:</strong><br>';
            echo '1. Your database is ready!<br>';
            echo '2. You can now use the application normally.<br>';
            echo '3. Visit <a href="index.php">the homepage</a> to get started.';
            echo '</div>';

        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>❌ DATABASE CONNECTION FAILED</strong><br>';
            echo 'Error: ' . htmlspecialchars($e->getMessage());
            echo '</div>';

            echo '<div class="warning">';
            echo '<h3>How to Fix This:</h3>';
            echo '<div class="step">';
            echo '<h3>Step 1: Start MySQL in XAMPP</h3>';
            echo '<ol>';
            echo '<li>Open XAMPP Control Panel</li>';
            echo '<li>Click "Start" next to MySQL</li>';
            echo '<li>Wait until MySQL shows as "Running" (green)</li>';
            echo '</ol>';
            echo '</div>';

            echo '<div class="step">';
            echo '<h3>Step 2: Verify MySQL is Running</h3>';
            echo '<p>You should see MySQL running in XAMPP Control Panel. If it won\'t start:</p>';
            echo '<ul>';
            echo '<li>Check if port 3306 is already in use</li>';
            echo '<li>Try stopping and restarting MySQL</li>';
            echo '<li>Check XAMPP error logs</li>';
            echo '</ul>';
            echo '</div>';

            echo '<div class="step">';
            echo '<h3>Step 3: Check Configuration</h3>';
            echo '<p>If MySQL is running but you still see this error, check <code>config/db.php</code>:</p>';
            echo '<ul>';
            echo '<li>Verify DB_HOST is correct (usually 127.0.0.1 or localhost)</li>';
            echo '<li>Verify DB_USER is correct (usually root for XAMPP)</li>';
            echo '<li>Verify DB_PASS matches your MySQL root password</li>';
            echo '</ul>';
            echo '</div>';

            echo '<div class="info">';
            echo '<strong>Note:</strong> The application will use file-based storage as a fallback if the database is unavailable. ';
            echo 'However, for full functionality, please ensure MySQL is running.';
            echo '</div>';
            echo '</div>';

            echo '<div class="info">';
            echo '<strong>After fixing the issue:</strong> Refresh this page to test the connection again.';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>❌ UNEXPECTED ERROR</strong><br>';
            echo 'Error: ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
