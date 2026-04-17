<?php
/**
 * Audit Helper
 * Thin wrapper around logAuditTrail() defined in ScreeningHelper.php.
 * Include this file wherever audit logging is needed.
 */

if (!function_exists('logAuditTrail')) {
    require_once __DIR__ . '/ScreeningHelper.php';
}

if (!function_exists('notifyStudent')) {
    require_once __DIR__ . '/NotificationHelper.php';
}
