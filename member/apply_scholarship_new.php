<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../config/email.php';

// Authentication
requireLogin();
requireRole('student', 'Student access required');

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$message = '';
$scholarship_id = sanitizeInt($_GET['id'] ?? 0);

// Get student profile
$stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get scholarship details if ID provided
$scholarship = null;
if ($scholarship_id) {
    $stmt = $pdo->prepare("SELECT * FROM scholarships WHERE id = :id AND status = 'open'");
    $stmt->execute([':id' => $scholarship_id]);
    $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($scholarship) {
        $stmt = $pdo->prepare("
            SELECT * FROM eligibility_requirements 
            WHERE scholarship_id = :id
        ");
        $stmt->execute([':id' => $scholarship_id]);
        $scholarship['requirements'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scholarship_id = sanitizeInt($_POST['scholarship_id'] ?? 0);
    $title = sanitizeString($_POST['title'] ?? '');
    $gpa = sanitizeFloat($_POST['gpa'] ?? 0);
    $details = sanitizeString($_POST['details'] ?? '');
    $motivational_letter = sanitizeString($_POST['motivational_letter'] ?? '');
    
    if (!$scholarship_id || !$title || !$details) {
        $message = 'Please fill in all required fields.';
    } else {
        try {
            // Check for duplicate application
            $dupStmt = $pdo->prepare("
                SELECT COUNT(*) FROM applications
                WHERE user_id = :user_id AND scholarship_id = :sch_id
            ");
            $dupStmt->execute([':user_id' => $user_id, ':sch_id' => $scholarship_id]);
            if ($dupStmt->fetchColumn() > 0) {
                $message = 'You have already applied for this scholarship.';
            } else {
                // Get scholarship details
                $schStmt = $pdo->prepare("SELECT * FROM scholarships WHERE id = :id");
                $schStmt->execute([':id' => $scholarship_id]);
                $sch = $schStmt->fetch(PDO::FETCH_ASSOC);
                
                // Insert application
                $stmt = $pdo->prepare("
                    INSERT INTO applications (user_id, scholarship_id, title, details, gpa, motivational_letter, status, submitted_at)
                    VALUES (:user_id, :sch_id, :title, :details, :gpa, :motivational_letter, 'submitted', NOW())
                ");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':sch_id' => $scholarship_id,
                    ':title' => $title,
                    ':details' => $details,
                    ':gpa' => $gpa,
                    ':motivational_letter' => $motivational_letter
                ]);
                
                $app_id = $pdo->lastInsertId();
                
                // Handle file uploads
                if (!empty($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                    $upload_dir = '../uploads/applications/' . $app_id . '/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpfile = $_FILES['documents']['tmp_name'][$i];
                            $filename = generateSafeFileName($_FILES['documents']['name'][$i]);
                            $filepath = $upload_dir . $filename;
                            
                            if (move_uploaded_file($tmpfile, $filepath)) {
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $mimetype = finfo_file($finfo, $filepath);
                                finfo_close($finfo);
                                
                                $docStmt = $pdo->prepare("
                                    INSERT INTO documents (application_id, user_id, document_type, file_name, file_path, file_size, mime_type, verification_status)
                                    VALUES (:app_id, :user_id, :type, :name, :path, :size, :mime, 'pending')
                                ");
                                $docStmt->execute([
                                    ':app_id' => $app_id,
                                    ':user_id' => $user_id,
                                    ':type' => sanitizeString($_POST['document_type'][$i] ?? 'supporting'),
                                    ':name' => $filename,
                                    ':path' => 'uploads/applications/' . $app_id . '/' . $filename,
                                    ':size' => filesize($filepath),
                                    ':mime' => $mimetype
                                ]);
                            }
                        }
                    }
                }
                
                // Create notification
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, related_application_id, related_scholarship_id)
                    VALUES (:user_id, 'Application Submitted', :message, 'info', :app_id, :sch_id)
                ");
                $notifStmt->execute([
                    ':user_id' => $user_id,
                    ':message' => 'Your application for ' . htmlspecialchars($sch['title']) . ' has been submitted.',
                    ':app_id' => $app_id,
                    ':sch_id' => $scholarship_id
                ]);
                
                // Send email confirmation
                $userStmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = :id");
                $userStmt->execute([':id' => $user_id]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                $emailBody = "
                    <h2>Application Submitted</h2>
                    <p>Dear " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>Your application for <strong>" . htmlspecialchars($sch['title']) . "</strong> has been successfully submitted.</p>
                    <p>You can track your application status in your dashboard.</p>
                    <p>Good luck!</p>
                ";
                
                sendEmail($user['email'], 'Application Submitted - ' . $sch['title'], $emailBody, true);
                
                $_SESSION['message'] = 'Application submitted successfully!';
                header('Location: dashboard_new.php');
                exit;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Get list of open scholarships
$stmt = $pdo->query("
    SELECT id, title, amount, deadline, status
    FROM scholarships
    WHERE status = 'open' AND deadline > NOW()
    ORDER BY deadline ASC
");
$open_scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Scholarship</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; justify-content: space-between; }
        .navbar a { color: white; text-decoration: none; margin-right: 20px; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .panel { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; font-size: 14px;
        }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #667eea; color: white; }
        .btn-primary:hover { background-color: #5568d3; }
        .btn-secondary { background-color: #718096; color: white; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .requirements { background: #f9f9f9; padding: 15px; border-left: 4px solid #667eea; border-radius: 4px; margin-bottom: 20px; }
        .requirements h4 { margin-top: 0; }
        .requirements ul { margin-left: 20px; }
        .requirements li { margin-bottom: 8px; }
        .file-upload { margin-bottom: 10px; }
        .file-upload input { margin-bottom: 5px; }
        .add-file-btn { background-color: #4299e1; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2>🎓 Apply for Scholarship</h2>
        <div>
            <a href="dashboard_new.php">Dashboard</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
                <?= sanitizeString($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($scholarship): ?>
            <div class="panel">
                <h1><?= sanitizeString($scholarship['title']) ?></h1>
                <p><?= nl2br(sanitizeString($scholarship['description'])) ?></p>
                
                <?php if (!empty($scholarship['requirements'])): ?>
                    <div class="requirements">
                        <h4>Requirements</h4>
                        <ul>
                            <?php foreach ($scholarship['requirements'] as $req): ?>
                                <li><?= sanitizeString($req['requirement']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="scholarship_id" value="<?= $scholarship['id'] ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Application Title *</label>
                            <input type="text" name="title" required placeholder="e.g., Application for Academic Excellence">
                        </div>
                        <div class="form-group">
                            <label>Your GPA *</label>
                            <input type="number" name="gpa" step="0.01" min="0" max="4" required placeholder="e.g., 3.5">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Application Details *</label>
                        <textarea name="details" required placeholder="Tell us about yourself, your achievements, and why you deserve this scholarship..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Motivational Letter</label>
                        <textarea name="motivational_letter" placeholder="Optional: Share your story and aspirations..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Upload Supporting Documents</label>
                        <div id="fileUploads">
                            <div class="file-upload">
                                <input type="hidden" name="document_type[]" value="supporting">
                                <input type="file" name="documents[]" accept=".pdf,.doc,.docx,.jpg,.png">
                            </div>
                        </div>
                        <button type="button" class="add-file-btn" onclick="addFileInput()">+ Add Another Document</button>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                        <a href="dashboard_new.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <script>
                function addFileInput() {
                    const container = document.getElementById('fileUploads');
                    const div = document.createElement('div');
                    div.className = 'file-upload';
                    div.innerHTML = `
                        <input type="hidden" name="document_type[]" value="supporting">
                        <input type="file" name="documents[]" accept=".pdf,.doc,.docx,.jpg,.png">
                    `;
                    container.appendChild(div);
                }
            </script>
        <?php else: ?>
            <div class="panel">
                <h1>Select a Scholarship</h1>
                <p>Choose a scholarship to apply for:</p>

                <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                    <thead>
                        <tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">
                            <th style="padding: 12px; text-align: left;">Title</th>
                            <th style="padding: 12px; text-align: left;">Amount</th>
                            <th style="padding: 12px; text-align: left;">Deadline</th>
                            <th style="padding: 12px; text-align: left;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($open_scholarships)): ?>
                            <?php foreach ($open_scholarships as $sch): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><?= sanitizeString($sch['title']) ?></td>
                                    <td style="padding: 12px;">₱<?= number_format($sch['amount'] ?? 0, 2) ?></td>
                                    <td style="padding: 12px;"><?= $sch['deadline'] ?></td>
                                    <td style="padding: 12px;">
                                        <a href="?id=<?= $sch['id'] ?>" class="btn btn-primary" style="padding: 8px 16px; font-size: 14px;">Apply</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="padding: 20px; text-align: center;">No open scholarships available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px;">
                    <a href="dashboard_new.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
