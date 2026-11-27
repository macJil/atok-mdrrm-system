<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$pageTitle = 'Staff dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Staff dashboard</h1>
<p>Signed in as <?= htmlspecialchars($user['full_name']) ?>.</p>
<a class="btn btn-primary" href="../response/teams.php">Response teams</a>
<a class="btn btn-primary" href="../households/index.php">Household registry</a>
<a class="btn btn-primary" href="../accounts/index.php">Manage accounts</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
