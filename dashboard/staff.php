<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$pageTitle = 'Staff dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Staff dashboard</h1>
<?php
$areas = ['Abiang','Caliking','Cattubo','Naguey','Paoay','Pasdong','Poblacion','Topdac'];
$area = in_array($_GET['barangay'] ?? '', $areas, true) ? $_GET['barangay'] : '';
$args = [$area, $area];
$query = $pdo->prepare("SELECT id,incident_type,barangay,reported_at FROM incidents
    WHERE status='Pending' AND (?='' OR barangay=?) ORDER BY reported_at,id LIMIT 5");
$query->execute($args);
$pending = $query->fetchAll();
$query = $pdo->prepare("SELECT incident_type,barangay,COUNT(*) AS total FROM incidents
    WHERE (?='' OR barangay=?) GROUP BY incident_type,barangay ORDER BY barangay,incident_type");
$query->execute($args);
$counts = $query->fetchAll();
$query = $pdo->prepare("SELECT c.id,c.name,c.barangay,c.capacity,COUNT(s.id) AS staying FROM evacuation_centers c
    LEFT JOIN evacuation_stays s ON s.center_id=c.id AND s.left_at IS NULL
    WHERE c.status='Open' AND (?='' OR c.barangay=?) GROUP BY c.id ORDER BY c.name");
$query->execute($args);
$centers = $query->fetchAll();
$query = $pdo->prepare("SELECT COUNT(*) FROM households WHERE registry_reason IN ('Affected','Monitored and affected') AND (?='' OR barangay=?)");
$query->execute($args);
$affected = $query->fetchColumn();
$attention = $pdo->query('SELECT COUNT(*) FROM relief_goods WHERE balance<=minimum_stock')->fetchColumn();
?>
<form method="get" class="mb-3">
    <label>Barangay
        <select class="form-select" name="barangay">
            <option value="">All barangays</option>
            <?php foreach ($areas as $name): ?><option <?= $area === $name ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select>
    </label>
    <button class="btn btn-primary">Apply filter</button>
</form>
<h2 class="h4">Oldest pending reports</h2>
<?php if (!$pending): ?><p>No reports awaiting review.</p><?php endif; ?>
<?php foreach ($pending as $row): ?>
    <p><?= htmlspecialchars($row['reported_at'].' · '.$row['incident_type'].' · '.($row['barangay'] ?? 'Awaiting confirmation')) ?>
        <a class="btn btn-outline-secondary" href="../incidents/view.php?id=<?= (int)$row['id'] ?>">Review</a>
    </p>
<?php endforeach; ?>
<h2 class="h4">Incident counts by barangay and type</h2>
<?php if (!$counts): ?><p>No incidents recorded for this selection.</p><?php endif; ?>
<ul>
    <?php foreach ($counts as $row): ?>
        <?php $name = $row['barangay'] ?? 'Awaiting confirmation'; ?>
        <li><a href="../incidents/index.php?<?= htmlspecialchars(http_build_query(['barangay'=>$name,'type'=>$row['incident_type']])) ?>">
            <?= htmlspecialchars($name.' · '.$row['incident_type'].' · '.$row['total']) ?>
        </a></li>
    <?php endforeach; ?>
</ul>
<h2 class="h4">People staying now: <?= array_sum(array_column($centers,'staying')) ?></h2>
<?php if (!$centers): ?><p>No open centers in this selection.</p><?php endif; ?>
<?php foreach ($centers as $center): ?>
    <p><a href="../evacuation/view.php?id=<?= (int)$center['id'] ?>&amp;tab=current"><?= htmlspecialchars($center['name']) ?></a>
        · <?= (int)$center['staying'] ?> staying / <?= (int)$center['capacity'] ?> capacity
    </p>
<?php endforeach; ?>
<p><a href="../households/index.php?affected=1&amp;barangay=<?= urlencode($area) ?>">Affected households: <?= (int)$affected ?></a></p>
<p><a href="../relief_inventory/index.php?attention=1">Municipal inventory · Goods needing attention: <?= (int)$attention ?></a> (all barangays)</p>
<h2 class="h4">Work areas</h2>
<a class="btn btn-primary" href="../summaries/index.php?barangay=<?= urlencode($area) ?>">Disaster summaries</a>
<a class="btn btn-primary" href="../map/index.php">Community map</a>
<p>Signed in as <?= htmlspecialchars($user['full_name']) ?>.</p>
<a class="btn btn-primary" href="../announcements/index.php">Manage announcements</a>
<a class="btn btn-primary" href="../relief_inventory/index.php">Relief inventory</a>
<a class="btn btn-primary" href="../relief_distribution/index.php">Relief distribution</a>
<a class="btn btn-primary" href="../evacuation/index.php">Evacuation centers</a>
<a class="btn btn-primary" href="../response/teams.php">Response teams</a>
<a class="btn btn-primary" href="../households/index.php">Household registry</a>
<a class="btn btn-primary" href="../accounts/index.php">Manage accounts</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
