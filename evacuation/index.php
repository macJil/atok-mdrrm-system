<?php
require __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../map/boundary.php';
$user = requireRole('staff');
$editing = isset($_GET['id']);
$adding = ($_GET['action'] ?? '') === 'add';
$deleting = ($_GET['action'] ?? '') === 'delete' && $editing;
$managing = $editing || $adding;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$form = ['name'=>'', 'barangay'=>'', 'address'=>'', 'capacity'=>'', 'status'=>'Open'];
if ($editing) {
    $query = $pdo->prepare('SELECT * FROM evacuation_centers WHERE id=?');
    $query->execute([$id]);
    $form = $query->fetch();
    if (!$form) {
        http_response_code(404);
        exit('Center not found.');
    }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        if ($editing) {
            $query = $pdo->prepare('SELECT id FROM evacuation_centers WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            if (!$query->fetchColumn()) {
                throw new DomainException('Center no longer exists.');
            }
        }
        if ($deleting && posted('action') === 'delete' && posted('confirm') === 'yes') {
            $query = $pdo->prepare('DELETE FROM evacuation_centers WHERE id=?');
            $query->execute([$id]);
        } elseif (posted('action') === 'save') {
            foreach (['name','barangay','address','capacity','status'] as $key) {
                $form[$key] = trim(posted($key));
            }
            $capacity = filter_var($form['capacity'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000]]);
            if ($form['name'] === '' || mb_strlen($form['name']) > 100 || $form['address'] === '' || mb_strlen($form['address']) > 255 ||
                !in_array($form['barangay'],BARANGAYS,true) || !in_array($form['status'],['Open','Closed'],true) || !$capacity) {
                throw new DomainException('Enter a name, barangay, address, valid status and a capacity of 1–1,000,000 people.');
            }
            $lat = trim(posted('latitude'));
            $lng = trim(posted('longitude'));
            $problems = locationErrors($form['address'], $lat, $lng);
            if ($problems) {
                throw new DomainException($problems[0]);
            }
            $lat = $lat === '' ? null : $lat;
            $lng = $lng === '' ? null : $lng;
            $args = [$form['name'],$form['barangay'],$form['address'],$capacity,$form['status'],$lat,$lng];
            if ($editing) {
                $query = $pdo->prepare('SELECT COUNT(*) FROM evacuation_stays WHERE center_id=? AND left_at IS NULL');
                $query->execute([$id]);
                $occupancy = (int)$query->fetchColumn();
                if ($occupancy > $capacity || ($occupancy > 0 && $form['status'] === 'Closed')) {
                    throw new DomainException('Check people out before closing the center or reducing capacity below current occupancy.');
                }
                $query = $pdo->prepare('UPDATE evacuation_centers SET name=?,barangay=?,address=?,capacity=?,status=?,latitude=?,longitude=? WHERE id=?');
                $args[] = $id;
            } else {
                $query = $pdo->prepare('INSERT INTO evacuation_centers(name,barangay,address,capacity,status,latitude,longitude) VALUES(?,?,?,?,?,?,?)');
            }
            $query->execute($args);
        } else {
            throw new DomainException('Confirm deletion or choose Save.');
        }
        $pdo->commit();
        go('evacuation/index.php');
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Change failed. Center names must be unique within a barangay. Centers with stay history must be retained.';
    }
}
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = searchPattern($search);
$where = " WHERE (name LIKE ? ESCAPE '!' OR barangay LIKE ? ESCAPE '!' OR address LIKE ? ESCAPE '!' )";
$args = [$term, $term, $term];
$area = in_array($_GET['barangay'] ?? '', BARANGAYS, true) ? $_GET['barangay'] : '';
$open = ($_GET['status'] ?? '') === 'Open';
if ($open) {
    $where .= " AND status='Open'";
}
if ($area !== '') {
    $where .= ' AND barangay=?';
    $args[] = $area;
}

$query = $pdo->prepare('SELECT COUNT(*) FROM evacuation_centers' . $where);
$query->execute($args);
$total = (int)$query->fetchColumn();
[$page, $offset, $pages] = pageWindow($total, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$query = $pdo->prepare('SELECT c.*, (SELECT COUNT(*) FROM evacuation_stays s WHERE s.center_id=c.id AND left_at IS NULL) AS staying FROM evacuation_centers c' . $where . " ORDER BY name,id LIMIT 10 OFFSET $offset");
$query->execute($args);
$pageTitle = 'Evacuation centers';
require __DIR__ . '/../includes/header.php';
?>
<h1>Evacuation centers</h1>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if (!$managing): ?>
    <a class="btn btn-primary" href="?action=add">Add center</a>
    <form method="get" class="my-3 filter-panel">
        <input type="hidden" name="barangay" value="<?= h($area) ?>"><input type="hidden" name="status" value="<?= $open ? 'Open' : '' ?>">
        <?php if ($open || $area): ?><p><?= $open ? 'Open centers' : 'Centers' ?> · <?= h($area ?: 'All barangays') ?> <a href="index.php">Clear filters</a></p><?php endif; ?>
        <label>Search name, address or barangay<input class="form-control" name="q" value="<?= h($search) ?>"></label>
        <button class="btn btn-outline-secondary">Search</button>
    </form>
    <?php if ($total === 0): ?><p class="empty-state">No evacuation centers match this search.</p><?php else: ?>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Center</th><th>Barangay</th><th>Status</th><th>Staying / capacity</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($query as $row): ?>
                <tr>
                    <td><?= h($row['name']) ?></td><td><?= h($row['barangay']) ?></td>
                    <td><span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span></td><td><?= (int)$row['staying'] ?> / <?= (int)$row['capacity'] ?></td>
                    <td><a class="btn btn-outline-primary" href="view.php?id=<?= (int)$row['id'] ?>">Manage stays</a>
                        <a class="btn btn-outline-secondary" href="?id=<?= (int)$row['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <p>Page <?= $page ?> of <?= $pages ?>
        <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&amp;status=<?= $open ? 'Open' : '' ?>&amp;barangay=<?= urlencode($area) ?>&amp;q=<?= urlencode($search) ?>">Previous</a><?php endif; ?>
        <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>&amp;status=<?= $open ? 'Open' : '' ?>&amp;barangay=<?= urlencode($area) ?>&amp;q=<?= urlencode($search) ?>">Next</a><?php endif; ?>
    </p>
<?php elseif ($deleting): ?>
    <section class="form-panel col-md-8">
        <h2>Confirm center deletion</h2>
        <p>Delete <strong><?= h($form['name']) ?></strong>? Centers with stay history cannot be deleted.</p>
        <form method="post">
            <?= csrfField() ?>
            <label class="d-block mb-3"><input type="checkbox" name="confirm" value="yes" required> I confirm deletion of this unused center.</label>
            <button class="btn btn-danger" name="action" value="delete">Delete center</button>
            <a class="btn btn-outline-secondary" href="?id=<?= (int)$id ?>">Cancel</a>
        </form>
    </section>
<?php else: ?>
<h2 id="center-form"><?= $editing ? 'Edit center' : 'Add center' ?></h2>
<form method="post" class="col-md-8 form-panel workflow-form">
    <?= csrfField() ?>
    <label class="d-block mb-2">Center name<input name="name" class="form-control" required maxlength="100" value="<?= h($form['name']) ?>"></label>
    <label class="d-block mb-2">Barangay<select name="barangay" class="form-select" required>
        <option value="">Choose barangay</option>
        <?php foreach (BARANGAYS as $area): ?><option <?= $form['barangay'] === $area ? 'selected' : '' ?>><?= $area ?></option><?php endforeach; ?>
    </select></label>
    <?php
    $pickerKind = 'centers';
    $point = $form;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $point['latitude'] = posted('latitude');
        $point['longitude'] = posted('longitude');
    }
    require __DIR__ . '/../map/picker.php';
    ?>
    <label class="d-block mb-2">Capacity in people<input name="capacity" class="form-control" type="number" min="1" max="1000000" required value="<?= h($form['capacity']) ?>"></label>
    <label class="d-block mb-2">Status<select name="status" class="form-select"><option>Open</option><option <?= $form['status']==='Closed'?'selected':'' ?>>Closed</option></select></label>
    <button class="btn btn-primary" name="action" value="save">Save center</button>
    <a class="btn btn-outline-secondary" href="index.php">Cancel</a>
</form>
<?php if ($editing): ?>
    <p class="mt-3"><a class="btn btn-outline-danger" href="?action=delete&amp;id=<?= (int)$id ?>">Delete center</a></p>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
