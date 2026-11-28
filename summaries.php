<?php
require __DIR__ . '/includes/session.php';
requireRole('staff');

// Keep saved links and PDF shortcuts working.
$query = ['tab' => 'reports'];
foreach (['barangay', 'from', 'through', 'export'] as $field) {
    if (isset($_GET[$field]) && is_string($_GET[$field])) {
        $query[$field] = $_GET[$field];
    }
}
header('Location: dashboard/staff.php?' . http_build_query($query));
exit;
