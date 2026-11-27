<?php
require __DIR__ . '/record.php';
$pageTitle = $reference;
require __DIR__ . '/../includes/header.php';
?>
<h1><?= $reference ?></h1>
<p><?= htmlspecialchars($report['incident_type']) ?> · <?= htmlspecialchars($report['status']) ?> · Reported <?= htmlspecialchars($report['reported_at']) ?></p>
<h2 class="h5">Location</h2>
<p><?= htmlspecialchars($report['barangay'] ?? 'Awaiting confirmation') ?> · <?= htmlspecialchars($report['location']) ?></p>
<h2 class="h5">Description</h2>
<p><?= nl2br(htmlspecialchars($report['description'])) ?></p>
<h2 class="h5">Reporter</h2>
<p><?= htmlspecialchars($report['contact_name']) ?> · <?= htmlspecialchars($report['contact_phone']) ?> · <?= htmlspecialchars($report['source']) ?></p>
<?php if ($report['has_image']): ?><p><a class="btn btn-outline-secondary" href="image.php?id=<?= $id ?>">View attached image</a></p><?php endif; ?>
<a class="btn btn-primary" href="print.php?id=<?= $id ?>">Open report copy</a>
<a class="btn btn-outline-secondary" href="index.php">Return to reports</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
