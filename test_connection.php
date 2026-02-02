<?php
/**
 * Database Connection Test & Verification
 * Run this file to verify all database connections and tables are set up correctly
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #fafbfc;
            padding: 2rem;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e3a5f;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 0.5rem;
        }
        .status {
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        .success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #1e3a5f;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f9fafb;
        }
        .check { color: #10b981; font-weight: bold; }
        .cross { color: #ef4444; font-weight: bold; }
        code {
            background: #f3f4f6;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Connection Test</h1>
        
        <?php
        $allGood = true;
        $tables = [];
        
        try {
            $pdo = getPDO();
            
            echo '<div class="status success">';
            echo '<strong>✅ Database Connection Successful!</strong><br>';
            echo 'Connected to database: <code>' . htmlspecialchars(DB_NAME) . '</code><br>';
            echo 'Host: <code>' . htmlspecialchars(DB_HOST) . '</code><br>';
            echo 'User: <code>' . htmlspecialchars(DB_USER) . '</code>';
            echo '</div>';
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Expected tables based on schema
            $expectedTables = [
                'users',
                'applications',
                'scholarships',
                'eligibility_requirements',
                'reviews',
                'students',
                'documents',
                'notifications',
                'awards',
                'disbursements',
                'password_resets',
                'activations',
                'email_verification_codes'
            ];
            
            echo '<div class="status info">';
            echo '<strong>📊 Database Tables Status</strong><br>';
            echo 'Found ' . count($tables) . ' table(s) in database.';
            echo '</div>';
            
            echo '<table>';
            echo '<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>';
            
            foreach ($expectedTables as $table) {
                $exists = in_array($table, $tables);
                $rowCount = 0;
                
                if ($exists) {
                    try {
                        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                        $rowCount = $countStmt->fetchColumn();
                    } catch (Exception $e) {
                        $rowCount = 'Error';
                    }
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($table) . '</code></td>';
                    echo '<td><span class="check">✅ Exists</span></td>';
                    echo '<td>' . number_format($rowCount) . '</td>';
                    echo '</tr>';
                } else {
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($table) . '</code></td>';
                    echo '<td><span class="cross">❌ Missing</span></td>';
                    echo '<td>-</td>';
                    echo '</tr>';
                    $allGood = false;
                }
            }
            
            // Show any extra tables
            $extraTables = array_diff($tables, $expectedTables);
            if (!empty($extraTables)) {
                foreach ($extraTables as $table) {
                    try {
                        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                        $rowCount = $countStmt->fetchColumn();
                    } catch (Exception $e) {
                        $rowCount = 'Error';
                    }
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($table) . '</code></td>';
                    echo '<td><span class="check">✅ (Extra)</span></td>';
                    echo '<td>' . number_format($rowCount) . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</table>';
            
            // Check for admin user
            try {
                $adminStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $adminCount = $adminStmt->fetchColumn();
                
                if ($adminCount > 0) {
                    echo '<div class="status success">';
                    echo '<strong>✅ Admin Account Found</strong><br>';
                    echo 'There are ' . $adminCount . ' admin account(s) in the database.<br>';
                    echo 'Default credentials: <code>admin</code> / <code>admin123</code>';
                    echo '</div>';
                } else {
                    echo '<div class="status info">';
                    echo '<strong>ℹ️ No Admin Account</strong><br>';
                    echo 'Admin account will be created automatically on first database connection.';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">';
                echo '<strong>❌ Error checking admin account:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            
            // Test sample queries
            echo '<div class="status info">';
            echo '<strong>🧪 Testing Sample Queries</strong>';
            echo '</div>';
            
            $testQueries = [
                'SELECT COUNT(*) FROM users' => 'Users count',
                'SELECT COUNT(*) FROM applications' => 'Applications count',
                'SELECT COUNT(*) FROM scholarships' => 'Scholarships count',
                'SELECT COUNT(*) FROM reviews' => 'Reviews count',
                'SELECT COUNT(*) FROM notifications' => 'Notifications count'
            ];
            
            echo '<table>';
            echo '<tr><th>Query</th><th>Result</th><th>Status</th></tr>';
            
            foreach ($testQueries as $query => $label) {
                try {
                    $testStmt = $pdo->query($query);
                    $result = $testStmt->fetchColumn();
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($label) . '</code></td>';
                    echo '<td>' . number_format($result) . '</td>';
                    echo '<td><span class="check">✅ OK</span></td>';
                    echo '</tr>';
                } catch (Exception $e) {
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($label) . '</code></td>';
                    echo '<td>Error</td>';
                    echo '<td><span class="cross">❌ Failed</span></td>';
                    echo '</tr>';
                    $allGood = false;
                }
            }
            
            echo '</table>';
            
            if ($allGood) {
                echo '<div class="status success">';
                echo '<strong>🎉 All Systems Operational!</strong><br>';
                echo 'Your database is fully connected and all tables are set up correctly.<br>';
                echo '<a href="index.php" style="color: #166534; font-weight: 600; margin-top: 0.5rem; display: inline-block;">← Go to Homepage</a>';
                echo '</div>';
            } else {
                echo '<div class="status error">';
                echo '<strong>⚠️ Some Issues Detected</strong><br>';
                echo 'Please refresh this page to recreate missing tables, or check the error logs.';
                echo '</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="status error">';
            echo '<strong>❌ Database Connection Failed</strong><br>';
            echo 'Error: <code>' . htmlspecialchars($e->getMessage()) . '</code><br><br>';
            echo '<strong>How to Fix:</strong><br>';
            echo '1. Make sure MySQL is running in XAMPP Control Panel<br>';
            echo '2. Check that the database credentials in <code>config/db.php</code> are correct<br>';
            echo '3. Verify that the PDO MySQL extension is enabled in PHP<br>';
            echo '4. Try running <a href="setup_database.php">setup_database.php</a> for detailed diagnostics';
            echo '</div>';
            $allGood = false;
        } catch (Exception $e) {
            echo '<div class="status error">';
            echo '<strong>❌ Unexpected Error</strong><br>';
            echo 'Error: <code>' . htmlspecialchars($e->getMessage()) . '</code>';
            echo '</div>';
            $allGood = false;
        }
        ?>
    </div>
</body>
</html>
