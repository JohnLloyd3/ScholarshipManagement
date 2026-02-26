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
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; display: flex; justify-content: space-between; }
        .navbar a { color: white; margin-right: 20px; text-decoration: none; }
        .panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;
        }
        .form-group textarea { resize: vertical; min-height: 150px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #667eea; color: white; }
        .btn-danger { background-color: #f56565; color: white; }
        .btn-secondary { background-color: #718096; color: white; }
        .announcement-item { padding: 15px; border-left: 4px solid #667eea; margin-bottom: 15px; background: #f9f9f9; border-radius: 4px; }
        .announcement-item.info { border-left-color: #4299e1; }
        .announcement-item.success { border-left-color: #48bb78; }
        .announcement-item.warning { border-left-color: #ff9800; }
        .announcement-item.urgent { border-left-color: #f56565; }
        .announcement-title { font-weight: bold; color: #333; margin-bottom: 8px; }
        .announcement-meta { font-size: 12px; color: #999; margin-bottom: 8px; }
        .announcement-message { color: #666; margin-bottom: 10px; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2>📢 Announcements</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?= sanitizeString($message) ?></div>
        <?php endif; ?>

        <div class="panel">
            <h2>Create New Announcement</h2>
            <form method="POST">
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
                                    <input type="hidden" name="action" value="unpublish">
                                    <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">Unpublish</button>
                                </form>
                            <?php endif; ?>
                            <form style="display: inline;" method="POST" onsubmit="return confirm('Delete this announcement?');">
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
</body>
</html>
