<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/SecurityHelper.php';

$pdo = getPDO();

// Get open scholarships
$stmt = $pdo->query("
    SELECT * FROM scholarships
    WHERE status = 'open' AND deadline > NOW()
    ORDER BY deadline ASC
    LIMIT 10
");
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Get announcements
$stmt = $pdo->query("
    SELECT * FROM announcements
    WHERE published = 1 AND (expires_at IS NULL OR expires_at > NOW())
    ORDER BY published_at DESC
    LIMIT 3
");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Count applications
$totalApps = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn() ?: 0;
$totalScholarships = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scholarship Management System</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #fff; }
    .navbar { background: linear-gradient(135deg, #c41e3a 0%, #8b1a1a 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .navbar-logo { font-size: 28px; font-weight: bold; }
    .navbar-nav { display: flex; gap: 30px; }
    .navbar-nav a { color: white; text-decoration: none; font-size: 16px; }
    .navbar-nav a:hover { opacity: 0.8; }
    .navbar-buttons { display: flex; gap: 10px; }
    .navbar-buttons button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .login { background: transparent; color: white; border: 2px solid white; }
    .register { background: white; color: #c41e3a; font-weight: bold; }
    .login:hover { background: rgba(255,255,255,0.1); }
    .register:hover { opacity: 0.9; }
    .hero { background: linear-gradient(135deg, #c41e3a 0%, #8b1a1a 100%); color: white; padding: 80px 40px; text-align: center; }
    .hero h1 { font-size: 48px; margin-bottom: 20px; }
    .hero p { font-size: 20px; margin-bottom: 30px; }
    .primary-btn { background: #c41e3a; color: white; padding: 15px 40px; border: none; border-radius: 4px; font-size: 18px; cursor: pointer; font-weight: bold; transition: all 0.3s; }
    .primary-btn:hover { background: #8b1a1a; opacity: 0.9; transform: translateY(-2px); }
    .container { max-width: 1200px; margin: 0 auto; padding: 0 40px; }
    .section { padding: 60px 0; }
    .section h2 { font-size: 36px; margin-bottom: 40px; text-align: center; color: #c41e3a; }
    .features { background: #f5f5f5; }
    .feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
    .feature-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; font-size: 18px; font-weight: bold; border-top: 4px solid #c41e3a; }
    .feature-card:hover { transform: translateY(-5px); box-shadow: 0 4px 16px rgba(196,30,58,0.3); }
    .process { background: white; }
    .process ol { max-width: 600px; margin: 0 auto; font-size: 18px; }
    .process li { margin-bottom: 20px; padding: 20px; background: #f9f9f9; border-left: 4px solid #c41e3a; border-radius: 4px; }
    .scholarships { background: white; }
    .scholarship-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; border-left: 5px solid #c41e3a; }
    .scholarship-card h4 { color: #c41e3a; margin-bottom: 10px; }
    .scholarship-card .amount { font-size: 24px; font-weight: bold; color: #c41e3a; }
    .scholarship-card .deadline { color: #999; font-size: 14px; }
    .announcements { background: #f5f5f5; }
    .announcement { background: linear-gradient(135deg, #c41e3a 0%, #f5576c 100%); color: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; }
    .announcement h4 { margin-bottom: 10px; }
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-bottom: 40px; }
    .stat-card { background: linear-gradient(135deg, #c41e3a 0%, #8b1a1a 100%); color: white; padding: 30px; border-radius: 8px; text-align: center; }
    .stat-card .number { font-size: 48px; font-weight: bold; }
    .stat-card .label { font-size: 16px; opacity: 0.9; }
    .contact { background: #c41e3a; color: white; text-align: center; }
    .contact p { font-size: 18px; margin-bottom: 20px; }
    footer { background: #8b1a1a; color: white; text-align: center; padding: 20px; }
    @media (max-width: 768px) {
      .feature-grid { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: 1fr; }
      .navbar { flex-direction: column; gap: 20px; }
      .navbar-nav { flex-direction: column; gap: 10px; }
    }
  </style>
</head>
<body>

<nav class="navbar">
  <div class="navbar-logo">🎓 Scholarship Management System</div>
  <div class="navbar-nav">
    <a href="#home">Home</a>
    <a href="#scholarships">Scholarships</a>
    <a href="#features">Features</a>
  </div>
  <div class="navbar-buttons">
    <?php if (isLoggedIn()): ?>
      <button class="login" onclick="window.location.href='member/dashboard_new.php'" style="background: white; color: #667eea;">Dashboard</button>
      <button class="register" onclick="window.location.href='auth/logout.php'" style="background: #f56565; color: white;">Logout</button>
    <?php else: ?>
      <button class="login" onclick="window.location.href='auth/login.php'">Login</button>
      <button class="register" onclick="window.location.href='auth/register.php'">Register</button>
    <?php endif; ?>
  </div>
</nav>

<section id="home" class="hero">
  <h1>Scholarship Opportunities</h1>
  <p>Apply for life-changing scholarships and achieve your dreams</p>
  <?php if (!isLoggedIn()): ?>
    <button class="primary-btn" onclick="window.location.href='auth/register.php'">Apply Now</button>
  <?php else: ?>
    <button class="primary-btn" onclick="window.location.href='member/apply_scholarship_new.php'">Explore & Apply</button>
  <?php endif; ?>
</section>

<div class="container">
  <!-- Stats Section -->
  <div class="section">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="number"><?= $totalScholarships ?></div>
        <div class="label">Active Scholarships</div>
      </div>
      <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
        <div class="number"><?= $totalApps ?></div>
        <div class="label">Applications Submitted</div>
      </div>
      <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
        <div class="number">100+</div>
        <div class="label">Successful Awardees</div>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <?php if (!empty($announcements)): ?>
    <section id="announcements" class="announcements section">
      <h2>Latest Announcements</h2>
      <?php foreach ($announcements as $ann): ?>
        <div class="announcement">
          <h4><?= sanitizeString($ann['title']) ?></h4>
          <p><?= sanitizeString(substr($ann['message'], 0, 150)) ?>...</p>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <!-- Available Scholarships -->
  <section id="scholarships" class="scholarships section">
    <h2>Available Scholarships</h2>
    <?php if (!empty($scholarships)): ?>
      <?php foreach ($scholarships as $sch): ?>
        <div class="scholarship-card">
          <h4><?= sanitizeString($sch['title']) ?></h4>
          <p><?= sanitizeString(substr($sch['description'] ?? '', 0, 150)) ?>...</p>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
            <div>
              <div class="amount">₱<?= number_format($sch['amount'] ?? 0, 2) ?></div>
              <div class="deadline">Deadline: <?= date('M d, Y', strtotime($sch['deadline'])) ?></div>
            </div>
            <?php if (isLoggedIn()): ?>
              <button class="primary-btn" style="margin: 0;" onclick="window.location.href='member/apply_scholarship_new.php?id=<?= $sch['id'] ?>'">Apply</button>
            <?php else: ?>
              <button class="primary-btn" style="margin: 0;" onclick="window.location.href='auth/register.php'">Register to Apply</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align: center;">No scholarships available at this time. Please check back soon!</p>
    <?php endif; ?>
  </section>

  <!-- Features -->
  <section id="features" class="features section">
    <h2>Key Features</h2>
    <div class="feature-grid">
      <div class="feature-card">📋 Online Application</div>
      <div class="feature-card">📄 Document Upload</div>
      <div class="feature-card">🔍 Application Tracking</div>
      <div class="feature-card">📧 Email Notifications</div>
      <div class="feature-card">📊 Admin Dashboard</div>
      <div class="feature-card">👥 Team Management</div>
    </div>
  </section>

  <!-- How It Works -->
  <section id="process" class="process section">
    <h2>How It Works</h2>
    <ol>
      <li><strong>Register an Account:</strong> Create your student profile with all required information</li>
      <li><strong>Explore & Apply:</strong> Browse available scholarships and submit your applications</li>
      <li><strong>Track Status:</strong> Monitor your applications in real-time from your dashboard</li>
      <li><strong>Get Notified:</strong> Receive email and in-app notifications about your application status</li>
      <li><strong>Receive Award:</strong> Once approved, receive your scholarship and disbursement details</li>
    </ol>
  </section>
</div>

<section class="contact">
  <h2>Contact Us</h2>
  <p>Email: scholarship@system.com</p>
  <p>Phone: +63 (2) 912-3456</p>
  <p>Office Hours: Mon-Fri, 9:00 AM - 5:00 PM</p>
</section>

<footer>
  <p>© 2026 Scholarship Management System | All Rights Reserved | <a href="#" style="color: #667eea; text-decoration: none;">Privacy Policy</a></p>
</footer>

</body>
</html>
