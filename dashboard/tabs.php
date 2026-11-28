<?php
if (!isset($user) || $user['role'] !== 'staff') {
    http_response_code(403);
    exit;
}
$reportTab = basename($_SERVER['SCRIPT_NAME']) === 'reports.php';
?>
<h1>Staff dashboard</h1>
<nav class="nav nav-tabs mb-3" aria-label="Staff dashboard sections">
    <a class="nav-link <?= $reportTab ? '' : 'active' ?>" <?= $reportTab ? '' : 'aria-current="page"' ?> href="staff.php?barangay=<?= urlencode($area) ?>">Overview</a>
    <a class="nav-link <?= $reportTab ? 'active' : '' ?>" <?= $reportTab ? 'aria-current="page"' : '' ?> href="reports.php?barangay=<?= urlencode($area) ?>">Disaster Reports</a>
</nav>
