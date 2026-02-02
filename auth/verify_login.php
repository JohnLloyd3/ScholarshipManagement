<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../member/dashboard.php");
    exit;
}
// Login is now direct (no verification step). Keep this page as a safe redirect.
header("Location: login.php");
exit;
?>
<!-- verify_login is no longer used -->
