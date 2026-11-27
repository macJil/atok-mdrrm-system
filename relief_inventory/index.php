<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$editing = isset($_GET['id']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$good = ['name'=>'', 'unit'=>'', 'minimum_stock'=>0];
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
    if ($good['name'] === '' || mb_strlen($good['name']) > 100 || $good['unit'] === '' || mb_strlen($good['unit']) > 40 || $minimum === false) {
        $error = 'Enter a name, a unit and a whole-number minimum from 0 to 1,000,000,000.';
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
                $query = $pdo->prepare('INSERT INTO relief_goods(name,unit,minimum_stock) VALUES(?,?,?)');
                $query->execute([$good['name'],$good['unit'],$minimum]);
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
<a class="btn btn-primary" href="movements.php">Record or find a stock movement</a>
<?php if (!$editing):
    $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $term = '%' . strtr($search, ['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
    $where = " WHERE name LIKE ? ESCAPE '!' OR unit LIKE ? ESCAPE '!'";
    $query = $pdo->prepare('SELECT COUNT(*) FROM relief_goods' . $where);
    $query->execute([$term,$term]);
    $pages = max(1,(int)ceil($query->fetchColumn()/10));
    $page = min($pages,max(1,(int)filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT)));
    $offset = ($page-1)*10;
    $query = $pdo->prepare('SELECT * FROM relief_goods' . $where . " ORDER BY name,unit,id LIMIT 10 OFFSET $offset");
    $query->execute([$term,$term]);
?>
    <a class="btn btn-outline-primary" href="#good-form">Add good</a>
    <form method="get" class="my-3">
        <label>Search name or unit<input class="form-control" name="q" value="<?= htmlspecialchars($search) ?>"></label>
        <button class="btn btn-outline-secondary">Search</button>
    </form>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Good</th><th>Unit</th><th>Available</th><th>Minimum</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($query as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td><td><?= htmlspecialchars($row['unit']) ?></td>
                    <td>
                        <?php if ((int)$row['balance'] === 0): ?>
                            <strong class="text-danger">Out of stock</strong>
                        <?php else: ?>
                            <?= (int)$row['balance'] ?>
                            <?php if ($row['balance'] <= $row['minimum_stock']): ?><strong>Low stock</strong><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$row['minimum_stock'] ?></td>
                    <td><a class="btn btn-outline-secondary" href="?id=<?= (int)$row['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <p>Page <?= $page ?> of <?= $pages ?>
        <?php if ($page > 1): ?><a href="?q=<?= urlencode($search) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
        <?php if ($page < $pages): ?><a href="?q=<?= urlencode($search) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
    </p>
<?php endif; ?>
<h2 id="good-form"><?= $editing ? 'Edit good' : 'Add good' ?></h2>
<?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="col-md-7">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block mb-2">Good name<input class="form-control" name="name" required maxlength="100" value="<?= htmlspecialchars($good['name']) ?>"></label>
    <label class="d-block mb-2">Unit (e.g. packs)<input class="form-control" name="unit" required maxlength="40" value="<?= htmlspecialchars($good['unit']) ?>"></label>
    <label class="d-block mb-3">Minimum stock<input class="form-control" type="number" min="0" max="1000000000" name="minimum_stock" required value="<?= htmlspecialchars($good['minimum_stock']) ?>"></label>
    <button class="btn btn-primary">Save good</button>
    <?php if ($editing): ?><a class="btn btn-outline-secondary" href="index.php">Return to inventory</a><?php endif; ?>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
