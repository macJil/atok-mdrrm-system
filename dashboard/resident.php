<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('resident');
$pageTitle = 'Resident dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Resident dashboard</h1>
<div class="resident-actions mb-4">
    <a class="btn btn-primary" href="../incidents/add.php">Report an incident</a>
    <a class="btn btn-outline-secondary" href="../map/index.php">Community map</a>
    <a class="btn btn-outline-secondary" href="../incidents/index.php">All my reports</a>
</div>
<section class="content-card p-3 p-md-4 mb-4" aria-labelledby="latest-report">
<h2 class="h4" id="latest-report">My latest report</h2>
<?php
$query = $pdo->prepare("SELECT id,incident_type,status,reported_at FROM incidents
    WHERE submitted_by=? AND source='Resident' ORDER BY reported_at DESC,id DESC LIMIT 1");
$query->execute([$user['id']]);
$report = $query->fetch();
?>
<?php if ($report): ?>
    <p><?= h($report['incident_type']) ?> · <span class="status-badge status-<?= strtolower(h($report['status'])) ?>"><?= h($report['status']) ?></span> · <?= h($report['reported_at']) ?></p>
    <a class="btn btn-outline-primary" href="../incidents/view.php?id=<?= (int)$report['id'] ?>">View report details</a>
<?php else: ?>
    <p class="empty-state">You have no incident reports yet. Use “Report an incident” when you need to submit one.</p>
<?php endif; ?>
<p class="text-secondary mt-3 mb-0">Signed in as <?= h($user['full_name']) ?>.</p>
</section>
<?php
$total = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE status='Published'")->fetchColumn();
[$page, $offset, $pages] = pageWindow($total, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT), 5);
$notices = $pdo->query("SELECT title,category,message,published_at FROM announcements WHERE status='Published' ORDER BY published_at DESC,id DESC LIMIT 5 OFFSET $offset")->fetchAll();
?>
<?php if ($notices): ?>
    <section class="resident-announcements mt-4" aria-labelledby="notices">
        <h2 id="notices">Resident announcements</h2>
        <?php foreach ($notices as $notice): ?>
            <article class="mb-3">
                <h3 class="h5"><?= h($notice['title']) ?></h3>
                <p class="mb-1"><?= h($notice['category']) ?> · <?= h($notice['published_at']) ?></p>
                <p><?= nl2br(h($notice['message'])) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
    <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?page=<?= $page - 1 ?>#notices">Previous notices</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?page=<?= $page + 1 ?>#notices">More notices</a><?php endif; ?>
<?php else: ?>
    <p>No published announcements.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/weather.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
