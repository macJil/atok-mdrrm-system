<?php
require __DIR__ . '/../includes/session.php';
requireRole('resident');
$pageTitle = 'Resident dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Resident dashboard</h1>
<a class="btn btn-primary" href="../incidents/add.php">Report an incident</a>
<p>Signed in as <?= htmlspecialchars($user['full_name']) ?>.</p>
<?php
$total = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE status='Published'")->fetchColumn();
$pages = max(1, (int)ceil($total / 5));
$page = min($pages, max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)));
$offset = ($page - 1) * 5;
$notices = $pdo->query("SELECT title,category,message,published_at FROM announcements WHERE status='Published' ORDER BY published_at DESC,id DESC LIMIT 5 OFFSET $offset")->fetchAll();
?>
<?php if ($notices): ?>
    <section class="mt-4" aria-labelledby="notices">
        <h2 id="notices">Resident announcements</h2>
        <?php foreach ($notices as $notice): ?>
            <article class="mb-3">
                <h3 class="h5"><?= htmlspecialchars($notice['title']) ?></h3>
                <p class="mb-1"><?= htmlspecialchars($notice['category']) ?> · <?= htmlspecialchars($notice['published_at']) ?></p>
                <p><?= nl2br(htmlspecialchars($notice['message'])) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
    <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?page=<?= $page - 1 ?>#notices">Previous notices</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?page=<?= $page + 1 ?>#notices">More notices</a><?php endif; ?>
<?php else: ?>
    <p>No published announcements.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
