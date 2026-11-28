<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('staff');
$pageTitle = 'Staff dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Staff dashboard</h1>
<?php
$area = in_array($_GET['barangay'] ?? '', BARANGAYS, true) ? $_GET['barangay'] : '';
$args = [$area, $area];
$query = $pdo->prepare("SELECT id,incident_type,barangay,reported_at FROM incidents
    WHERE status='Pending' AND (?='' OR barangay=?) ORDER BY reported_at,id LIMIT 5");
$query->execute($args);
$pending = $query->fetchAll();
$today = $pdo->query('SELECT CURRENT_DATE')->fetchColumn();
$query = $pdo->prepare("SELECT incident_type,COUNT(*) AS total FROM incidents
    WHERE reported_at >= ? AND reported_at < DATE_ADD(?, INTERVAL 1 DAY)
    AND (?='' OR barangay=?) GROUP BY incident_type");
$query->execute([$today,$today,$area,$area]);
$typeCounts = $query->fetchAll(PDO::FETCH_KEY_PAIR);
$query = $pdo->prepare("SELECT COALESCE(SUM(status='Pending'),0) AS pending,
        COALESCE(SUM(status='Responding'),0) AS responding
    FROM incidents WHERE (?='' OR barangay=?)");
$query->execute($args);
$incidentStatusTotals = $query->fetch();
$query = $pdo->prepare("SELECT COUNT(s.id)
    FROM evacuation_centers c LEFT JOIN evacuation_stays s ON s.center_id=c.id AND s.left_at IS NULL
    WHERE c.status='Open' AND (?='' OR c.barangay=?)");
$query->execute($args);
$staying = (int)$query->fetchColumn();
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
    <?php
    $areaFilter = urlencode($area);
    $cards = [
        ['Pending reports',$incidentStatusTotals['pending'],'../incidents/index.php?status=Pending&barangay='.$areaFilter],
        ['Incidents being responded to',$incidentStatusTotals['responding'],'../incidents/index.php?status=Responding&barangay='.$areaFilter],
        ['People staying in centers',$staying,'../summaries.php?barangay='.$areaFilter.'#occupancy'],
        ['Affected households',$affected,'../households/index.php?affected=1&barangay='.$areaFilter],
    ];
    foreach ($cards as [$label,$value,$url]): ?>
        <a class="dashboard-card" href="<?= h($url) ?>">
            <strong class="metric-value"><?= (int)$value ?></strong><span class="metric-label"><?= h($label) ?></span>
        </a>
    <?php endforeach; ?>
</section>
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

<section class="dashboard-card my-3">
    <h2 class="h4">Incidents reported today</h2>
    <p><?= h($today) ?> · <?= h($area ?: 'All barangays') ?></p>
    <div class="record-summary">
        <?php foreach (INCIDENT_TYPES as $type): ?>
            <a href="../incidents/index.php?<?= h(http_build_query(['date'=>$today,'barangay'=>$area,'type'=>$type])) ?>">
                <?= h($type) ?> <strong><?= (int)($typeCounts[$type] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php if ($attention): ?>
    <p><a href="../relief_inventory/index.php?attention=1">Municipal stock: <?= (int)$attention ?> goods low or out of stock</a> (all barangays)</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/weather.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
