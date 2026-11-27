<?php
require_once __DIR__ . '/includes/session.php';
$pageTitle = 'Service overview';
try {
    db()->query('SELECT 1')->fetchColumn();
    $status = 'Database connected';
} catch (PDOException $error) {
    http_response_code(503);
    $status = 'Database unavailable. Check the local setup and refresh.';
}
require __DIR__ . '/includes/header.php';
?>
<h1>Service overview</h1>
<p>This system is being prepared for incident reporting, evacuation monitoring and relief coordination across Atok.</p>
<section id="service-status" class="status-panel mt-4 p-3" aria-labelledby="status-title">
    <h2 id="status-title" class="h5">Service status</h2>
    <p class="mb-0"><?= htmlspecialchars($status) ?></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
