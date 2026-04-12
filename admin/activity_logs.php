<?php
/**
 * Activity Logs — alias for Audit Logs (admin view)
 * Redirects to audit_logs.php to avoid duplicate pages.
 */
header('Location: audit_logs.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
