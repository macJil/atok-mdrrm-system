<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('staff');
require_once __DIR__ . '/../map/boundary.php';
$id = filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$tab = $_GET['tab'] ?? 'household';
if (!in_array($tab,['household','walkin','current','history'],true)) {
    $tab = 'household';
}
$path = 'evacuation/view.php?id=' . (int)$id;
$addingWalkin = $tab === 'walkin' && ($_GET['action'] ?? '') === 'add';
$householdId = filter_input(INPUT_GET,'household',FILTER_VALIDATE_INT);
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        // Check-in, checkout and center edits take the same center lock first.
        $query = $pdo->prepare('SELECT capacity,status FROM evacuation_centers WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $center = $query->fetch();
        if (!$center) {
            throw new DomainException('Center not found.');
        }
        $action = posted('action');
        if ($action === 'checkout') {
            $query = $pdo->prepare('UPDATE evacuation_stays SET left_at=CURRENT_TIMESTAMP,checked_out_by=? WHERE id=? AND center_id=? AND left_at IS NULL');
            $query->execute([$user['id'],posted('stay'),$id]);
            if (!$query->rowCount()) {
                throw new DomainException('This person has no active stay here.');
            }
        } else {
            if ($center['status'] !== 'Open') {
                throw new DomainException('This center is closed.');
            }
            $query = $pdo->prepare('SELECT COUNT(*) FROM evacuation_stays WHERE center_id=? AND left_at IS NULL');
            $query->execute([$id]);
            $remaining = $center['capacity'] - (int)$query->fetchColumn();
            if ($action === 'household') {
                $selected = $_POST['members'] ?? [];
                if (!is_array($selected) || !$selected || !$householdId) {
                    throw new DomainException('Select a household and at least one arriving member.');
                }
                if (count($selected) > $remaining) {
                    throw new DomainException('Insufficient capacity for this group. Nobody was checked in.');
                }
                $member = $pdo->prepare('SELECT id FROM household_members WHERE id=? AND household_id=? AND active=1 FOR UPDATE');
                $insert = $pdo->prepare('INSERT INTO evacuation_stays(center_id,member_id,arrived_by) VALUES(?,?,?)');
                foreach ($selected as $person) {
                    if (!is_string($person) || !ctype_digit($person)) {
                        throw new DomainException('Invalid member selection.');
                    }
                    $member->execute([$person,$householdId]);
                    if (!$member->fetchColumn()) {
                        throw new DomainException('Choose active members of the selected household.');
                    }
                    $insert->execute([$id,$person,$user['id']]);
                }
            } elseif ($action === 'returning' || $action === 'new') {
                if ($remaining < 1) {
                    throw new DomainException('This center is full.');
                }
                if ($action === 'returning' && $search === '') {
                    throw new DomainException('Search for the person first.');
                }
                if ($action === 'returning') {
                    $query = $pdo->prepare('SELECT id FROM evacuation_walkins WHERE id=?');
                    $query->execute([posted('walkin')]);
                    $person = $query->fetchColumn();
                    if (!$person) {
                        throw new DomainException('Select a returning person.');
                    }
                } else {
                    $name = trim(posted('name'));
                    $address = trim(posted('address'));
                    $phone = trim(posted('phone'));
                    $latitude = posted('latitude');
                    $longitude = posted('longitude');
                    $locationProblems = locationErrors($address, $latitude, $longitude);
                    if ($locationProblems) {
                        throw new DomainException(implode(' ', $locationProblems));
                    }
                    if ($name === '' || mb_strlen($name)>100 || $address === '' || mb_strlen($address)>255 || !validMobileNumber($phone)) {
                        throw new DomainException('Enter a name, address and valid mobile number (personal or guardian).');
                    }
                    if (str_starts_with($phone,'+63')) {
                        $phone = '0' . substr($phone,3);
                    }
                    $query = $pdo->prepare('INSERT INTO evacuation_walkins(name,address,phone,latitude,longitude)
                        VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),
                        latitude=COALESCE(VALUES(latitude),latitude),longitude=COALESCE(VALUES(longitude),longitude)');
                    $query->execute([$name,$address,$phone,$latitude === '' ? null : $latitude,$longitude === '' ? null : $longitude]);
                    $person = $pdo->lastInsertId();
                    $query = $pdo->prepare('SELECT c.name FROM evacuation_stays s JOIN evacuation_centers c ON c.id=s.center_id
                        WHERE s.walkin_id=? AND s.left_at IS NULL LIMIT 1');
                    $query->execute([$person]);
                    $activeCenter = $query->fetchColumn();
                    if ($activeCenter) {
                        throw new DomainException('This walk-in already has an active stay at ' . $activeCenter . '. Check them out before checking them in again.');
                    }
                }
                $query = $pdo->prepare('INSERT INTO evacuation_stays(center_id,walkin_id,arrived_by) VALUES(?,?,?)');
                $query->execute([$id,$person,$user['id']]);
            } else {
                throw new DomainException('Choose an arrival or checkout action.');
            }
        }
        $pdo->commit();
        go($path . '&tab=current');
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException) {
            error_log('Evacuation stay update failed: ' . $exception->getMessage());
        }
        $error = $exception instanceof DomainException
            ? $exception->getMessage()
            : ((int)($exception->errorInfo[1] ?? 0) === 1062
                ? 'This person already has an active stay. Check them out before checking them in again.'
                : 'Could not save this evacuation record because of a database error. Please try again or contact the system administrator.');
    }
}
$query = $pdo->prepare('SELECT c.*, (SELECT COUNT(*) FROM evacuation_stays s WHERE s.center_id=c.id AND left_at IS NULL) AS staying FROM evacuation_centers c WHERE c.id=?');
$query->execute([$id]);
$center = $query->fetch();
if (!$center) {
    http_response_code(404);
    exit('Center not found.');
}
$pageTitle = $center['name'];
$pickerKind = 'walkins';
$point = [
    'address' => posted('address'),
    'latitude' => posted('latitude'),
    'longitude' => posted('longitude'),
];
require __DIR__ . '/../includes/header.php';
?>
<h1><?= h($center['name']) ?></h1>
<p><?= h($center['barangay'] . ' · ' . $center['address']) ?> · <span class="status-badge status-<?= strtolower(h($center['status'])) ?>"><?= h($center['status']) ?></span></p>
<div class="record-summary">
    <div><span class="metric-value"><?= (int)$center['staying'] ?></span><span class="metric-label">People staying now</span></div>
    <div><span class="metric-value"><?= $center['status'] === 'Open' ? (int)$center['capacity'] - (int)$center['staying'] : 0 ?></span><span class="metric-label">Remaining spaces</span></div>
    <div><span class="metric-value"><?= (int)$center['capacity'] ?></span><span class="metric-label">Total capacity</span></div>
</div>
<a class="btn btn-outline-secondary" href="index.php">Return to centers</a>
<nav class="my-3" aria-label="Center actions">
    <?php foreach (['household'=>'Check in household','walkin'=>'Check in walk-in','current'=>'Currently staying','history'=>'Completed stays'] as $key=>$label): ?>
        <a class="btn <?= $tab === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?id=<?= $id ?>&amp;tab=<?= $key ?>"><?= $label ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if (($tab === 'walkin' || $tab === 'household') && ($center['status'] !== 'Open' || $center['staying'] >= $center['capacity'])): ?>
    <p>Arrivals are unavailable at this center. Choose another open center with space.</p>
<?php elseif ($tab === 'household'): ?>
    <form method="get" class="mb-3">
        <input type="hidden" name="id" value="<?= $id ?>">
        <label>Household<select name="household" class="form-select" required>
            <option value="">Select a household</option>
            <?php
            $rows = $pdo->query('SELECT h.id,h.address,h.barangay,m.full_name FROM households h LEFT JOIN household_representatives r ON r.household_id=h.id LEFT JOIN household_members m ON m.id=r.member_id ORDER BY h.barangay,m.full_name,h.address,h.id');
            foreach ($rows as $row): ?>
                <option value="<?= (int)$row['id'] ?>" <?= $householdId == $row['id'] ? 'selected' : '' ?>><?= h(($row['full_name'] ?? 'Representative not selected').' · '.$row['barangay'].' · '.$row['address']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <button class="btn btn-outline-secondary">Show members</button>
    </form>
    <?php if ($householdId): ?>
        <form method="post">
            <?= csrfField() ?>
            <p>Uncheck anyone who has not arrived.</p>
            <?php
            $query = $pdo->prepare('SELECT m.id,m.full_name,s.id AS stay FROM household_members m LEFT JOIN evacuation_stays s ON s.member_id=m.id AND s.left_at IS NULL WHERE household_id=? AND active=1 ORDER BY m.full_name,m.id');
            $query->execute([$householdId]);
            $available = 0;
            foreach ($query as $member):
                if (!$member['stay']) {
                    $available++;
                }
                $checked = !$member['stay'] && (!$error || in_array((string)$member['id'], (array)($_POST['members'] ?? []),true));
            ?>
                <label class="d-block"><input type="checkbox" name="members[]" value="<?= (int)$member['id'] ?>" <?= $member['stay'] ? 'disabled' : '' ?> <?= $checked ? 'checked' : '' ?>>
                    <?= h($member['full_name']) ?> <?= $member['stay'] ? '· Already staying' : '' ?>
                </label>
            <?php endforeach; ?>
            <?php if ($available): ?>
                <button class="btn btn-primary mt-2" name="action" value="household">Check in selected people</button>
            <?php else: ?><p>No active members available for check-in.</p><?php endif; ?>
        </form>
    <?php endif; ?>
<?php elseif ($tab === 'walkin' && $addingWalkin): ?>
    <p><a class="btn btn-outline-secondary mb-3" href="?id=<?= (int)$id ?>&amp;tab=walkin">Search returning walk-ins instead</a></p>
    <form method="post" action="?id=<?= (int)$id ?>&amp;tab=walkin&amp;action=add" class="walkin-register-form form-panel workflow-form">
        <?= csrfField() ?>
        <h2 class="h5">Register and check in a walk-in</h2>
        <div class="row g-2">
            <label class="col-md-6">Full name<input class="form-control" name="name" maxlength="100" required value="<?= h(posted('name')) ?>"></label>
            <label class="col-md-6">Mobile number (personal or guardian)<input class="form-control" type="tel" name="phone" maxlength="13" required value="<?= h(posted('phone')) ?>"></label>
        </div>
        <?php require __DIR__ . '/../map/picker.php'; ?>
        <p class="mb-3">A selected point is saved with the private walk-in record for staff use. It is not shown on the community map.</p>
        <button class="btn btn-primary" name="action" value="new">Register and check in</button>
    </form>
<?php elseif ($tab === 'walkin'): ?>
    <form method="get" class="mb-3 d-flex align-items-end flex-wrap gap-2">
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tab" value="walkin">
        <label class="walkin-search-field">Search returning person by name<input class="form-control" name="search" value="<?= h($search) ?>"></label>
        <button class="btn btn-outline-secondary">Search</button>
        <a class="btn btn-primary" href="?id=<?= (int)$id ?>&amp;tab=walkin&amp;action=add">Add walk-in</a>
    </form>
    <?php
        $query = $pdo->prepare("SELECT w.*,s.id AS stay,c.name AS active_center FROM evacuation_walkins w
            LEFT JOIN evacuation_stays s ON s.walkin_id=w.id AND s.left_at IS NULL
            LEFT JOIN evacuation_centers c ON c.id=s.center_id
            WHERE (?='' OR w.name LIKE ? ESCAPE '!')
            ORDER BY CASE WHEN ?='' THEN w.id END DESC,w.name,w.address,w.id LIMIT 21");
        $term = searchPattern($search);
        $query->execute([$search,$term,$search]);
        $matches = $query->fetchAll();
    ?>
    <h2 class="h5"><?= $search === '' ? 'Recent returning walk-ins' : 'Matching walk-ins' ?></h2>
    <?php if (!$matches): ?>
        <p class="empty-state"><?= $search === '' ? 'No walk-in records have been added yet.' : 'No walk-ins match this name.' ?> Use Add walk-in to register a person.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table record-list-table">
            <thead><tr><th>Name</th><th>Address</th><th>Mobile</th><th>Current stay</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($matches,0,20) as $row): ?>
                <tr>
                    <td><?= h($row['name']) ?></td>
                    <td><?= h($row['address']) ?></td>
                    <td><?= h($row['phone']) ?></td>
                    <td><?= $row['stay'] ? h($row['active_center']) : 'Not staying' ?></td>
                    <td>
                        <form method="post" class="m-0">
                            <?= csrfField() ?>
                            <input type="hidden" name="walkin" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-outline-primary btn-sm" name="action" value="returning" <?= $row['stay'] ? 'disabled' : '' ?>><?= $row['stay'] ? 'Already staying' : 'Check in' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
<?php else:
    $where = $tab === 'current' ? 's.left_at IS NULL' : 's.left_at IS NOT NULL';
    $query = $pdo->prepare("SELECT COUNT(*) FROM evacuation_stays s WHERE s.center_id=? AND $where");
    $query->execute([$id]);
    [$page, $offset, $pages] = pageWindow(
        (int)$query->fetchColumn(),
        (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)
    );
    $query = $pdo->prepare("SELECT s.*,COALESCE(m.full_name,w.name) AS person FROM evacuation_stays s
        LEFT JOIN household_members m ON m.id=s.member_id LEFT JOIN evacuation_walkins w ON w.id=s.walkin_id
        WHERE s.center_id=? AND $where ORDER BY s.id DESC LIMIT 10 OFFSET $offset");
    $query->execute([$id]);
    $stays = $query->fetchAll();
?>
    <h2><?= $tab === 'current' ? 'Currently staying' : 'Completed stays' ?></h2>
    <?php if (!$stays): ?><p>No stays to show.</p><?php endif; ?>
    <?php if ($stays): ?>
        <div class="table-responsive"><table class="table log-table">
            <thead><tr><th>Person</th><th>Arrived</th><th>Left</th><th>Action</th></tr></thead>
            <tbody><?php foreach ($stays as $stay): ?>
                <tr>
                    <td><strong><?= h($stay['person']) ?></strong></td>
                    <td class="log-time"><?= h($stay['arrived_at']) ?></td>
                    <td class="log-time"><?= h($stay['left_at'] ?? 'Currently staying') ?></td>
                    <td><?php if (!$stay['left_at']): ?>
                        <form method="post" class="m-0">
                            <?= csrfField() ?>
                            <input type="hidden" name="stay" value="<?= (int)$stay['id'] ?>">
                            <button class="btn btn-outline-secondary btn-sm" name="action" value="checkout">Check out</button>
                        </form>
                    <?php else: ?><span class="status-badge status-completed">Completed</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <?php if ($page > 1): ?><a href="?id=<?= $id ?>&amp;tab=<?= $tab ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?><a href="?id=<?= $id ?>&amp;tab=<?= $tab ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
