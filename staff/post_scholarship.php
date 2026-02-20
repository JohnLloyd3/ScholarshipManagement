<?php
// Scholarship Posting Form
require_once __DIR__ . '/../auth/helpers.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $status = $_POST['status'] ?? 'open';
    $requirements = $_POST['requirements'] ?? [];
    $deadline = trim($_POST['deadline'] ?? '');
    $amount = trim($_POST['amount'] ?? '');

    $errors = [];
    if (!$title || !$organization) {
        $errors[] = 'Title and organization are required.';
    }
    if (!$deadline) {
        $errors[] = 'Application deadline is required.';
    }
    if (!$amount || !is_numeric($amount)) {
        $errors[] = 'Scholarship amount is required and must be a number.';
    }

    // Prevent duplicate scholarship entries
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM scholarships WHERE title = :title AND organization = :organization AND deadline = :deadline AND amount = :amount');
    $stmt->execute(['title' => $title, 'organization' => $organization, 'deadline' => $deadline, 'amount' => $amount]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Scholarship with this title, organization, deadline, and amount already exists.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO scholarships (title, description, organization, status, deadline, amount) VALUES (:title, :description, :organization, :status, :deadline, :amount)');
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'organization' => $organization,
            'status' => $status,
            'deadline' => $deadline,
            'amount' => $amount
        ]);
        $scholarship_id = $pdo->lastInsertId();
        $unique_reqs = [];
        foreach ($requirements as $req) {
            $req = trim($req);
            if ($req && !in_array($req, $unique_reqs)) {
                $unique_reqs[] = $req;
                $stmt = $pdo->prepare('INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:scholarship_id, :requirement)');
                $stmt->execute([
                    'scholarship_id' => $scholarship_id,
                    'requirement' => $req
                ]);
            }
        }
        header('Location: ../staff/scholarships.php?posted=1');
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Post Scholarship</title>
        <link rel="stylesheet" href="../assets/style.css">
        <style>
            body { background: #f7f7fa; }
            .main { max-width: 650px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 10px; box-shadow: 0 2px 16px #e0e0e0; }
            h2 { text-align: center; margin-bottom: 30px; font-size: 2.2rem; }
            .form-group { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
            .form-group label { flex: 0 0 180px; margin-bottom: 0; font-weight: 500; color: #222; font-size: 1rem; }
            .form-group input, .form-group select, .form-group textarea {
                flex: 1 1 320px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem;
                background: #fff; margin-left: 10px; min-width: 0;
            }
            .form-group textarea { resize: vertical; min-height: 38px; }
            .btn { display: block; width: 100%; background: #4CAF50; color: #fff; border: none; padding: 13px 0; border-radius: 6px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 18px; transition: background 0.2s; }
            .btn:hover { background: #388e3c; }
            .flash.error-flash { background: #ffeaea; color: #b71c1c; border: 1px solid #ffcdd2; padding: 10px 18px; border-radius: 6px; margin-bottom: 18px; }
            #requirements-container { margin-top: 8px; }
            .requirement-item { display: flex; align-items: center; margin-bottom: 8px; }
            .requirement-item input { flex: 1 1 220px; margin-left: 0; margin-right: 10px; }
            .requirement-item button { background: #e53935; color: #fff; border: none; border-radius: 4px; padding: 6px 12px; font-size: 0.95rem; cursor: pointer; transition: background 0.2s; }
            .requirement-item button:hover { background: #b71c1c; }
            .form-group > button[type="button"] { background: #1976d2; color: #fff; border: none; border-radius: 4px; padding: 7px 16px; font-size: 0.98rem; margin-top: 6px; cursor: pointer; transition: background 0.2s; }
            .form-group > button[type="button"]:hover { background: #0d47a1; }
            @media (max-width: 700px) {
                .main { padding: 10px; }
                .form-group { flex-direction: column; align-items: stretch; }
                .form-group label { margin-bottom: 6px; }
                .form-group input, .form-group select, .form-group textarea { margin-left: 0; }
                .requirement-item { flex-direction: column; align-items: stretch; }
                .requirement-item input { margin-right: 0; margin-bottom: 6px; }
            }
        </style>
</head>
<body>
    <div class="main">
        <h2>Post Scholarship</h2>
        <?php if (!empty($errors)): ?>
            <div class="flash error-flash">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Organization *</label>
                <input type="text" name="organization" required value="<?= htmlspecialchars($_POST['organization'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Application Deadline *</label>
                <input type="date" name="deadline" required value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Scholarship Amount *</label>
                <input type="number" name="amount" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="open" <?= ($_POST['status'] ?? 'open') == 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="closed" <?= ($_POST['status'] ?? '') == 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="form-group">
                <label>Eligibility Requirements</label>
                <div id="requirements-container">
                    <?php if (!empty($_POST['requirements'])): ?>
                        <?php foreach ($_POST['requirements'] as $req): ?>
                            <div class="requirement-item">
                                <input type="text" name="requirements[]" value="<?= htmlspecialchars($req) ?>" placeholder="e.g., GPA >= 3.5">
                                <button type="button" onclick="this.parentElement.remove()">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="requirement-item">
                            <input type="text" name="requirements[]" placeholder="e.g., GPA >= 3.5">
                            <button type="button" onclick="this.parentElement.remove()">Remove</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addRequirement()">Add Requirement</button>
            </div>
            <button type="submit" class="btn">Post Scholarship</button>
        </form>
    </div>
    <script>
        function addRequirement() {
            const container = document.getElementById('requirements-container');
            const div = document.createElement('div');
            div.className = 'requirement-item';
            div.innerHTML = '<input type="text" name="requirements[]" placeholder="e.g., GPA >= 3.5">' +
                '<button type="button" onclick="this.parentElement.remove()">Remove</button>';
            container.appendChild(div);
        }
    </script>
</body>
</html>
