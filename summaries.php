<?php
require __DIR__ . '/includes/session.php';
requireRole('staff');
$query = http_build_query(array_intersect_key($_GET, array_flip(['barangay', 'from', 'through', 'export'])));
header('Location: dashboard/reports.php' . ($query === '' ? '' : '?' . $query));
exit;
