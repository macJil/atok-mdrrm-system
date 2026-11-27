<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$today = $pdo->query('SELECT CURRENT_DATE')->fetchColumn();
$date = $today;
$direction = posted('direction');
$reason = trim(posted('reason'));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = posted('movement_date');
    $parts = explode('-', $date);
    $quantity = filter_var(posted('quantity'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000000]]);
    $goodId = filter_var(posted('good'), FILTER_VALIDATE_INT);
    if (!$quantity || !$goodId || !in_array($direction,['Stock-in','Stock-out'],true)) {
        $error = 'Choose a good, movement type and positive whole quantity.';
    } elseif (count($parts) !== 3 || !ctype_digit(implode('', $parts)) || !checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) || strlen($date) !== 10 || $date < '1000-01-01' || $date > $today) {
        $error = 'Enter a valid movement date on or before today.';
    } elseif ($reason === '' || mb_strlen($reason) > 255) {
        $error = 'Enter a reason of up to 255 characters.';
    } else {
        try {
            $pdo->beginTransaction();
            $query = $pdo->prepare('SELECT balance FROM relief_goods WHERE id=? FOR UPDATE');
            $query->execute([$goodId]);
            $balance = $query->fetchColumn();
            if ($balance === false) {
                throw new DomainException('Good not found.');
            }
            if ($direction === 'Stock-out') {
                if ($quantity > $balance) {
                    throw new DomainException('Insufficient stock. No movement was recorded.');
                }
                $balance -= $quantity;
            } else {
                $balance += $quantity;
            }
            $query = $pdo->prepare('UPDATE relief_goods SET balance=? WHERE id=?');
            $query->execute([$balance,$goodId]);
            $query = $pdo->prepare('INSERT INTO stock_movements(good_id,direction,quantity,movement_date,reason,recorded_by) VALUES(?,?,?,?,?,?)');
            $query->execute([$goodId,$direction,$quantity,$date,$reason,$user['id']]);
            $pdo->commit();
            $_SESSION['inventory_notice'] = 'Movement recorded. Available stock: ' . $balance . '.';
            go('relief_inventory/movements.php');
        } catch (DomainException | PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception instanceof DomainException ? $exception->getMessage() : 'Could not record this movement. Check the quantity and retry.';
        }
    }
}
$pageTitle = 'Stock movements';
require __DIR__ . '/../includes/header.php';
?>
<h1>Stock movements</h1>
<a class="btn btn-outline-secondary" href="index.php">Return to municipal inventory</a>
<a class="btn btn-outline-primary" href="#history">Find a movement</a>
<?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (isset($_SESSION['inventory_notice'])): ?><p role="status"><?= htmlspecialchars($_SESSION['inventory_notice']) ?></p><?php unset($_SESSION['inventory_notice']); endif; ?>
<form method="post" class="col-md-7 my-3">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block mb-2">Good and unit<select class="form-select" name="good" required>
        <option value="">Select good</option>
        <?php foreach ($pdo->query('SELECT id,name,unit,balance FROM relief_goods ORDER BY name,unit') as $row): ?>
            <option value="<?= (int)$row['id'] ?>" <?= posted('good') === (string)$row['id'] ? 'selected' : '' ?>><?= htmlspecialchars($row['name'].' · '.$row['unit'].' · '.$row['balance'].' available') ?></option>
        <?php endforeach; ?>
    </select></label>
    <label class="d-block mb-2">Movement<select class="form-select" name="direction" required>
        <option value="">Choose type</option><option <?= $direction === 'Stock-in' ? 'selected' : '' ?>>Stock-in</option><option <?= $direction === 'Stock-out' ? 'selected' : '' ?>>Stock-out</option>
    </select></label>
    <label class="d-block mb-2">Quantity<input class="form-control" type="number" min="1" max="1000000000" required name="quantity" value="<?= htmlspecialchars(posted('quantity')) ?>"></label>
    <label class="d-block mb-2">Movement date<input class="form-control" type="date" required name="movement_date" max="<?= $today ?>" value="<?= htmlspecialchars($date) ?>"></label>
    <label class="d-block mb-3">Reason<input class="form-control" name="reason" maxlength="255" required value="<?= htmlspecialchars($reason) ?>"></label>
    <button class="btn btn-primary">Record movement</button>
</form>
<h2 id="history">Movement history</h2>
<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = '%' . strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
$from = " FROM stock_movements m JOIN relief_goods g ON g.id=m.good_id JOIN accounts a ON a.id=m.recorded_by
    WHERE g.name LIKE ? ESCAPE '!' OR m.reason LIKE ? ESCAPE '!' OR a.full_name LIKE ? ESCAPE '!' OR m.direction LIKE ? ESCAPE '!'";
$args = [$term,$term,$term,$term];
$query = $pdo->prepare('SELECT COUNT(*)' . $from);
$query->execute($args);
$pages = max(1,(int)ceil($query->fetchColumn()/10));
$page = min($pages,max(1,(int)filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT)));
$offset = ($page-1)*10;
$query = $pdo->prepare('SELECT m.*,g.name,g.unit,a.full_name' . $from . " ORDER BY m.movement_date DESC,m.id DESC LIMIT 10 OFFSET $offset");
$query->execute($args);
?>
<form method="get" action="#history" class="mb-2">
    <label>Find by good, reason, type or staff<input class="form-control" name="q" value="<?= htmlspecialchars($search) ?>"></label>
    <button class="btn btn-outline-secondary">Search history</button>
</form>
<div class="table-responsive"><table class="table">
    <thead><tr><th>Date</th><th>Good</th><th>Movement</th><th>Quantity</th><th>Reason</th><th>Recorded by</th></tr></thead>
    <tbody>
        <?php foreach ($query as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['movement_date']) ?></td><td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['direction']) ?></td><td><?= htmlspecialchars($row['quantity'].' '.$row['unit']) ?></td>
                <td><?= htmlspecialchars($row['reason']) ?></td><td><?= htmlspecialchars($row['full_name']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table></div>
<p>Page <?= $page ?> of <?= $pages ?>
    <?php if ($page > 1): ?><a href="?q=<?= urlencode($search) ?>&amp;page=<?= $page-1 ?>#history">Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?><a href="?q=<?= urlencode($search) ?>&amp;page=<?= $page+1 ?>#history">Next</a><?php endif; ?>
</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
