<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('staff');
$editing = isset($_GET['id']);
$adding = ($_GET['action'] ?? '') === 'add';
$managing = $editing || $adding;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$good = ['name'=>'', 'unit'=>'', 'minimum_stock'=>0, 'opening_quantity'=>0];
if ($editing) {
    $query = $pdo->prepare('SELECT * FROM relief_goods WHERE id=?');
    $query->execute([$id]);
    $good = $query->fetch();
    if (!$good) {
        http_response_code(404);
        exit('Good not found.');
    }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $good['name'] = trim(posted('name'));
    $good['unit'] = trim(posted('unit'));
    $good['minimum_stock'] = trim(posted('minimum_stock'));
    $minimum = filter_var($good['minimum_stock'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>1000000000]]);
    $opening = $editing ? 0 : filter_var(posted('opening_quantity'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>1000000000]]);
    $good['opening_quantity'] = $editing ? 0 : posted('opening_quantity');
    if ($good['name'] === '' || mb_strlen($good['name']) > 100 || $good['unit'] === '' || mb_strlen($good['unit']) > 40 || $minimum === false || $opening === false) {
        $error = 'Enter a name and unit, plus whole-number stock quantities from 0 to 1,000,000,000.';
    } else {
        try {
            if ($editing) {
                $pdo->beginTransaction();
                $query = $pdo->prepare('SELECT unit FROM relief_goods WHERE id=? FOR UPDATE');
                $query->execute([$id]);
                $oldUnit = $query->fetchColumn();
                if ($oldUnit !== $good['unit']) {
                    $query = $pdo->prepare('SELECT id FROM stock_movements WHERE good_id=? LIMIT 1');
                    $query->execute([$id]);
                    if ($query->fetchColumn()) {
                        throw new DomainException('This unit has recorded movements. Register a separate good for the new unit.');
                    }
                }
                $query = $pdo->prepare('UPDATE relief_goods SET name=?,unit=?,minimum_stock=? WHERE id=?');
                $query->execute([$good['name'],$good['unit'],$minimum,$id]);
                $pdo->commit();
            } else {
                $pdo->beginTransaction();
                $query = $pdo->prepare('INSERT INTO relief_goods(name,unit,minimum_stock,balance) VALUES(?,?,?,?)');
                $query->execute([$good['name'],$good['unit'],$minimum,$opening]);
                if ($opening > 0) {
                    $query = $pdo->prepare("INSERT INTO stock_movements(good_id,direction,quantity,movement_date,reason,recorded_by)
                        VALUES(?,'Stock-in',?,CURRENT_DATE,'Opening stock',?)");
                    $query->execute([$pdo->lastInsertId(),$opening,$user['id']]);
                }
                $pdo->commit();
            }
            go('relief_inventory/index.php');
        } catch (DomainException | PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception instanceof DomainException ? $exception->getMessage() : 'Could not save. Check whether this name and unit already exist.';
        }
    }
}
$pageTitle = 'Municipal relief inventory';
require __DIR__ . '/../includes/header.php';
?>
<h1>Municipal relief inventory</h1>
<p class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="?action=add">Add good</a>
    <a class="btn btn-outline-primary" href="movements.php?action=add">Record stock movement</a>
    <a class="btn btn-outline-primary" href="../relief_distribution.php?action=distribute">Distribute goods</a>
    <a class="btn btn-outline-secondary" href="movements.php">Movement history</a>
</p>
<?php if (!$managing):
    $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $term = searchPattern($search);
    $where = " WHERE (name LIKE ? ESCAPE '!' OR unit LIKE ? ESCAPE '!')";
    $attention = ($_GET['attention'] ?? '') === '1';
    if ($attention) {
        $where .= ' AND balance <= minimum_stock';
    }
    $query = $pdo->prepare('SELECT COUNT(*) FROM relief_goods' . $where);
    $query->execute([$term,$term]);
    $total = (int)$query->fetchColumn();
    [$page, $offset, $pages] = pageWindow($total, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
    $query = $pdo->prepare('SELECT * FROM relief_goods' . $where . " ORDER BY name,unit,id LIMIT 10 OFFSET $offset");
    $query->execute([$term,$term]);
?>
    <form method="get" class="my-3 filter-panel">
        <?php if ($attention): ?><input type="hidden" name="attention" value="1"><p>Out of stock or at minimum stock. <a href="index.php">Show all goods</a></p><?php endif; ?>
        <label>Search name or unit<input class="form-control" name="q" value="<?= h($search) ?>"></label>
        <button class="btn btn-outline-secondary">Search</button>
    </form>
    <?php if ($attention): ?><p class="results-summary"><?= $total ?> goods needing attention</p><?php else: ?><p class="results-summary"><?= $total ?> goods in inventory</p><?php endif; ?>
    <?php if (!$total): ?><p class="empty-state">No goods match this search and stock filter.</p><?php else: ?>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Good</th><th>Unit</th><th>Available</th><th>Minimum</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($query as $row): ?>
                <tr>
                    <td><?= h($row['name']) ?></td><td><?= h($row['unit']) ?></td>
                    <td>
                        <?php if ((int)$row['balance'] === 0): ?>
                            <span class="status-badge status-out">Out of stock</span>
                        <?php else: ?>
                            <?= (int)$row['balance'] ?>
                            <?php if ($row['balance'] <= $row['minimum_stock']): ?><span class="status-badge status-low stock-badge">Low stock</span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$row['minimum_stock'] ?></td>
                    <td><a class="btn btn-outline-secondary" href="?id=<?= (int)$row['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <p>Page <?= $page ?> of <?= $pages ?>
        <?php if ($page > 1): ?><a href="?q=<?= urlencode($search) ?>&amp;attention=<?= (int)$attention ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
        <?php if ($page < $pages): ?><a href="?q=<?= urlencode($search) ?>&amp;attention=<?= (int)$attention ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
    </p>
<?php endif; ?>
<?php if ($managing): ?>
<h2 id="good-form"><?= $editing ? 'Edit good' : 'Add good' ?></h2>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="col-md-7 form-panel workflow-form">
    <?= csrfField() ?>
    <label class="d-block mb-2">Good name<input class="form-control" name="name" required maxlength="100" value="<?= h($good['name']) ?>"></label>
    <label class="d-block mb-2">Unit (e.g. packs)<input class="form-control" name="unit" required maxlength="40" value="<?= h($good['unit']) ?>"></label>
    <label class="d-block mb-3">Minimum stock<input class="form-control" type="number" min="0" max="1000000000" name="minimum_stock" required value="<?= h($good['minimum_stock']) ?>"></label>
    <?php if (!$editing): ?>
        <label class="d-block mb-3">Opening stock quantity<input class="form-control" type="number" min="0" max="1000000000" name="opening_quantity" required value="<?= h($good['opening_quantity']) ?>"></label>
        <p>Positive opening stock is saved as an initial stock-in movement. Use 0 when the good starts without stock.</p>
    <?php endif; ?>
    <button class="btn btn-primary">Save good</button>
    <a class="btn btn-outline-secondary" href="index.php">Cancel</a>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
