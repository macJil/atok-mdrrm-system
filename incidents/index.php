<?php
require __DIR__ . '/access.php';
$staff = $user['role'] === 'staff';
$conditions = [];
$args = [];
if (!$staff) {
    $conditions[] = "submitted_by=? AND source='Resident'";
    $args[] = $user['id'];
}
$filters = [];
foreach (['reference','type','status','date','barangay'] as $field) {
    $filters[$field] = is_string($_GET[$field] ?? null) ? trim($_GET[$field]) : '';
}
$filterError = '';
if ($filters['date'] !== '') {
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $filters['date']);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $filters['date']) {
        $filters['date'] = '';
        $filterError = 'Enter a valid reported date.';
    }
}
if ($filters['reference'] !== '') {
    $conditions[] = "CONCAT('ATOK-IR-',YEAR(reported_at),'-',LPAD(id,GREATEST(4,CHAR_LENGTH(id)),'0'))=?";
    $args[] = $filters['reference'];
}
foreach (['type'=>'incident_type','status'=>'status','barangay'=>'barangay'] as $field=>$column) {
    if ($field === 'barangay' && $filters[$field] === 'Awaiting confirmation') {
        $conditions[] = 'barangay IS NULL';
    } elseif ($filters[$field] !== '') {
        $conditions[] = $column . '=?';
        $args[] = $filters[$field];
    }
}
if ($filters['date'] !== '') {
    $conditions[] = 'reported_at >= ? AND reported_at <= ?';
    $args[] = $filters['date'] . ' 00:00:00';
    $args[] = $filters['date'] . ' 23:59:59';
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$query = $pdo->prepare('SELECT COUNT(*) FROM incidents' . $where);
$query->execute($args);
$total = (int)$query->fetchColumn();
[$page, $offset, $pages] = pageWindow($total, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$query = $pdo->prepare('SELECT id,reported_at,incident_type,status,barangay FROM incidents' . $where . " ORDER BY reported_at DESC,id DESC LIMIT 10 OFFSET $offset");
$query->execute($args);
$pageTitle = $staff ? 'Incident reports' : 'My reports';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= $pageTitle ?></h1>
<?php if ($filterError): ?><p role="alert"><?= h($filterError) ?></p><?php endif; ?>
<a class="btn btn-primary mb-3" href="add.php"><?= $staff ? 'Record an incident' : 'Report an incident' ?></a>
<form method="get" class="row g-2 mb-3 filter-panel">
    <label class="col-md-3">Reference<input class="form-control" name="reference" value="<?= h($filters['reference']) ?>"></label>
    <label class="col-md-2">Reported date<input class="form-control" type="date" name="date" value="<?= h($filters['date']) ?>"></label>
    <?php foreach (['type'=>$types,'status'=>['Pending','Verified','Responding','Resolved','Rejected'],'barangay'=>array_merge(BARANGAYS, ['Awaiting confirmation'])] as $field=>$options): ?>
        <label class="col-md-2"><?= ucfirst($field) ?>
            <select class="form-select" name="<?= $field ?>">
                <option value="">All</option>
                <?php foreach ($options as $option): ?><option <?= $filters[$field] === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?>
            </select>
        </label>
    <?php endforeach; ?>
    <div><button class="btn btn-outline-secondary">Filter</button> <a class="btn btn-outline-secondary" href="index.php">Clear</a></div>
</form>
<p class="results-summary"><?= $total ?> matching reports · Page <?= $page ?> of <?= $pages ?></p>
<?php if (!$total): ?><p class="empty-state">No reports match these filters. Try changing or clearing the filters.</p><?php else: ?>
<div class="table-responsive"><table class="table">
    <thead><tr><th>Reference</th><th>Type</th><th>Barangay</th><th>Status</th><th>Reported</th></tr></thead>
    <tbody>
        <?php foreach ($query as $row): ?>
            <tr>
                <td><a href="view.php?id=<?= (int)$row['id'] ?>">ATOK-IR-<?= substr($row['reported_at'],0,4) ?>-<?= str_pad($row['id'],4,'0',STR_PAD_LEFT) ?></a></td>
                <td><?= h($row['incident_type']) ?></td><td><?= h($row['barangay'] ?? 'Awaiting confirmation') ?></td>
                <td><span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span></td><td><?= h($row['reported_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?<?= h(http_build_query($filters + ['page'=>$page-1])) ?>">Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?<?= h(http_build_query($filters + ['page'=>$page+1])) ?>">Next</a><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
