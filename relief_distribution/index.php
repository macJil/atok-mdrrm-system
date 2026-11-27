<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$areas = ['Abiang','Caliking','Cattubo','Naguey','Paoay','Pasdong','Poblacion','Topdac'];
$error = '';
$household = posted('household');
$point = posted('point');
$receiver = trim(posted('receiver'));
$quantities = $_POST['quantities'] ?? [];
$key = bin2hex(random_bytes(16));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (posted('action') === 'add_point') {
            $name = trim(posted('name'));
            $address = trim(posted('address'));
            $area = posted('barangay');
            if ($name === '' || mb_strlen($name) > 100 || $address === '' || mb_strlen($address) > 255 || !in_array($area,$areas,true)) {
                throw new DomainException('Enter a point name, barangay and written address.');
            }
            $query = $pdo->prepare('INSERT INTO relief_points(name,barangay,address) VALUES(?,?,?)');
            $query->execute([$name,$area,$address]);
            go('relief_distribution/index.php');
        }
        if (posted('action') !== 'distribute' || !ctype_xdigit(posted('submission_key')) || strlen(posted('submission_key')) !== 32) {
            throw new DomainException('Reopen the distribution form and try again.');
        }
        $key = posted('submission_key');
        if (!ctype_digit($household) || !ctype_digit($point) || $receiver === '' || mb_strlen($receiver) > 100) {
            throw new DomainException('Choose a household and relief point, and enter the actual receiver.');
        }
        $lines = [];
        if (is_array($quantities)) {
            foreach ($quantities as $good => $input) {
                if ($input === '' || $input === '0') {
                    continue;
                }
                $quantity = filter_var($input,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000000]]);
                if (!is_int($good) || $good < 1 || !$quantity) {
                    throw new DomainException('Use positive whole quantities for the goods being issued.');
                }
                $lines[$good] = $quantity;
            }
        }
        if (!$lines) {
            throw new DomainException('Enter a quantity for at least one good.');
        }
        ksort($lines,SORT_NUMERIC);
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT registry_reason FROM households WHERE id=? FOR UPDATE');
        $query->execute([$household]);
        if (!in_array($query->fetchColumn(),['Affected','Monitored and affected'],true)) {
            throw new DomainException('Select a registered affected household.');
        }
        $query = $pdo->prepare('INSERT INTO relief_distributions(household_id,point_id,receiver,staff_id,submission_key) VALUES(?,?,?,?,?)');
        $query->execute([$household,$point,$receiver,$user['id'],$key]);
        $distribution = $pdo->lastInsertId();
        $stock = $pdo->prepare('SELECT name,balance FROM relief_goods WHERE id=? FOR UPDATE');
        $deduct = $pdo->prepare('UPDATE relief_goods SET balance=balance-? WHERE id=?');
        $movement = $pdo->prepare("INSERT INTO stock_movements(good_id,direction,quantity,movement_date,reason,recorded_by) VALUES(?,'Stock-out',?,CURRENT_DATE,'Household relief distribution',?)");
        $item = $pdo->prepare('INSERT INTO distribution_items(distribution_id,good_id,quantity,movement_id) VALUES(?,?,?,?)');
        foreach ($lines as $good => $quantity) {
            $stock->execute([$good]);
            $row = $stock->fetch();
            if (!$row || $row['balance'] < $quantity) {
                throw new DomainException('Insufficient or unavailable stock: ' . ($row['name'] ?? 'unknown good') . '. No goods issued.');
            }
            $deduct->execute([$quantity,$good]);
            $movement->execute([$good,$quantity,$user['id']]);
            $item->execute([$distribution,$good,$quantity,$pdo->lastInsertId()]);
        }
        $pdo->commit();
        go('relief_distribution/index.php?receipt=' . $distribution);
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Could not save. Check the selected records and history for a duplicate.';
    }
}
$historySql = 'SELECT d.id,d.distributed_at,d.receiver,h.barangay,h.address,m.full_name AS representative,p.name AS point_name,a.full_name AS encoder
    FROM relief_distributions d JOIN households h ON h.id=d.household_id
    LEFT JOIN household_representatives r ON r.household_id=h.id LEFT JOIN household_members m ON m.id=r.member_id
    JOIN relief_points p ON p.id=d.point_id JOIN accounts a ON a.id=d.staff_id';
$receipt = null;
if (isset($_GET['receipt'])) {
    $query = $pdo->prepare($historySql . ' WHERE d.id=?');
    $query->execute([filter_input(INPUT_GET,'receipt',FILTER_VALIDATE_INT)]);
    $receipt = $query->fetch();
    if (!$receipt) {
        http_response_code(404);
        exit('Distribution not found.');
    }
}
$pageTitle = 'Relief distribution';
require __DIR__ . '/../includes/header.php';
?>
<h1>Relief distribution</h1>
<?php if ($receipt): ?>
    <h2 class="h4">Distribution receipt</h2>
    <p>Household: <?= htmlspecialchars(($receipt['representative'] ?? 'Representative not selected').' · '.$receipt['barangay'].' · '.$receipt['address']) ?></p>
    <p>Received by <?= htmlspecialchars($receipt['receiver']) ?> at <?= htmlspecialchars($receipt['point_name']) ?>.</p>
    <p>Recorded <?= htmlspecialchars($receipt['distributed_at'].' · '.$receipt['encoder']) ?></p>
    <ul>
        <?php
        $query = $pdo->prepare('SELECT g.name,g.unit,i.quantity FROM distribution_items i JOIN relief_goods g ON g.id=i.good_id WHERE i.distribution_id=? ORDER BY g.name,g.unit');
        $query->execute([$receipt['id']]);
        foreach ($query as $line): ?>
            <li><?= htmlspecialchars($line['name'].' · '.$line['quantity'].' '.$line['unit']) ?></li>
        <?php endforeach; ?>
    </ul>
    <a class="btn btn-outline-secondary" href="index.php">Return to distributions</a>
<?php else: ?>
    <?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <a href="#history" class="btn btn-outline-secondary">Distribution history</a>
    <a href="../relief_inventory/index.php" class="btn btn-outline-secondary">Municipal inventory</a>
    <details class="my-3" <?= posted('action') === 'add_point' ? 'open' : '' ?>>
        <summary>Add relief point</summary>
        <form method="post" class="col-md-7">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
            <label class="d-block">Point name<input class="form-control" name="name" required maxlength="100" value="<?= htmlspecialchars(posted('name')) ?>"></label>
            <label class="d-block">Barangay<select class="form-select" name="barangay" required><option value="">Choose barangay</option>
                <?php foreach ($areas as $area): ?><option <?= posted('barangay') === $area ? 'selected' : '' ?>><?= $area ?></option><?php endforeach; ?>
            </select></label>
            <label class="d-block mb-2">Address or landmark<input class="form-control" name="address" required maxlength="255" value="<?= htmlspecialchars(posted('address')) ?>"></label>
            <button class="btn btn-primary" name="action" value="add_point">Save relief point</button>
        </form>
    </details>
    <form method="post" class="col-lg-8">
        <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
        <input type="hidden" name="submission_key" value="<?= htmlspecialchars($key) ?>">
        <label class="d-block mb-2">Affected household<select class="form-select" name="household" required>
            <option value="">Choose household</option>
            <?php
            $query = $pdo->query("SELECT h.id,h.barangay,h.address,m.full_name FROM households h LEFT JOIN household_representatives r ON r.household_id=h.id
                LEFT JOIN household_members m ON m.id=r.member_id WHERE h.registry_reason IN ('Affected','Monitored and affected') ORDER BY h.barangay,m.full_name,h.address");
            foreach ($query as $row): ?>
                <option value="<?= (int)$row['id'] ?>" <?= $household === (string)$row['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($row['full_name'] ?? 'Representative not selected').' · '.$row['barangay'].' · '.$row['address']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="d-block mb-2">Actual receiving representative<input class="form-control" name="receiver" required maxlength="100" value="<?= htmlspecialchars($receiver) ?>"></label>
        <label class="d-block mb-3">Relief point<select class="form-select" name="point" required>
            <option value="">Choose relief point</option>
            <?php foreach ($pdo->query('SELECT * FROM relief_points ORDER BY barangay,name') as $row): ?>
                <option value="<?= (int)$row['id'] ?>" <?= $point === (string)$row['id'] ? 'selected' : '' ?>><?= htmlspecialchars($row['name'].' · '.$row['barangay'].' · '.$row['address']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <h2 class="h5">Goods to issue</h2>
        <?php foreach ($pdo->query('SELECT * FROM relief_goods WHERE balance>0 ORDER BY name,unit') as $row): ?>
            <label class="d-block mb-2"><?= htmlspecialchars($row['name'].' · '.$row['balance'].' '.$row['unit'].' available') ?>
                <input class="form-control" type="number" min="0" max="<?= (int)$row['balance'] ?>" name="quantities[<?= (int)$row['id'] ?>]" value="<?= htmlspecialchars(is_string($quantities[$row['id']] ?? null) ? $quantities[$row['id']] : '') ?>">
            </label>
        <?php endforeach; ?>
        <button class="btn btn-primary" name="action" value="distribute">Record distribution</button>
    </form>
    <h2 id="history" class="h4 mt-4">Distribution history</h2>
    <?php
    $total = (int)$pdo->query('SELECT COUNT(*) FROM relief_distributions')->fetchColumn();
    $pages = max(1,(int)ceil($total/10));
    $page = min($pages,max(1,(int)filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT)));
    $offset = ($page-1)*10;
    if (!$total): ?><p>No distributions recorded.</p><?php endif; ?>
    <?php foreach ($pdo->query($historySql . " ORDER BY d.id DESC LIMIT 10 OFFSET $offset") as $entry): ?>
        <p><?= htmlspecialchars($entry['distributed_at'].' · '.$entry['receiver'].' · '.$entry['point_name']) ?>
            <a class="btn btn-outline-secondary" href="?receipt=<?= (int)$entry['id'] ?>">View receipt</a>
        </p>
    <?php endforeach; ?>
    <?php if ($page>1): ?><a href="?page=<?= $page-1 ?>#history">Previous</a><?php endif; ?>
    <?php if ($page<$pages): ?><a href="?page=<?= $page+1 ?>#history">Next</a><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
