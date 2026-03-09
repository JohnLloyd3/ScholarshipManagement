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
  <title>Scholarship Hub - Your Future Starts Here</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/modern-theme.css">
</head>
<body>

  <!-- Modern Navbar -->
  <nav class="navbar">
    <div class="navbar-container">
      <a href="index.php" class="navbar-logo">
        <span class="navbar-logo-icon">🎓</span>
        <span>ScholarHub</span>
      </a>
      
      <ul class="navbar-menu">
        <li><a href="#home" class="navbar-link">Home</a></li>
        <li><a href="#scholarships" class="navbar-link">Scholarships</a></li>
        <li><a href="#features" class="navbar-link">Features</a></li>
        <li><a href="#about" class="navbar-link">About</a></li>
      </ul>
      
      <div class="navbar-actions">
        <?php if (isLoggedIn()): ?>
          <a href="member/dashboard.php" class="btn btn-ghost">Dashboard</a>
          <a href="auth/logout.php" class="btn btn-primary">Logout</a>
        <?php else: ?>
          <a href="auth/login.php" class="btn btn-ghost">Login</a>
          <a href="auth/register.php" class="btn btn-primary">Get Started</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section id="home" class="hero">
    <div class="container">
      <div class="hero-content fade-in">
        <div class="hero-badge">
          <span>✨</span>
          <span><?= $totalScholarships ?> Active Scholarships</span>
        </div>
        
        <h1 class="hero-title">
          Your Future Starts with a <span class="highlight">Scholarship</span>
        </h1>
        
        <p class="hero-description">
          Discover opportunities, apply with ease, and track your journey to success. 
          Join thousands of students achieving their dreams.
        </p>
        
        <div class="hero-actions">
          <?php if (!isLoggedIn()): ?>
            <a href="auth/register.php" class="btn btn-primary btn-lg">Apply Now</a>
            <a href="#scholarships" class="btn btn-secondary btn-lg">Browse Scholarships</a>
          <?php else: ?>
            <a href="member/scholarships.php" class="btn btn-primary btn-lg">Explore Scholarships</a>
            <a href="member/applications.php" class="btn btn-secondary btn-lg">My Applications</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Stats Section -->
  <section class="section-sm bg-white">
    <div class="container">
      <div class="stats-grid">
        <div class="stat-card slide-in">
          <div class="stat-icon">📚</div>
          <div class="stat-value"><?= $totalScholarships ?></div>
          <div class="stat-label">Active Scholarships</div>
        </div>
        
        <div class="stat-card slide-in" style="animation-delay: 0.1s;">
          <div class="stat-icon">👥</div>
          <div class="stat-value"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn() ?: 0 ?></div>
          <div class="stat-label">Students Registered</div>
        </div>
        
        <div class="stat-card slide-in" style="animation-delay: 0.2s;">
          <div class="stat-icon">📝</div>
          <div class="stat-value"><?= $totalApps ?></div>
          <div class="stat-label">Applications Submitted</div>
        </div>
        
        <div class="stat-card slide-in" style="animation-delay: 0.3s;">
          <div class="stat-icon">🎉</div>
          <div class="stat-value">98%</div>
          <div class="stat-label">Success Rate</div>
        </div>
      </div>
    </div>
  </section>

  <div class="container">

  <!-- Announcements -->
  <?php if (!empty($announcements)): ?>
  <section class="section bg-gray">
    <div class="container">
      <h2 class="text-center mb-4">📢 Latest Updates</h2>
      
      <div class="feature-grid">
        <?php foreach ($announcements as $ann): ?>
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><?= sanitizeString($ann['title']) ?></h3>
            <p class="card-subtitle"><?= date('M d, Y', strtotime($ann['published_at'])) ?></p>
          </div>
          <div class="card-body">
            <p><?= sanitizeString(substr($ann['message'], 0, 150)) ?>...</p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Scholarships Section -->
  <section id="scholarships" class="section bg-white">
    <div class="container">
      <div class="text-center mb-4">
        <h2>Available Scholarships</h2>
        <p class="text-gray">Find the perfect opportunity for your educational journey</p>
      </div>
      
      <?php if (!empty($scholarships)): ?>
      <div class="feature-grid">
        <?php foreach ($scholarships as $sch): ?>
        <div class="card">
          <div class="card-header">
            <div class="flex justify-between items-center">
              <h3 class="card-title"><?= sanitizeString($sch['title']) ?></h3>
              <span class="badge badge-primary">Open</span>
            </div>
          </div>
          
          <div class="card-body">
            <p><?= sanitizeString(substr($sch['description'] ?? '', 0, 120)) ?>...</p>
          </div>
          
          <div class="card-footer">
            <div>
              <div class="stat-value" style="font-size: 1.5rem;">₱<?= number_format($sch['amount'] ?? 0, 0) ?></div>
              <div class="text-muted" style="font-size: 0.875rem;">
                Deadline: <?= date('M d, Y', strtotime($sch['deadline'])) ?>
              </div>
            </div>
            <?php if (isLoggedIn()): ?>
              <a href="member/apply_scholarship.php?scholarship_id=<?= $sch['id'] ?>" class="btn btn-primary btn-sm">Apply Now</a>
            <?php else: ?>
              <a href="auth/register.php" class="btn btn-secondary btn-sm">Register</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="text-center">
        <p class="text-gray">No scholarships available at this time. Check back soon!</p>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Features Section -->
  <section id="features" class="section bg-gray">
    <div class="container">
      <div class="text-center mb-4">
        <h2>Why Choose ScholarHub?</h2>
        <p class="text-gray">Everything you need to succeed in one place</p>
      </div>
      
      <div class="feature-grid">
        <div class="feature-card">
          <div class="feature-icon">📱</div>
          <h3 class="feature-title">Easy Application</h3>
          <p class="feature-description">Apply to multiple scholarships with a single profile</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">🔔</div>
          <h3 class="feature-title">Real-time Updates</h3>
          <p class="feature-description">Get instant notifications about your application status</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3 class="feature-title">Track Progress</h3>
          <p class="feature-description">Monitor all your applications in one dashboard</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">🔒</div>
          <h3 class="feature-title">Secure & Private</h3>
          <p class="feature-description">Your data is protected with enterprise-grade security</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">💬</div>
          <h3 class="feature-title">24/7 Support</h3>
          <p class="feature-description">Get help whenever you need it from our support team</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">⚡</div>
          <h3 class="feature-title">Fast Processing</h3>
          <p class="feature-description">Quick review and approval process for all applications</p>
        </div>
      </div>
    </div>
  </section>

  <!-- How It Works -->
  <section id="about" class="section bg-white">
    <div class="container container-sm">
      <div class="text-center mb-4">
        <h2>How It Works</h2>
        <p class="text-gray">Get started in 4 simple steps</p>
      </div>
      
      <div class="flex flex-col gap-3">
        <div class="card">
          <div class="flex gap-3">
            <div style="flex-shrink: 0; width: 48px; height: 48px; background: var(--red-primary); color: white; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem;">1</div>
            <div>
              <h4 class="card-title">Create Your Profile</h4>
              <p class="text-gray">Sign up and complete your student profile with your academic information</p>
            </div>
          </div>
        </div>
        
        <div class="card">
          <div class="flex gap-3">
            <div style="flex-shrink: 0; width: 48px; height: 48px; background: var(--red-primary); color: white; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem;">2</div>
            <div>
              <h4 class="card-title">Browse Scholarships</h4>
              <p class="text-gray">Explore available scholarships and find the ones that match your goals</p>
            </div>
          </div>
        </div>
        
        <div class="card">
          <div class="flex gap-3">
            <div style="flex-shrink: 0; width: 48px; height: 48px; background: var(--red-primary); color: white; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem;">3</div>
            <div>
              <h4 class="card-title">Submit Application</h4>
              <p class="text-gray">Fill out the application form and upload required documents</p>
            </div>
          </div>
        </div>
        
        <div class="card">
          <div class="flex gap-3">
            <div style="flex-shrink: 0; width: 48px; height: 48px; background: var(--red-primary); color: white; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem;">4</div>
            <div>
              <h4 class="card-title">Track & Receive</h4>
              <p class="text-gray">Monitor your application status and receive your scholarship award</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

  <!-- CTA Section -->
  <section class="section" style="background: linear-gradient(135deg, var(--red-primary) 0%, var(--red-dark) 100%); color: white;">
    <div class="container text-center">
      <h2 style="color: white; margin-bottom: var(--space-lg);">Ready to Start Your Journey?</h2>
      <p style="color: rgba(255,255,255,0.9); font-size: 1.125rem; margin-bottom: var(--space-xl); max-width: 600px; margin-left: auto; margin-right: auto;">
        Join thousands of students who have already found their perfect scholarship opportunity
      </p>
      <?php if (!isLoggedIn()): ?>
        <a href="auth/register.php" class="btn btn-lg" style="background: white; color: var(--red-primary);">Create Free Account</a>
      <?php else: ?>
        <a href="member/scholarships.php" class="btn btn-lg" style="background: white; color: var(--red-primary);">Browse Scholarships</a>
      <?php endif; ?>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-links">
          <a href="#" class="footer-link">About Us</a>
          <a href="#" class="footer-link">Contact</a>
          <a href="#" class="footer-link">Privacy Policy</a>
          <a href="#" class="footer-link">Terms of Service</a>
          <a href="#" class="footer-link">FAQ</a>
        </div>
        <p class="footer-copyright">
          © 2026 ScholarHub. All rights reserved. Made with ❤️ for students.
        </p>
      </div>
    </div>
  </footer>

</body>
</html>
