<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Check if logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Mark notifications as read
if ($_POST['action'] ?? '' === 'mark_read') {
    $notif_id = sanitizeInt($_POST['notif_id'] ?? 0);
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET seen = 1, seen_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([':id' => $notif_id, ':user_id' => $user_id]);
}

// Mark all as read
if ($_POST['action'] ?? '' === 'mark_all_read') {
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET seen = 1, seen_at = NOW()
        WHERE user_id = :user_id AND seen = 0
    ");
    $stmt->execute([':user_id' => $user_id]);
}

// Get all notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute([':user_id' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Get announcements
$stmt = $pdo->query("
    SELECT * FROM announcements
    WHERE published = 1 AND (expires_at IS NULL OR expires_at > NOW())
    ORDER BY published_at DESC
");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Count unread
$unread_count = count(array_filter($notifications, fn($n) => !$n['seen']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications & Announcements</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; justify-content: space-between; }
        .navbar a { color: white; text-decoration: none; margin-right: 20px; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .notification-item { padding: 15px; border-bottom: 1px solid #eee; border-left: 4px solid #667eea; margin-bottom: 10px; background: #f9f9f9; border-radius: 4px; }
        .notification-item.unread { background: #e3f2fd; border-left-color: #2196F3; font-weight: 500; }
        .notification-item.success { border-left-color: #4caf50; }
        .notification-item.error { border-left-color: #f44336; }
        .notification-item.warning { border-left-color: #ff9800; }
        .notification-title { font-weight: bold; color: #333; margin-bottom: 5px; }
        .notification-message { color: #666; margin-bottom: 8px; }
        .notification-time { font-size: 12px; color: #999; }
        .announcement { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; }
        .announcement h3 { margin-top: 0; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #667eea; color: white; }
        .btn-secondary { background-color: #718096; color: white; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .tab { padding: 10px 20px; cursor: pointer; border: none; background: none; font-size: 16px; color: #666; }
        .tab.active { color: #667eea; border-bottom: 3px solid #667eea; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2>🔔 Notifications & Announcements</h2>
        <div>
            <a href="../member/dashboard_new.php">Dashboard</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1>Updates & Announcements</h1>
            <?php if ($unread_count > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-primary">Mark All as Read</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Announcements Section -->
        <?php if (!empty($announcements)): ?>
            <div class="panel">
                <h2>📢 Announcements</h2>
                <?php foreach ($announcements as $annc): ?>
                    <div class="announcement">
                        <h3><?= sanitizeString($annc['title']) ?></h3>
                        <p><?= nl2br(sanitizeString($annc['message'])) ?></p>
                        <small><?= date('M d, Y H:i', strtotime($annc['published_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Notifications Section -->
        <div class="panel">
            <h2>🔔 Notifications (<?= count($notifications) ?>)</h2>
            
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= !$notif['seen'] ? 'unread' : '' ?> <?= $notif['type'] ?>">
                        <div class="notification-title">
                            <?= sanitizeString($notif['title']) ?>
                            <?php if (!$notif['seen']): ?>
                                <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; float: right;">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-message">
                            <?= sanitizeString($notif['message']) ?>
                        </div>
                        <div class="notification-time">
                            <?= date('M d, Y H:i', strtotime($notif['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No notifications yet. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
