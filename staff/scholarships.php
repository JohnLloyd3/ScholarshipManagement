<?php
// Staff can manage scholarships using the same page as admin/staff-enabled scholarships page.
require_once __DIR__ . '/../auth/helpers.php';
require_role(['staff', 'admin']);
header('Location: ../admin/scholarships.php');
exit;

