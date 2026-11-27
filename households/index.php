<?php
require __DIR__ . '/household_function.php';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$page = max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$from = ' FROM households h LEFT JOIN household_representatives r ON r.household_id=h.id
          LEFT JOIN household_members rep ON rep.id=r.member_id';
$where = '';
$args = [];
if ($search !== '') {
    $term = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where = " WHERE h.barangay LIKE ? ESCAPE '!' OR h.address LIKE ? ESCAPE '!' OR EXISTS
              (SELECT 1 FROM household_members m WHERE m.household_id=h.id AND m.full_name LIKE ? ESCAPE '!')";
    $args = [$term, $term, $term];
}
$query = $pdo->prepare('SELECT COUNT(*)' . $from . $where);
$query->execute($args);
$total = (int)$query->fetchColumn();
$pages = max(1, (int)ceil($total / 10));
$page = min($page, $pages);
$offset = ($page - 1) * 10;
$query = $pdo->prepare('SELECT h.id,h.barangay,h.address,rep.full_name,
    (SELECT COUNT(*) FROM household_members m WHERE m.household_id=h.id AND m.active=1) AS people'
    . $from . $where . " ORDER BY h.barangay,h.address,h.id LIMIT 10 OFFSET $offset");
$query->execute($args);
$pageTitle = 'Household registry';
require __DIR__ . '/../includes/header.php';
?>
<h1>Household registry</h1>
<p><a class="btn btn-primary" href="form.php">Add household</a></p>
<form method="get" class="mb-3">
    <label>Search by person, barangay or address<input class="form-control" name="q" value="<?= h($search) ?>"></label>
    <button class="btn btn-outline-secondary">Search</button>
    <a class="btn btn-outline-secondary" href="index.php">Clear</a>
</form>
<p><?= $total ?> matching households · Page <?= $page ?> of <?= $pages ?></p>
<div class="table-responsive">
    <table class="table">
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
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
