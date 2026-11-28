<?php
require __DIR__ . '/household_function.php';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$requestedPage = (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$from = ' FROM households h LEFT JOIN household_representatives r ON r.household_id=h.id
          LEFT JOIN household_members rep ON rep.id=r.member_id';
$conditions = [];
$args = [];
if ($search !== '') {
    $term = searchPattern($search);
    $conditions[] = "(h.barangay LIKE ? ESCAPE '!' OR h.address LIKE ? ESCAPE '!' OR EXISTS
        (SELECT 1 FROM household_members m WHERE m.household_id=h.id AND m.full_name LIKE ? ESCAPE '!'))";
    $args = [$term, $term, $term];
}
$affected = ($_GET['affected'] ?? '') === '1';
$area = is_string($_GET['barangay'] ?? null) ? $_GET['barangay'] : '';
if ($affected) {
    $conditions[] = "h.registry_reason IN ('Affected','Monitored and affected')";
}
if ($area !== '') {
    $conditions[] = 'h.barangay=?';
    $args[] = $area;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$query = $pdo->prepare('SELECT COUNT(*)' . $from . $where);
$query->execute($args);
$total = (int)$query->fetchColumn();
[$page, $offset, $pages] = pageWindow($total, $requestedPage);
$query = $pdo->prepare('SELECT h.id,h.barangay,h.address,rep.full_name,
    (SELECT COUNT(*) FROM household_members m WHERE m.household_id=h.id AND m.active=1) AS people'
    . $from . $where . " ORDER BY h.barangay,h.address,h.id LIMIT 10 OFFSET $offset");
$query->execute($args);
$pageTitle = 'Household registry';
require __DIR__ . '/../includes/header.php';
?>
<h1>Household registry</h1>
<p class="household-intro">Search the household records by member, barangay, or address. Open a record to review household details and manage members.</p>
<p><a class="btn btn-primary" href="form.php">Add household</a></p>
<form method="get" class="household-filter filter-panel">
    <input type="hidden" name="affected" value="<?= (int)$affected ?>">
    <input type="hidden" name="barangay" value="<?= h($area) ?>">
    <h2 class="h5">Find a household</h2>
    <?php if ($affected): ?><p class="household-filter-note">Affected households · <?= h($area ?: "All barangays") ?></p><?php endif; ?>
    <div class="household-filter-controls">
        <label>Search by person, barangay or address<input class="form-control" name="q" value="<?= h($search) ?>"></label>
        <button class="btn btn-primary">Search</button>
        <a class="btn btn-outline-secondary" href="index.php">Clear search</a>
    </div>
</form>
<p class="household-results-summary" role="status"><strong><?= $total ?></strong> matching households <span>Page <?= $page ?> of <?= $pages ?></span></p>
<?php if (!$total): ?><p class="empty-state">No households match this search. Try another name, barangay, or address.</p><?php else: ?>
<div class="table-responsive">
    <table class="table household-table">
        <thead><tr><th>Representative</th><th>Barangay</th><th>Address / landmark</th><th>Active members</th><th>Record</th></tr></thead>
        <tbody>
            <?php foreach ($query as $row): ?>
                <tr>
                    <td><?= h($row['full_name'] ?? 'Representative not selected') ?></td>
                    <td><?= h($row['barangay']) ?></td><td><?= h($row['address']) ?></td><td><?= (int)$row['people'] ?></td>
                    <td><a class="btn btn-outline-secondary" href="view.php?id=<?= (int)$row['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<nav class="household-pagination" aria-label="Household pages">
    <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;affected=<?= (int)$affected ?>&amp;barangay=<?= urlencode($area) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;affected=<?= (int)$affected ?>&amp;barangay=<?= urlencode($area) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
</nav>
<?php require __DIR__ . '/../includes/footer.php'; ?>
