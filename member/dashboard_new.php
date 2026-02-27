<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Authentication
requireLogin();
requireRole('student', 'Student access required');

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Get student profile
$stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student applications
$stmt = $pdo->prepare("
    SELECT a.*, s.title as scholarship_title, s.deadline, s.amount
    FROM applications a
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE a.user_id = :user_id
    ORDER BY a.created_at DESC
");
$stmt->execute([':user_id' => $user_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Get unread notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = :user_id AND seen = 0
");
$stmt->execute([':user_id' => $user_id]);
$unread_count = $stmt->fetchColumn() ?: 0;

// Get recent notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([':user_id' => $user_id]);
$recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Get open scholarships for quick apply
$stmt = $pdo->query("
    SELECT * FROM scholarships
    WHERE status = 'open' AND deadline > NOW()
    ORDER BY deadline ASC
    LIMIT 5
");
$open_scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Count by status
$stats = [];
foreach (['pending', 'approved', 'rejected', 'under_review', 'submitted'] as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = :user_id AND status = :status");
    $stmt->execute([':user_id' => $user_id, ':status' => $status]);
    $stats[$status] = $stmt->fetchColumn() ?: 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Scholarship Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar-logo { font-size: 24px; font-weight: bold; }
        .navbar-menu { display: flex; gap: 20px; }
        .navbar-menu a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; transition: background 0.3s; }
        .navbar-menu a:hover { background: rgba(255,255,255,0.2); }
        .notification-badge { background: #ff6b6b; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { color: #667eea; font-size: 32px; margin: 10px 0; }
        .stat-card p { color: #999; font-size: 14px; }
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #667eea; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn-primary { background-color: #667eea; color: white; }
        .btn-primary:hover { background-color: #5568d3; }
        .btn-success { background-color: #48bb78; color: white; }
        .btn-info { background-color: #4299e1; color: white; }
        .btn-danger { background-color: #f56565; color: white; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background-color: #f5f5f5; font-weight: bold; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-pending { background-color: #ffd700; color: #333; }
        .status-approved { background-color: #90EE90; color: #000; }
        .status-rejected { background-color: #FFB6C6; color: #000; }
        .status-submitted, .status-under_review { background-color: #87CEEB; color: #000; }
        .notification-item { background: #f9f9f9; padding: 12px; margin-bottom: 10px; border-left: 4px solid #667eea; border-radius: 4px; }
        .notification-item.unread { background: #e3f2fd; border-left-color: #2196F3; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-logo">🎓 Scholarship Portal</div>
        <div class="navbar-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="apply_scholarship.php">Apply Now</a>
            <a href="notifications.php">Notifications <?php if ($unread_count > 0): ?><span class="notification-badge"><?= $unread_count ?></span><?php endif; ?></a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1>Welcome, <?= sanitizeString($_SESSION['user']['first_name'] ?? 'Student') ?>!</h1>

        <!-- Statistics -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <p>Applications</p>
                <h3><?= count($applications) ?></h3>
            </div>
            <div class="stat-card">
                <p>Pending</p>
                <h3><?= $stats['pending'] ?></h3>
            </div>
            <div class="stat-card">
                <p>Approved</p>
                <h3><?= $stats['approved'] ?></h3>
            </div>
            <div class="stat-card">
                <p>Open Scholarships</p>
                <h3><?= count($open_scholarships) ?></h3>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section">
            <h2>Quick Actions</h2>
            <a href="apply_scholarship.php" class="btn btn-primary">+ Apply for Scholarship</a>
            <a href="notifications.php" class="btn btn-info">View All Notifications</a>
        </div>

        <!-- Recent Notifications -->
        <?php if (!empty($recent_notifications)): ?>
            <div class="section">
                <h2>Recent Notifications</h2>
                <?php foreach ($recent_notifications as $notif): ?>
                    <div class="notification-item <?= !$notif['seen'] ? 'unread' : '' ?>">
                        <strong><?= sanitizeString($notif['title']) ?></strong>
                        <p><?= sanitizeString($notif['message']) ?></p>
                        <small><?= date('M d, Y H:i', strtotime($notif['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- My Applications -->
        <div class="section">
            <h2>My Applications</h2>
            <?php if (!empty($applications)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Scholarship</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Deadline</th>
                            <th>Applied</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?= sanitizeString($app['scholarship_title']) ?></td>
                                <td><span class="status-badge status-<?= $app['status'] ?>"><?= $app['status'] ?></span></td>
                                <td>₱<?= number_format($app['amount'] ?? 0, 2) ?></td>
                                <td><?= $app['deadline'] ?></td>
                                <td><?= date('M d, Y', strtotime($app['submitted_at'] ?? $app['created_at'])) ?></td>
                                <td>
                                    <a href="application_details.php?id=<?= $app['id'] ?>" class="btn btn-info" style="padding: 5px 10px; font-size: 12px;">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You haven't applied for any scholarships yet. <a href="apply_scholarship.php">Start applying now!</a></p>
            <?php endif; ?>
        </div>

        <!-- Available Scholarships -->
        <div class="section">
            <h2>Available Scholarships</h2>
            <?php if (!empty($open_scholarships)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Amount</th>
                            <th>Deadline</th>
                            <th>Days Left</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($open_scholarships as $sch): ?>
                            <tr>
                                <td><?= sanitizeString($sch['title']) ?></td>
                                <td>₱<?= number_format($sch['amount'] ?? 0, 2) ?></td>
                                <td><?= $sch['deadline'] ?></td>
                                <td>
                                    <?php 
                                        $days = (strtotime($sch['deadline']) - time()) / 86400;
                                        echo (int)$days;
                                    ?>
                                </td>
                                <td>
                                    <a href="apply_scholarship.php?id=<?= $sch['id'] ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">Apply</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No open scholarships available at this time.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
