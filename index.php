<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/SecurityHelper.php';

startSecureSession();

$scholarships = [];
$announcements = [];
$totalApps = 0;
$totalScholarships = 0;
$totalStudents = 0;

try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM scholarships WHERE status = 'open' AND (deadline IS NULL OR deadline > NOW()) ORDER BY deadline ASC LIMIT 9");
    $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $stmt = $pdo->query("SELECT * FROM announcements WHERE published = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY published_at DESC LIMIT 3");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $totalApps        = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    $totalScholarships = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'")->fetchColumn();
    $totalStudents    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
} catch (Exception $e) {
    error_log('[index.php] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ScholarHub - Your Gateway to Educational Opportunities</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #fff; color: #1a1a2e; line-height: 1.6; }

    /* Navbar */
    .navbar { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.97); backdrop-filter: blur(12px); border-bottom: 1px solid #D1D5DB; padding: 0 2.5rem; height: 64px; display: flex; align-items: center; justify-content: space-between; }
    .nav-logo { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
    .nav-logo-icon { width: 34px; height: 34px; background: #E8192C; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .nav-logo span { font-size: 1.2rem; font-weight: 700; color: #1a1a2e; }
    .nav-links { display: flex; align-items: center; gap: 2rem; list-style: none; }
    .nav-links a { text-decoration: none; color: #444; font-size: 0.9375rem; font-weight: 500; transition: color 0.2s; }
    .nav-links a:hover { color: #E8192C; }
    .nav-actions { display: flex; align-items: center; gap: 0.75rem; }
    .btn-signin { padding: 0.45rem 1.1rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #E8192C; background: transparent; border: none; cursor: pointer; text-decoration: none; transition: color 0.2s; }
    .btn-signin:hover { color: #c0001f; }
    .btn-getstarted { padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #fff; background: #E8192C; border: none; cursor: pointer; text-decoration: none; transition: background 0.2s, transform 0.15s; }
    .btn-getstarted:hover { background: #c0001f; transform: translateY(-1px); }

    /* Hero */
    .hero { background: linear-gradient(160deg, #fff5f5 0%, #fff 60%); padding: 5rem 2.5rem 4rem; min-height: calc(100vh - 64px); display: flex; align-items: center; }
    .hero-inner { max-width: 1200px; margin: 0 auto; width: 100%; }
    .hero-badge { display: inline-flex; align-items: center; gap: 0.4rem; background: #fff0f0; border: 1px solid #D1D5DB; border-radius: 999px; padding: 0.3rem 0.9rem; font-size: 0.8125rem; font-weight: 500; color: #E8192C; margin-bottom: 1.75rem; }
    .hero-title { font-size: clamp(2.25rem, 5vw, 3.5rem); font-weight: 800; line-height: 1.15; color: #1a1a2e; margin-bottom: 1.25rem; max-width: 700px; }
    .hero-title .accent { color: #E8192C; }
    .hero-desc { font-size: 1.0625rem; color: #555; max-width: 520px; line-height: 1.7; margin-bottom: 2.25rem; }
    .hero-btns { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 3.5rem; }
    .btn-hero-primary { padding: 0.8rem 2rem; border-radius: 10px; font-size: 1rem; font-weight: 700; color: #fff; background: #E8192C; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; transition: background 0.2s, transform 0.15s, box-shadow 0.2s; box-shadow: 0 4px 16px rgba(232,25,44,0.3); }
    .btn-hero-primary:hover { background: #c0001f; transform: translateY(-2px); }
    .btn-hero-secondary { padding: 0.8rem 2rem; border-radius: 10px; font-size: 1rem; font-weight: 600; color: #1a1a2e; background: #fff; border: 2px solid #D1D5DB; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; transition: border-color 0.2s, transform 0.15s; }
    .btn-hero-secondary:hover { border-color: #E8192C; color: #E8192C; transform: translateY(-2px); }

    /* Stats */
    .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; max-width: 860px; }
    .stat-card { background: #fff; border: 1px solid #D1D5DB; border-radius: 14px; padding: 1.25rem 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
    .stat-icon { width: 36px; height: 36px; background: #fff0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 0.75rem; color: #E8192C; }
    .stat-value { font-size: 1.5rem; font-weight: 800; color: #1a1a2e; line-height: 1; margin-bottom: 0.25rem; }
    .stat-label { font-size: 0.8rem; color: #888; font-weight: 500; }

    /* Section */
    .section { padding: 5rem 2.5rem; }
    .section-inner { max-width: 1200px; margin: 0 auto; }
    .section-header { text-align: center; margin-bottom: 3rem; }
    .section-header h2 { font-size: clamp(1.5rem, 3vw, 2.25rem); font-weight: 800; color: #1a1a2e; margin-bottom: 0.75rem; }
    .section-header p { font-size: 1rem; color: #666; max-width: 500px; margin: 0 auto; }

    /* Scholarship cards */
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
    .sch-card { background: #fff; border: 1px solid #D1D5DB; border-radius: 14px; padding: 1.5rem; transition: box-shadow 0.2s, transform 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .sch-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.1); transform: translateY(-3px); }
    .sch-badge { display: inline-block; background: #fff0f0; color: #E8192C; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.75rem; }
    .sch-title { font-size: 1rem; font-weight: 700; color: #1a1a2e; margin-bottom: 0.5rem; }
    .sch-desc { font-size: 0.875rem; color: #666; line-height: 1.6; margin-bottom: 1rem; }
    .sch-footer { display: flex; justify-content: space-between; align-items: center; }
    .sch-amount { font-size: 1.25rem; font-weight: 800; color: #E8192C; }
    .sch-deadline { font-size: 0.75rem; color: #999; margin-top: 0.15rem; }
    .btn-apply { padding: 0.45rem 1.1rem; border-radius: 8px; font-size: 0.8125rem; font-weight: 600; color: #fff; background: #E8192C; text-decoration: none; transition: background 0.2s; border: none; cursor: pointer; }
    .btn-apply:hover { background: #c0001f; }

    /* Features */
    .features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
    .feature-card { background: #fff; border: 1px solid #D1D5DB; border-radius: 14px; padding: 1.75rem; text-align: center; }
    .feature-icon { font-size: 2rem; margin-bottom: 1rem; }
    .feature-title { font-size: 1rem; font-weight: 700; color: #1a1a2e; margin-bottom: 0.5rem; }
    .feature-desc { font-size: 0.875rem; color: #666; line-height: 1.6; }

    /* CTA */
    .cta-section { background: linear-gradient(135deg, #E8192C 0%, #c0001f 100%); padding: 5rem 2.5rem; text-align: center; }
    .cta-section h2 { font-size: clamp(1.5rem, 3vw, 2.25rem); font-weight: 800; color: #fff; margin-bottom: 1rem; }
    .cta-section p { font-size: 1.0625rem; color: rgba(255,255,255,0.85); max-width: 520px; margin: 0 auto 2rem; }
    .btn-cta { padding: 0.875rem 2.5rem; border-radius: 10px; font-size: 1rem; font-weight: 700; color: #E8192C; background: #fff; border: none; cursor: pointer; text-decoration: none; display: inline-block; transition: transform 0.15s, box-shadow 0.2s; box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
    .btn-cta:hover { transform: translateY(-2px); }

    /* Footer */
    .footer { background: #1a1a2e; padding: 2.5rem; text-align: center; }
    .footer-links { display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.875rem; transition: color 0.2s; }
    .footer-links a:hover { color: #fff; }
    .footer-copy { color: rgba(255,255,255,0.4); font-size: 0.8125rem; }

    @media (max-width: 768px) {
      .nav-links { display: none; }
      .stats-row { grid-template-columns: repeat(2, 1fr); }
      .hero { padding: 3rem 1.5rem; }
      .section { padding: 3rem 1.5rem; }
    }
  </style>
</head>
<body>

  <nav class="navbar">
    <a href="index.php" class="nav-logo">
      <div class="nav-logo-icon">&#127891;</div>
      <span>ScholarHub</span>
    </a>
    <ul class="nav-links">
      <li><a href="#home">Home</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#scholarships">Scholarships</a></li>
      <li><a href="#contact">Contact</a></li>
    </ul>
    <div class="nav-actions">
      <?php if (isLoggedIn()):
        $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'student';
        $dashUrl = match($role) { 'admin' => 'admin/dashboard.php', 'staff' => 'staff/dashboard.php', default => 'students/dashboard.php' };
      ?>
        <a href="<?= $dashUrl ?>" class="btn-signin">Dashboard</a>
        <a href="auth/logout.php" class="btn-getstarted">Logout</a>
      <?php else: ?>
        <a href="auth/login.php" class="btn-signin">Sign In</a>
        <a href="auth/register.php" class="btn-getstarted">Get Started</a>
      <?php endif; ?>
    </div>
  </nav>

  <section id="home" class="hero">
    <div class="hero-inner">
      <div class="hero-badge"><span>&#11088;</span><span>Trusted by 18,500+ students nationwide</span></div>
      <h1 class="hero-title">Your Gateway to<br><span class="accent">Educational</span> Opportunities</h1>
      <p class="hero-desc">Discover, apply, and track thousands of scholarships tailored to your academic profile. ScholarHub simplifies your path to funding.</p>
      <div class="hero-btns">
        <?php if (!isLoggedIn()): ?>
          <a href="auth/register.php" class="btn-hero-primary">Start for Free &rarr;</a>
          <a href="auth/login.php" class="btn-hero-secondary">Sign In</a>
        <?php else: ?>
          <a href="students/scholarships.php" class="btn-hero-primary">Browse Scholarships &rarr;</a>
          <a href="students/applications.php" class="btn-hero-secondary">My Applications</a>
        <?php endif; ?>
      </div>
      <div class="stats-row">
        <div class="stat-card"><div class="stat-icon">&#127891;</div><div class="stat-value">2,400+</div><div class="stat-label">Active Scholarships</div></div>
        <div class="stat-card"><div class="stat-icon">&#128101;</div><div class="stat-value">18,500+</div><div class="stat-label">Students Funded</div></div>
        <div class="stat-card"><div class="stat-icon">&#127979;</div><div class="stat-value">340+</div><div class="stat-label">Partner Institutions</div></div>
        <div class="stat-card"><div class="stat-icon">&#128200;</div><div class="stat-value">$48M+</div><div class="stat-label">Total Disbursed</div></div>
      </div>
    </div>
  </section>

  <?php if (!empty($scholarships)): ?>
  <section id="scholarships" class="section" style="background:#fafafa;">
    <div class="section-inner">
      <div class="section-header"><h2>Available Scholarships</h2><p>Find the perfect opportunity for your educational journey</p></div>
      <div class="cards-grid">
        <?php foreach ($scholarships as $sch): ?>
        <div class="sch-card">
          <span class="sch-badge">Open</span>
          <div class="sch-title"><?= htmlspecialchars($sch['title']) ?></div>
          <div class="sch-desc"><?= htmlspecialchars(substr($sch['description'] ?? '', 0, 110)) ?>...</div>
          <div class="sch-footer">
            <div>
              <div class="sch-amount">&#8369;<?= number_format($sch['amount'] ?? 0) ?></div>
              <div class="sch-deadline">Deadline: <?= date('M d, Y', strtotime($sch['deadline'])) ?></div>
            </div>
            <?php if (isLoggedIn()): ?>
              <a href="students/apply_scholarship.php?scholarship_id=<?= $sch['id'] ?>" class="btn-apply">Apply Now</a>
            <?php else: ?>
              <a href="auth/register.php" class="btn-apply">Register</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section id="about" class="section">
    <div class="section-inner">
      <div class="section-header"><h2>Why Choose ScholarHub?</h2><p>Everything you need to succeed in one place</p></div>
      <div class="features-grid">
        <div class="feature-card"><div class="feature-icon">&#128241;</div><div class="feature-title">Easy Application</div><div class="feature-desc">Apply to multiple scholarships with a single profile</div></div>
        <div class="feature-card"><div class="feature-icon">&#128276;</div><div class="feature-title">Real-time Updates</div><div class="feature-desc">Get instant notifications about your application status</div></div>
        <div class="feature-card"><div class="feature-icon">&#128202;</div><div class="feature-title">Track Progress</div><div class="feature-desc">Monitor all your applications in one dashboard</div></div>
        <div class="feature-card"><div class="feature-icon">&#128274;</div><div class="feature-title">Secure & Private</div><div class="feature-desc">Your data is protected with enterprise-grade security</div></div>
        <div class="feature-card"><div class="feature-icon">&#128172;</div><div class="feature-title">Expert Guidance</div><div class="feature-desc">Get tips and support from our scholarship experts</div></div>
        <div class="feature-card"><div class="feature-icon">&#9889;</div><div class="feature-title">Fast Processing</div><div class="feature-desc">Quick review and approval process for all applications</div></div>
      </div>
    </div>
  </section>

  <section id="contact" class="cta-section">
    <h2>Ready to Start Your Journey?</h2>
    <p>Join thousands of students who have already found their perfect scholarship opportunity.</p>
    <?php if (!isLoggedIn()): ?>
      <a href="auth/register.php" class="btn-cta">Create Free Account</a>
    <?php else: ?>
      <a href="students/scholarships.php" class="btn-cta">Browse Scholarships</a>
    <?php endif; ?>
  </section>

  <footer class="footer">
    <div class="footer-links">
      <a href="#">About Us</a><a href="#">Contact</a><a href="#">Privacy Policy</a><a href="#">Terms of Service</a><a href="#">FAQ</a>
    </div>
    <p class="footer-copy">&copy; 2026 ScholarHub. All rights reserved. Made with &#10084;&#65039; for students.</p>
  </footer>

</body>
</html>
