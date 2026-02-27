<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = 'Invalid request (CSRF token missing or incorrect).';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = sanitizeString($_POST['title'] ?? '');
        $message_content = sanitizeString($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $expires_at = $_POST['expires_at'] ?? null;
        
        if ($title && $message_content) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (title, message, type, created_by, published, published_at, expires_at)
                    VALUES (:title, :message, :type, :created_by, 1, NOW(), :expires_at)
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':message' => $message_content,
                    ':type' => $type,
                    ':created_by' => $_SESSION['user_id'],
                    ':expires_at' => !empty($expires_at) ? $expires_at : null
                ]);
                $_SESSION['message'] = 'Announcement created successfully!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'unpublish') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        if ($id) {
            try {
                $pdo->prepare("UPDATE announcements SET published = 0 WHERE id = :id")
                    ->execute([':id' => $id]);
                $_SESSION['message'] = 'Announcement unpublished!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        if ($id) {
            try {
                $pdo->prepare("DELETE FROM announcements WHERE id = :id")
                    ->execute([':id' => $id]);
                $_SESSION['message'] = 'Announcement deleted!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch announcements
$announcements = $pdo->query("
    SELECT a.*, u.first_name, u.last_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.published_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../member/dashboard.css">
    <style>
        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif; }
        body { background-color: #f8f9fa; color: #1a1a1a; }
        h2, h3 { color: #1a1a1a; font-weight: 600; letter-spacing: -0.5px; }
        h2 { font-size: 28px; }
        h3 { font-size: 18px; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .navbar { background: linear-gradient(135deg, #c41e3a 0%, #8b1a1a 100%); color: white; padding: 15px; display: flex; justify-content: space-between; }
        .navbar a { color: white; margin-right: 20px; text-decoration: none; }
        
        .panel { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #1a1a1a; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-family: inherit; font-size: 14px; transition: all 0.2s ease;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: #c41e3a; box-shadow: 0 0 0 3px rgba(196,30,58,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 150px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; font-weight: 500; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(196,30,58,0.2); }
        .btn-primary { background-color: #c41e3a; color: white; }
        .btn-primary:hover { background-color: #9d1729; }
        .btn-danger { background-color: #dc2626; color: white; }
        .btn-secondary { background-color: #4b5563; color: white; }
        
        .announcement-item { padding: 18px; border-left: 4px solid #c41e3a; margin-bottom: 15px; background: #f8f9fa; border-radius: 8px; }
        .announcement-item.info { border-left-color: #1e40af; background: #eff6ff; }
        .announcement-item.success { border-left-color: #16a34a; background: #f0fdf4; }
        .announcement-item.warning { border-left-color: #ea580c; background: #fef3c7; }
        .announcement-item.urgent { border-left-color: #dc2626; background: #fef2f2; }
        
        .announcement-title { font-weight: 600; color: #1a1a1a; margin-bottom: 8px; font-size: 16px; }
        .announcement-meta { font-size: 12px; color: #7f8c8d; margin-bottom: 8px; }
        .announcement-message { color: #34495e; margin-bottom: 10px; font-size: 14px; }
        
        .message { padding: 16px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .dashboard-app { display: flex; min-height: 100vh; }
        .main { flex: 1; padding: 20px; }
        @media (max-width: 900px) { .container { padding: 12px; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar">A</div>
        <div>
          <div class="welcome">Admin</div>
          <div class="username"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="applications.php">Applications</a>
        <a href="scholarships.php">Scholarships</a>
        <a href="users.php">Users</a>
        <a href="analytics.php">Analytics</a>
        <a href="announcements.php">Announcements</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?= sanitizeString($message) ?></div>
        <?php endif; ?>

        <div class="panel">
            <h2>Create New Announcement</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Scholarship Deadline Extended">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" required placeholder="Enter the announcement message..."></textarea>
                </div>

                <div class="form-group">
                    <label>Expires At (Optional)</label>
                    <input type="datetime-local" name="expires_at" placeholder="Leave blank for no expiration">
                </div>

                <button type="submit" class="btn btn-primary">Post Announcement</button>
            </form>
        </div>

        <div class="panel">
            <h2>All Announcements</h2>
            
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $ann): ?>
                    <div class="announcement-item <?= $ann['type'] ?>">
                        <div class="announcement-title">
                            <?= sanitizeString($ann['title']) ?>
                            <?php if (!$ann['published']): ?>
                                <span style="background: #999; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; float: right;">UNPUBLISHED</span>
                            <?php endif; ?>
                        </div>
                        <div class="announcement-meta">
                            Posted by <?= sanitizeString($ann['first_name'] . ' ' . $ann['last_name']) ?> on <?= date('M d, Y H:i', strtotime($ann['published_at'])) ?>
                            <?php if ($ann['expires_at']): ?>
                                | Expires: <?= date('M d, Y', strtotime($ann['expires_at'])) ?>
                            <?php endif; ?>
                        </div>
                        <div class="announcement-message">
                            <?= nl2br(sanitizeString($ann['message'])) ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <?php if ($ann['published']): ?>
                                <form style="display: inline;" method="POST" onsubmit="return confirm('Unpublish this announcement?');">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="unpublish">
                                    <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">Unpublish</button>
                                </form>
                            <?php endif; ?>
                            <form style="display: inline;" method="POST" onsubmit="return confirm('Delete this announcement?');">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No announcements yet.</p>
            <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
