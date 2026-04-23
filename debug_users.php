<?php
/**
 * DEBUG SCRIPT - Check Users in Database
 * Access this file directly to see all users
 */
require_once __DIR__ . '/config/db.php';

$pdo = getPDO();

echo "<h1>Users in Database</h1>";
echo "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #4CAF50; color: white; }</style>";

try {
    $stmt = $pdo->query("SELECT id, username, first_name, last_name, email, role, active, email_verified, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color: red; font-weight: bold;'>NO USERS FOUND IN DATABASE!</p>";
        echo "<p>The users table exists but is empty. You need to create users.</p>";
    } else {
        echo "<p>Found " . count($users) . " user(s) in the database:</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Role</th><th>Active</th><th>Email Verified</th><th>Created</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . ($user['active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($user['email_verified'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Check session
    session_start();
    echo "<h2>Current Session Info</h2>";
    if (isset($_SESSION['user_id'])) {
        echo "<p><strong>Session User ID:</strong> " . htmlspecialchars($_SESSION['user_id']) . "</p>";
        
        // Check if this user exists
        $stmt = $pdo->prepare("SELECT id, username, first_name, last_name FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $sessionUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sessionUser) {
            echo "<p style='color: green;'>✓ User exists in database: " . htmlspecialchars($sessionUser['first_name'] . ' ' . $sessionUser['last_name']) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ User ID " . htmlspecialchars($_SESSION['user_id']) . " NOT FOUND in database!</p>";
            echo "<p>This is why you're getting errors. Your session has an invalid user_id.</p>";
        }
    } else {
        echo "<p>No active session (not logged in)</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='auth/login.php'>Go to Login</a> | <a href='auth/logout.php'>Logout</a></p>";
?>
