<?php
require __DIR__ . '/../includes/session.php';
requireRole('resident');
$pageTitle = 'Resident dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Resident dashboard</h1>
<p>Signed in as <?= htmlspecialchars($user['full_name']) ?>.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
