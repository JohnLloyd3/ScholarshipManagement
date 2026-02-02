<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scholarship Management System</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="navbar">
  <div class="logo">🎓 Scholarship Management</div>

  <nav>
    <a href="#home">Home</a>
    <a href="#features">Features</a>
    <a href="#process">Process</a>
    <a href="#contact">Contact</a>
  </nav>

  <div class="nav-buttons">
    <button class="login" onclick="window.location.href='auth/login.php'">
      Login
    </button>
    <button class="register" onclick="window.location.href='auth/register.php'">
      Register
    </button>
  </div>
</header>

<section id="home" class="hero">
  <h1>Scholarship Management System</h1>
  <p>Modern platform for managing scholarship applications efficiently.</p>
  <button class="primary-btn" onclick="window.location.href='auth/register.php'">
    Apply Now
  </button>
</section>

<section id="features" class="features">
  <h2>Key Features</h2>
  <div class="feature-grid">
    <div class="feature-card">Online Application</div>
    <div class="feature-card">Document Upload</div>
    <div class="feature-card">Application Tracking</div>
    <div class="feature-card">Approval Workflow</div>
    <div class="feature-card">Admin Dashboard</div>
    <div class="feature-card">Email Notifications</div>
  </div>
</section>

<section id="process" class="process">
  <h2>How It Works</h2>
  <ol>
    <li>Student registers an account</li>
    <li>Submits scholarship application</li>
    <li>Admin reviews and verifies documents</li>
    <li>Scholarship approval & notification</li>
  </ol>
</section>

<section id="contact" class="contact">
  <h2>Contact Us</h2>
  <p>Email: scholarship@system.com</p>
  <p>Phone: +63 912 345 6789</p>
</section>

<footer>
  <p>© 2026 Scholarship Management System | All Rights Reserved</p>
</footer>

</body>
</html>
