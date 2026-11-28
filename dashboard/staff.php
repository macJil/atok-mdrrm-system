<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('staff');
$area = in_array($_GET['barangay'] ?? '', BARANGAYS, true) ? $_GET['barangay'] : '';
$reports = ($_GET['tab'] ?? '') === 'reports';
if ($reports) {
    require __DIR__ . '/report-data.php';
}
$pageTitle = $reports ? 'Disaster Reports · Staff dashboard' : 'Staff dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Staff dashboard</h1>
<nav class="nav nav-tabs mb-3" aria-label="Dashboard sections">
    <a class="nav-link <?= $reports ? '' : 'active' ?>" <?= $reports ? '' : 'aria-current="page"' ?> href="staff.php?barangay=<?= urlencode($area) ?>">Overview</a>
    <a class="nav-link <?= $reports ? 'active' : '' ?>" <?= $reports ? 'aria-current="page"' : '' ?> href="staff.php?tab=reports&amp;barangay=<?= urlencode($area) ?>">Disaster Reports</a>
</nav>
<?php if ($reports): ?>
<form method="get" class="mb-3 filter-panel">
    <input type="hidden" name="tab" value="reports">
    <label>From <input type="date" class="form-control" name="from" value="<?= h($dates['from']) ?>"></label>
    <label>Through (inclusive) <input type="date" class="form-control" name="through" value="<?= h($dates['through']) ?>"></label>
    <label>Barangay <select class="form-select" name="barangay">
        <option value="">All barangays</option>
        <?php foreach (BARANGAYS as $name): ?><option <?= $area === $name ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
    </select></label>
    <button class="btn btn-primary">Apply filters</button>
    <button class="btn btn-outline-primary" name="export" value="pdf" formtarget="_blank">Print PDF</button>
</form>
<?php if ($error): ?>
    <p role="alert"><?= h($error) ?></p>
<?php else: ?>
    <div class="summary-board">
    <section class="stat-panel">
        <h2>Affected households</h2>
        <strong class="stat-number"><?= (int)$affected ?></strong>
        <p>Currently marked affected</p>
    </section>
    <section class="stat-panel" id="occupancy">
        <h2>Evacuation centers</h2>
        <strong class="stat-number"><?= array_sum(array_column($centers,'staying')) ?></strong>
        <p>People staying now · <?= array_sum(array_column($centers,'arrivals')) ?> arrivals in selected period</p>
        <details><summary>Occupancy by center</summary>
    <p class="results-summary">Capacity and people staying are current values; arrivals use the selected date period.</p>
    <div class="table-responsive"><table class="table record-list-table">
        <thead><tr><th>Center</th><th>Barangay</th><th>Status now</th><th>Person capacity</th><th>People staying now</th><th>Arrivals during selected period</th></tr></thead>
        <tbody>
            <?php foreach ($centers as $row): ?>
                <tr><td><?= h($row['name']) ?></td><td><?= h($row['barangay']) ?></td><td><span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span></td><td><?= (int)$row['capacity'] ?></td><td><?= (int)$row['staying'] ?></td><td><?= (int)$row['arrivals'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$centers): ?><p>No centers for this selection.</p><?php endif; ?>
        </details>
    </section>
    <section class="stat-panel">
        <h2>Incident reports</h2>
        <strong class="stat-number"><?= array_sum(array_map('array_sum', $counts)) ?></strong>
        <p>Reported in selected period</p>
        <details><summary>Counts by type and status</summary>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Type</th><?php foreach ($statuses as $status): ?><th><?= h($status) ?></th><?php endforeach; ?><th>Total</th></tr></thead>
        <tbody>
            <?php foreach ($counts as $type=>$numbers): ?>
                <tr><th scope="row"><?= h($type) ?></th>
                    <?php foreach ($statuses as $status): ?><td><?= $numbers[$status] ?></td><?php endforeach; ?>
                    <td><?= array_sum($numbers) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
        </details>
    </section>
    <section class="stat-panel">
        <h2>Relief distributions</h2>
        <strong class="stat-number"><?= (int)$distributionSummary['receipts'] ?></strong>
    <p>Distributions in selected period · <?= (int)$distributionSummary['households'] ?> households served</p>
    <details><summary>Quantities by good</summary>
    <p>Grouped by good and unit, using the barangay recorded at issue. Older distributions with an unconfirmed barangay appear only under All barangays.</p>
    <?php if ($unconfirmed): ?><p>Municipal distributions with unconfirmed barangay in this period: <?= $unconfirmed ?>.</p><?php endif; ?>
    <?php if (!$goods): ?><p class="empty-state">No goods distributed in this period.</p><?php endif; ?>
    <?php if ($goods): ?><ul class="summary-period-list"><?php foreach ($goods as $row): ?><li><?= h($row['name']) ?> · <?= (int)$row['issued'] ?> <?= h($row['unit']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </details></section>
    </div>
<?php endif; ?>
<?php
    require __DIR__ . '/../includes/footer.php';
    return;
endif;
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
        ['People staying in centers',$staying,'staff.php?tab=reports&barangay='.$areaFilter.'#occupancy'],
        ['Affected households',$affected,'../households/index.php?affected=1&barangay='.$areaFilter],
    ];
    foreach ($cards as [$label,$value,$url]): ?>
        <a class="dashboard-card" href="<?= h($url) ?>">
            <strong class="metric-value"><?= (int)$value ?></strong><span class="metric-label"><?= h($label) ?></span>
        </a>
    <?php endforeach; ?>
</section>
<div class="work-overview">
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

<section class="dashboard-card">
    <h2 class="h4">Incidents reported today</h2>
    <p><?= h($today) ?> · <?= h($area ?: 'All barangays') ?></p>
    <div class="today-counts">
        <?php foreach (INCIDENT_TYPES as $type): ?>
            <a href="../incidents/index.php?<?= h(http_build_query(['date'=>$today,'barangay'=>$area,'type'=>$type])) ?>">
                <?= h($type) ?> <strong><?= (int)($typeCounts[$type] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>
</div>
<?php if ($attention): ?>
    <p><a href="../relief_inventory/index.php?attention=1">Municipal stock: <?= (int)$attention ?> goods low or out of stock</a> (all barangays)</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/weather.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
