<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('staff');
$pageTitle = 'Staff dashboard';
require __DIR__ . '/../includes/header.php';
$area = in_array($_GET['barangay'] ?? '', BARANGAYS, true) ? $_GET['barangay'] : '';
require __DIR__ . '/tabs.php';
$args = [$area, $area];
$query = $pdo->prepare("SELECT id,incident_type,barangay,reported_at FROM incidents
    WHERE status='Pending' AND (?='' OR barangay=?) ORDER BY reported_at,id LIMIT 5");
$query->execute($args);
$pending = $query->fetchAll();
$query = $pdo->prepare("SELECT incident_type,COUNT(*) AS total,
        SUM(status='Pending') AS pending,SUM(status='Responding') AS responding
    FROM incidents WHERE (?='' OR barangay=?) GROUP BY incident_type ORDER BY incident_type");
$query->execute($args);
$typeCounts = $query->fetchAll();
$query = $pdo->prepare("SELECT COALESCE(SUM(status='Pending'),0) AS pending,
        COALESCE(SUM(status='Responding'),0) AS responding
    FROM incidents WHERE (?='' OR barangay=?)");
$query->execute($args);
$incidentStatusTotals = $query->fetch();
$query = $pdo->prepare("SELECT COUNT(DISTINCT c.id) AS centers,COUNT(s.id) AS staying
    FROM evacuation_centers c LEFT JOIN evacuation_stays s ON s.center_id=c.id AND s.left_at IS NULL
    WHERE c.status='Open' AND (?='' OR c.barangay=?)");
$query->execute($args);
$centerTotals = $query->fetch();
$query = $pdo->prepare("SELECT c.id,c.name,c.barangay,c.capacity,COUNT(s.id) AS staying FROM evacuation_centers c
    LEFT JOIN evacuation_stays s ON s.center_id=c.id AND s.left_at IS NULL
    WHERE c.status='Open' AND (?='' OR c.barangay=?) GROUP BY c.id ORDER BY c.name LIMIT 5");
$query->execute($args);
$centers = $query->fetchAll(PDO::FETCH_ASSOC);
$query = $pdo->prepare("SELECT COUNT(*) FROM households WHERE registry_reason IN ('Affected','Monitored and affected') AND (?='' OR barangay=?)");
$query->execute($args);
$affected = $query->fetchColumn();
$attention = $pdo->query('SELECT COUNT(*) FROM relief_goods WHERE balance<=minimum_stock')->fetchColumn();
?>
<form method="get" class="dashboard-filter filter-panel">
    <label>Barangay
        <select class="form-select" name="barangay">
            <option value="">All barangays</option>
            <?php foreach (BARANGAYS as $name): ?><option <?= $area === $name ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select>
    </label>
    <button class="btn btn-primary">Apply filter</button>
</form>
<section class="dashboard-metrics" aria-label="Current operational summary">
    <div><span class="metric-value"><?= (int)$incidentStatusTotals['pending'] ?></span><span class="metric-label"><a href="../incidents/index.php?status=Pending&amp;barangay=<?= urlencode($area) ?>">Awaiting review</a></span></div>
    <div><span class="metric-value"><?= (int)$incidentStatusTotals['responding'] ?></span><span class="metric-label"><a href="../incidents/index.php?status=Responding&amp;barangay=<?= urlencode($area) ?>">Under response</a></span></div>
    <div><span class="metric-value"><?= (int)$centerTotals['staying'] ?></span><span class="metric-label"><a href="reports.php?barangay=<?= urlencode($area) ?>">People staying in centers</a></span></div>
    <div><span class="metric-value"><?= (int)$affected ?></span><span class="metric-label"><a href="../households/index.php?affected=1&amp;barangay=<?= urlencode($area) ?>">Affected households</a></span></div>
</section>
<section class="dashboard-card mb-3" aria-labelledby="incident-totals">
    <h2 class="h4" id="incident-totals">Incident reports by type</h2>
    <p class="metric-label">All recorded reports · <?= h($area ?: 'All barangays') ?></p>
    <?php if (!$typeCounts): ?><p class="empty-state">No incident reports recorded for this selection.</p><?php else: ?>
        <div class="record-summary">
            <?php foreach ($typeCounts as $row): ?>
                <div>
                    <a href="../incidents/index.php?<?= h(http_build_query(['type'=>$row['incident_type'],'barangay'=>$area])) ?>">
                        <strong><?= h($row['incident_type']) ?></strong>
                    </a>
                    <span class="metric-value"><?= (int)$row['total'] ?></span>
                    <span class="metric-label"><?= (int)$row['pending'] ?> pending · <?= (int)$row['responding'] ?> responding</span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<div class="dashboard-grid">
    <section class="dashboard-card" aria-labelledby="pending-reports">
        <h2 class="h4" id="pending-reports">Oldest pending reports</h2>
        <?php if (!$pending): ?><p class="empty-state">No reports awaiting review.</p><?php endif; ?>
        <?php foreach ($pending as $row): ?>
            <div class="dashboard-row">
                <p><?= h($row['reported_at'].' · '.$row['incident_type'].' · '.($row['barangay'] ?? 'Awaiting confirmation')) ?></p>
                <a class="btn btn-primary btn-sm" href="../incidents/view.php?id=<?= (int)$row['id'] ?>">Review report</a>
            </div>
        <?php endforeach; ?>
        <?php if ($pending): ?><a href="../incidents/index.php?status=Pending&amp;barangay=<?= urlencode($area) ?>">View all pending reports</a><?php endif; ?>
    </section>

    <section class="dashboard-card dashboard-card--wide" aria-labelledby="open-centers">
        <h2 class="h4" id="open-centers">Open evacuation centers</h2>
        <p><?= (int)$centerTotals['centers'] ?> open centers · <?= (int)$centerTotals['staying'] ?> people staying · showing up to five centers</p>
        <?php if (!$centers): ?><p class="empty-state">No open centers in this selection.</p><?php endif; ?>
        <?php foreach ($centers as $center): ?>
            <?php $occupancy = max(0, min(100, (int)round(100 * (int)$center['staying'] / max(1, (int)$center['capacity'])))); ?>
            <div class="dashboard-row">
                <div class="flex-grow-1 w-100">
                    <p><a href="../evacuation/view.php?id=<?= (int)$center['id'] ?>&amp;tab=current"><?= h($center['name']) ?></a>
                        <span class="text-secondary">· <?= h($center['barangay']) ?></span>
                    </p>
                    <div class="progress mt-2" role="progressbar" aria-label="<?= h($center['name']) ?> occupancy" aria-valuenow="<?= $occupancy ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-success" style="width: <?= $occupancy ?>%"></div>
                    </div>
                </div>
                <strong><?= (int)$center['staying'] ?> / <?= (int)$center['capacity'] ?></strong>
            </div>
        <?php endforeach; ?>
        <?php if ((int)$centerTotals['centers'] > count($centers)): ?><a href="../evacuation/index.php">View all evacuation centers</a><?php endif; ?>
    </section>

</div>
<p><a href="../relief_inventory/index.php?attention=1">Municipal goods low or out of stock: <?= (int)$attention ?></a> · All barangays</p>
<?php require __DIR__ . '/../includes/weather.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
