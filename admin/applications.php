<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../config/email.php';

// Authentication
requireLogin();
requireAnyRole(['admin', 'staff'], 'Access Denied');

$pdo = getPDO();
$action = $_GET['action'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle POST requests (approve/reject/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = 'Invalid request (CSRF token missing or incorrect).';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $post_action = $_POST['action'] ??  '';
    $app_id = sanitizeInt($_POST['app_id'] ?? 0);
    
    if ($post_action === 'approve') {
        try {
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET status = 'approved', reviewed_at = NOW(), reviewer_id = :reviewer_id
                WHERE id = :id
            ");
            $stmt->execute([':id' => $app_id, ':reviewer_id' => $_SESSION['user_id']]);
            
            // Get application details for notification
            $appStmt = $pdo->prepare("
                SELECT a.*, u.email, u.first_name, s.title as scholarship_title
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN scholarships s ON a.scholarship_id = s.id
                WHERE a.id = :id
            ");
            $appStmt->execute([':id' => $app_id]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_application_id)
                VALUES (:user_id, 'Application Approved', :message, 'success', :app_id)
            ");
            $notifStmt->execute([
                ':user_id' => $app['user_id'],
                ':message' => 'Congratulations! Your application for ' . $app['scholarship_title'] . ' has been approved.',
                ':app_id' => $app_id
            ]);
            
            // Send email
            $emailSubject = 'Application Approved - ' . $app['scholarship_title'];
            $emailBody = "
                <h2>Application Approved</h2>
                <p>Dear " . htmlspecialchars($app['first_name']) . ",</p>
                <p>Congratulations! Your application for <strong>" . htmlspecialchars($app['scholarship_title']) . "</strong> has been approved.</p>
                <p>Please log in to your account for more details.</p>
            ";
            sendEmail($app['email'], $emailSubject, $emailBody, true);
            
            $_SESSION['message'] = 'Application approved!';
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }
    } elseif ($post_action === 'reject') {
        try {
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET status = 'rejected', reviewed_at = NOW(), reviewer_id = :reviewer_id
                WHERE id = :id
            ");
            $stmt->execute([':id' => $app_id, ':reviewer_id' => $_SESSION['user_id']]);
            
            // Get application details
            $appStmt = $pdo->prepare("
                SELECT a.*, u.email, u.first_name, s.title as scholarship_title
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN scholarships s ON a.scholarship_id = s.id
                WHERE a.id = :id
            ");
            $appStmt->execute([':id' => $app_id]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_application_id)
                VALUES (:user_id, 'Application Rejected', :message, 'error', :app_id)
            ");
            $notifStmt->execute([
                ':user_id' => $app['user_id'],
                ':message' => 'Unfortunately, your application for ' . $app['scholarship_title'] . ' was not approved this time.',
                ':app_id' => $app_id
            ]);
            
            // Send email
            $emailSubject = 'Application Update - ' . $app['scholarship_title'];
            sendEmail($app['email'], $emailSubject, 
                "<h2>Application Status</h2><p>Dear " . htmlspecialchars($app['first_name']) . ",</p><p>Unfortunately, your application was not selected this round. Keep trying!</p>", true);
            
            $_SESSION['message'] = 'Application rejected!';
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch applications based on user role
if (hasRole('admin')) {
    $query = "
        SELECT a.*, u.first_name, u.last_name, u.email, s.title as scholarship_title
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN scholarships s ON a.scholarship_id = s.id
        WHERE a.status != 'draft'
        ORDER BY a.status ASC, a.submitted_at DESC
    ";
} else {
    $query = "
        SELECT a.*, u.first_name, u.last_name, u.email, s.title as scholarship_title
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN scholarships s ON a.scholarship_id = s.id
        WHERE a.status != 'draft'
        ORDER BY a.status ASC, a.submitted_at DESC
    ";
}

$applications = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch single application for viewing
$viewing = null;
if ($action === 'view') {
    $id = sanitizeInt($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT a.*, u.first_name, u.last_name, u.email, s.title as scholarship_title, s.description as scholarship_desc
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN scholarships s ON a.scholarship_id = s.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $viewing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get documents
        if ($viewing) {
            $docStmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = :id");
            $docStmt->execute([':id' => $id]);
            $viewing['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../member/dashboard.css">
    <style>
        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif; }
        body { background: #f8f9fa; color: #1a1a1a; }
        h2, h3 { color: #1a1a1a; font-weight: 600; letter-spacing: -0.5px; }
        h2 { font-size: 28px; }
        h3 { font-size: 18px; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .panel { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .btn { padding: 10px 20px; border:none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; font-weight: 500; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(196,30,58,0.2); }
        .btn-primary { background-color: #c41e3a; color: white; }
        .btn-primary:hover { background-color: #9d1729; }
        .btn-success { background-color: #2d5016; color: white; }
        .btn-danger { background-color: #dc2626; color: white; }
        .btn-info { background-color: #1e40af; color: white; }
        .btn-secondary { background-color: #4b5563; color: white; }
        
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th, .table td { padding: 14px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .table th { background-color: #f8f9fa; font-weight: 600; color: #1a1a1a; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        .table td { color: #34495e; }
        .table tbody tr:hover { background: #f8f9fa; }
        
        .status-badge { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #dcfce7; color: #16a34a; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }
        .status-submitted, .status-under_review { background-color: #dbeafe; color: #1e40af; }
        
        .message { padding: 16px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .application-detail { background: #f8f9fa; padding: 24px; border-radius: 12px; border-left: 4px solid #c41e3a; }
        .detail-row { margin-bottom: 16px; }
        .detail-row label { font-weight: 600; color: #1a1a1a; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-row { color: #34495e; }
        
        .nav { background: linear-gradient(135deg, #c41e3a 0%, #8b1a1a 100%); color: white; padding: 15px; display: flex; justify-content: space-between; }
        .nav a { color: white; margin-right: 20px; text-decoration: none; }
        .dashboard-app { display: flex; min-height: 100vh; }
        .main { flex: 1; padding: 20px; }
        @media (max-width: 900px) { .container { padding: 12px; } .table th, .table td { padding: 8px; } }
        .table-responsive { overflow-x: auto; }
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
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main">
            <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?= sanitizeString($message) ?></div>
        <?php endif; ?>

        <?php if ($action === 'view' && $viewing): ?>
            <a href="applications.php" class="btn btn-secondary">← Back to Applications</a>
            
            <div class="panel application-detail">
                <h2><?= sanitizeString($viewing['scholarship_title']) ?></h2>
                <hr>
                
                <div class="detail-row">
                    <label>Applicant:</label>
                    <div><?= sanitizeString($viewing['first_name'] . ' ' . $viewing['last_name']) ?></div>
                </div>
                
                <div class="detail-row">
                    <label>Email:</label>
                    <div><?= sanitizeString($viewing['email']) ?></div>
                </div>
                
                <div class="detail-row">
                    <label>Application Title:</label>
                    <div><?= sanitizeString($viewing['title'] ?? 'N/A') ?></div>
                </div>
                
                <div class="detail-row">
                    <label>GPA:</label>
                    <div><?= $viewing['gpa'] ?? 'N/A' ?></div>
                </div>
                
                <div class="detail-row">
                    <label>Status:</label>
                    <div><span class="status-badge status-<?= $viewing['status'] ?>"><?= $viewing['status'] ?></span></div>
                </div>
                
                <div class="detail-row">
                    <label>Application Details:</label>
                    <div style="background: white; padding: 10px; border-radius: 4px;"><?= nl2br(sanitizeString($viewing['details'] ?? '')) ?></div>
                </div>
                
                <?php if (!empty($viewing['documents'])): ?>
                    <div class="detail-row">
                        <label>Uploaded Documents:</label>
                        <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th>File Name</th>
                                    <th>Uploaded</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($viewing['documents'] as $doc): ?>
                                    <tr>
                                        <td><?= sanitizeString($doc['document_type'] ?? 'Unknown') ?></td>
                                        <td><?= sanitizeString($doc['file_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                                        <td>
                                            <a href="../<?= $doc['file_path'] ?>" target="_blank" class="btn btn-info" style="padding: 5px 10px;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($viewing['status'] === 'under_review' || $viewing['status'] === 'submitted'): ?>
                    <hr>
                    <h3>Decision</h3>
                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="app_id" value="<?= $viewing['id'] ?>">
                        <button type="submit" class="btn btn-success">✓ Approve</button>
                    </form>
                    
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="app_id" value="<?= $viewing['id'] ?>">
                        <button type="submit" class="btn btn-danger">✗ Reject</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h1>Applications Management</h1>
            
            <div class="panel">
                <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Scholarship</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($applications)): ?>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?= sanitizeString($app['first_name'] . ' ' . $app['last_name']) ?></td>
                                    <td><?= sanitizeString($app['scholarship_title']) ?></td>
                                    <td><span class="status-badge status-<?= $app['status'] ?>"><?= $app['status'] ?></span></td>
                                    <td><?= date('M d, Y', strtotime($app['submitted_at'] ?? $app['created_at'])) ?></td>
                                    <td>
                                        <a href="?action=view&id=<?= $app['id'] ?>" class="btn btn-primary">View & Review</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px;">No applications to review</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
