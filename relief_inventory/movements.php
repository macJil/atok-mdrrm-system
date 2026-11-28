<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('staff');
$today = $pdo->query('SELECT CURRENT_DATE')->fetchColumn();
$adding = ($_GET['action'] ?? '') === 'add';
$correcting = ($_GET['action'] ?? '') === 'correct';
$movementId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$sourceMovement = null;
if ($correcting) {
    $query = $pdo->prepare("SELECT m.*,g.name,g.unit,
            EXISTS(SELECT 1 FROM stock_movements c WHERE c.corrects_movement_id=m.id) AS corrected,
            EXISTS(SELECT 1 FROM distribution_items i WHERE i.movement_id=m.id) AS distributed
        FROM stock_movements m JOIN relief_goods g ON g.id=m.good_id WHERE m.id=?");
    $query->execute([$movementId]);
    $sourceMovement = $query->fetch();
    if (!$sourceMovement) {
        http_response_code(404);
        exit('Movement not found.');
    }
}
$date = $today;
$direction = posted('direction');
$reason = trim(posted('reason'));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = posted('movement_date');
    $parts = explode('-', $date);
    $quantity = filter_var(posted('quantity'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000000]]);
    $goodId = filter_var(posted('good'), FILTER_VALIDATE_INT);
    if ($correcting) {
        $reason = trim(posted('reason'));
        if (!$movementId || $reason === '' || mb_strlen($reason) > 200) {
            $error = 'Enter a correction reason of up to 200 characters.';
        } else {
            try {
                $pdo->beginTransaction();
                $query = $pdo->prepare('SELECT m.*,g.balance FROM stock_movements m
                    JOIN relief_goods g ON g.id=m.good_id WHERE m.id=? FOR UPDATE');
                $query->execute([$movementId]);
                $source = $query->fetch();
                if (!$source) {
                    throw new DomainException('Movement not found.');
                }
                $query = $pdo->prepare('SELECT id FROM stock_movements WHERE corrects_movement_id=? FOR UPDATE');
                $query->execute([$movementId]);
                if ($query->fetchColumn()) {
                    throw new DomainException('This movement already has a correction. Correct the later movement instead.');
                }
                $query = $pdo->prepare('SELECT movement_id FROM distribution_items WHERE movement_id=?');
                $query->execute([$movementId]);
                if ($query->fetchColumn()) {
                    throw new DomainException('Distribution-linked stock-out movements cannot be corrected here. Keep the receipt unchanged and record a separate documented stock adjustment if needed.');
                }
                $query = $pdo->prepare('SELECT balance FROM relief_goods WHERE id=? FOR UPDATE');
                $query->execute([$source['good_id']]);
                $balance = (int)$query->fetchColumn();
                $reverseDirection = $source['direction'] === 'Stock-in' ? 'Stock-out' : 'Stock-in';
                if ($reverseDirection === 'Stock-out' && (int)$source['quantity'] > $balance) {
                    throw new DomainException('The current balance is too low to reverse this stock-in. Record a documented adjustment after reviewing the ledger.');
                }
                $newBalance = $reverseDirection === 'Stock-in'
                    ? $balance + (int)$source['quantity']
                    : $balance - (int)$source['quantity'];
                if ($newBalance > 1000000000) {
                    throw new DomainException('The correction would exceed the supported inventory limit.');
                }
                $query = $pdo->prepare('UPDATE relief_goods SET balance=? WHERE id=?');
                $query->execute([$newBalance,$source['good_id']]);
                $query = $pdo->prepare('INSERT INTO stock_movements(good_id,direction,quantity,movement_date,reason,recorded_by,corrects_movement_id)
                    VALUES(?,?,?,?,?,?,?)');
                $query->execute([$source['good_id'],$reverseDirection,$source['quantity'],$today,
                    'Correction of movement #' . $movementId . ': ' . $reason,$user['id'],$movementId]);
                $pdo->commit();
                $_SESSION['inventory_notice'] = 'Correction recorded as a new movement. Available stock: ' . $newBalance . '.';
                go('relief_inventory/movements.php');
            } catch (DomainException | PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $exception instanceof DomainException ? $exception->getMessage() : 'Could not record this correction. No movement was changed.';
            }
        }
    } elseif (!$quantity || !$goodId || !in_array($direction,['Stock-in','Stock-out'],true)) {
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
            if ($balance > 1000000000) {
                throw new DomainException('The movement would exceed the supported inventory limit.');
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
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if (isset($_SESSION['inventory_notice'])): ?><p role="status"><?= h($_SESSION['inventory_notice']) ?></p><?php unset($_SESSION['inventory_notice']); endif; ?>
<?php if ($correcting): ?>
<section class="form-panel col-md-8 my-3">
    <h2 class="h4">Correct movement #<?= (int)$sourceMovement['id'] ?></h2>
    <p><?= h($sourceMovement['movement_date'].' · '.$sourceMovement['name'].' · '.$sourceMovement['direction'].' · '.$sourceMovement['quantity'].' '.$sourceMovement['unit']) ?></p>
    <p>The original remains unchanged. This creates one full, opposite movement. Corrections of distribution-linked stock-outs are not allowed.</p>
    <?php if ($sourceMovement['corrected']): ?><p role="status">This movement already has a correction.</p>
    <?php elseif ($sourceMovement['distributed']): ?><p role="status">This movement is linked to a distribution receipt and cannot be reversed here.</p>
    <?php else: ?>
        <form method="post" action="?action=correct&amp;id=<?= (int)$movementId ?>" class="workflow-form">
            <?= csrfField() ?>
            <label class="d-block mb-3">Reason for correction<input class="form-control" name="reason" maxlength="200" required value="<?= h(posted('reason')) ?>"></label>
            <button class="btn btn-primary">Record compensating movement</button>
            <a class="btn btn-outline-secondary" href="movements.php">Cancel</a>
        </form>
    <?php endif; ?>
</section>
<?php elseif ($adding): ?>
<form method="post" action="?action=add" class="col-md-8 my-3 form-panel workflow-form">
    <?= csrfField() ?>
    <label class="d-block mb-2">Good and unit<select class="form-select" name="good" required>
        <option value="">Select good</option>
        <?php foreach ($pdo->query('SELECT id,name,unit,balance FROM relief_goods ORDER BY name,unit') as $row): ?>
            <option value="<?= (int)$row['id'] ?>" <?= posted('good') === (string)$row['id'] ? 'selected' : '' ?>><?= h($row['name'].' · '.$row['unit'].' · '.$row['balance'].' available') ?></option>
        <?php endforeach; ?>
    </select></label>
    <label class="d-block mb-2">Movement<select class="form-select" name="direction" required>
        <option value="">Choose type</option><option <?= $direction === 'Stock-in' ? 'selected' : '' ?>>Stock-in</option><option <?= $direction === 'Stock-out' ? 'selected' : '' ?>>Stock-out</option>
    </select></label>
    <label class="d-block mb-2">Quantity<input class="form-control" type="number" min="1" max="1000000000" required name="quantity" value="<?= h(posted('quantity')) ?>"></label>
    <label class="d-block mb-2">Movement date<input class="form-control" type="date" required name="movement_date" max="<?= $today ?>" value="<?= h($date) ?>"></label>
    <label class="d-block mb-3">Reason<input class="form-control" name="reason" maxlength="255" required value="<?= h($reason) ?>"></label>
    <button class="btn btn-primary">Record movement</button>
    <a class="btn btn-outline-secondary" href="movements.php">Cancel</a>
</form>
<?php else: ?>
<p><a class="btn btn-primary" href="?action=add">Record stock movement</a></p>
<h2 id="history">Movement history</h2>
<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = searchPattern($search);
$from = " FROM stock_movements m JOIN relief_goods g ON g.id=m.good_id JOIN accounts a ON a.id=m.recorded_by
    WHERE g.name LIKE ? ESCAPE '!' OR m.reason LIKE ? ESCAPE '!' OR a.full_name LIKE ? ESCAPE '!' OR m.direction LIKE ? ESCAPE '!'";
$args = [$term,$term,$term,$term];
$query = $pdo->prepare('SELECT COUNT(*)' . $from);
$query->execute($args);
[$page, $offset, $pages] = pageWindow(
    (int)$query->fetchColumn(),
    (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)
);
$query = $pdo->prepare('SELECT m.*,g.name,g.unit,a.full_name,
        EXISTS(SELECT 1 FROM stock_movements c WHERE c.corrects_movement_id=m.id) AS corrected,
        EXISTS(SELECT 1 FROM distribution_items i WHERE i.movement_id=m.id) AS distributed' . $from . " ORDER BY m.movement_date DESC,m.id DESC LIMIT 10 OFFSET $offset");
$query->execute($args);
?>
<form method="get" action="#history" class="mb-2">
    <label>Find by good, reason, type or staff<input class="form-control" name="q" value="<?= h($search) ?>"></label>
    <button class="btn btn-outline-secondary">Search history</button>
</form>
<div class="table-responsive"><table class="table log-table">
    <thead><tr><th>Date</th><th>Good</th><th>Movement</th><th>Quantity</th><th>Reason</th><th>Recorded by</th><th>Action</th></tr></thead>
    <tbody>
        <?php foreach ($query as $row): ?>
            <tr>
                <td><?= h($row['movement_date']) ?></td><td><?= h($row['name']) ?></td>
                <td><?= h($row['direction']) ?></td><td><?= h($row['quantity'].' '.$row['unit']) ?></td>
                <td><?= h($row['reason']) ?></td><td><?= h($row['full_name']) ?></td>
                <td><?php if ($row['corrected']): ?>Corrected<?php elseif ($row['distributed']): ?>Receipt-linked<?php else: ?><a class="btn btn-outline-secondary" href="?action=correct&amp;id=<?= (int)$row['id'] ?>">Correct</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table></div>
<p>Page <?= $page ?> of <?= $pages ?>
    <?php if ($page > 1): ?><a href="?q=<?= urlencode($search) ?>&amp;page=<?= $page-1 ?>#history">Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?><a href="?q=<?= urlencode($search) ?>&amp;page=<?= $page+1 ?>#history">Next</a><?php endif; ?>
</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
