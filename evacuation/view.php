<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$id = filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$tab = $_GET['tab'] ?? 'household';
if (!in_array($tab,['household','walkin','current','history'],true)) {
    $tab = 'household';
}
$path = 'evacuation/view.php?id=' . (int)$id;
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
                if ($search === '') {
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
                    if ($name === '' || mb_strlen($name)>100 || $address === '' || mb_strlen($address)>255 || !preg_match('/^(09|[+]639)[0-9]{9}$/D',$phone)) {
                        throw new DomainException('Enter a name, address and valid mobile number (personal or guardian).');
                    }
                    if (str_starts_with($phone,'+63')) {
                        $phone = '0' . substr($phone,3);
                    }
                    $query = $pdo->prepare('INSERT INTO evacuation_walkins(name,address,phone) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
                    $query->execute([$name,$address,$phone]);
                    $person = $pdo->lastInsertId();
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
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'No changes saved. Check whether a selected person already has an active stay, then retry.';
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
require __DIR__ . '/../includes/header.php';
?>
<h1><?= htmlspecialchars($center['name']) ?></h1>
<p><?= htmlspecialchars($center['barangay'] . ' · ' . $center['address']) ?> · <?= $center['status'] ?></p>
<p><strong><?= (int)$center['staying'] ?> staying, <?= $center['status'] === 'Open' ? $center['capacity'] - $center['staying'] : 0 ?> remaining</strong> · Capacity <?= (int)$center['capacity'] ?> people</p>
<a class="btn btn-outline-secondary" href="index.php">Return to centers</a>
<nav class="my-3" aria-label="Center actions">
    <?php foreach (['household'=>'Check in household','walkin'=>'Check in walk-in','current'=>'Currently staying','history'=>'Completed stays'] as $key=>$label): ?>
        <a class="btn <?= $tab === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?id=<?= $id ?>&amp;tab=<?= $key ?>"><?= $label ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
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
                <option value="<?= (int)$row['id'] ?>" <?= $householdId == $row['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($row['full_name'] ?? 'Representative not selected').' · '.$row['barangay'].' · '.$row['address']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <button class="btn btn-outline-secondary">Show members</button>
    </form>
    <?php if ($householdId): ?>
        <form method="post">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
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
                    <?= htmlspecialchars($member['full_name']) ?> <?= $member['stay'] ? '· Already staying' : '' ?>
                </label>
            <?php endforeach; ?>
            <?php if ($available): ?>
                <button class="btn btn-primary mt-2" name="action" value="household">Check in selected people</button>
            <?php else: ?><p>No active members available for check-in.</p><?php endif; ?>
        </form>
    <?php endif; ?>
<?php elseif ($tab === 'walkin'): ?>
    <form method="get" class="mb-3">
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tab" value="walkin">
        <label>Search returning person by name<input class="form-control" name="search" required value="<?= htmlspecialchars($search) ?>"></label>
        <button class="btn btn-outline-secondary">Search</button>
    </form>
    <?php if ($search !== ''):
        $query = $pdo->prepare("SELECT w.*,s.id AS stay FROM evacuation_walkins w LEFT JOIN evacuation_stays s ON s.walkin_id=w.id AND s.left_at IS NULL WHERE w.name LIKE ? ESCAPE '!' ORDER BY w.name,w.address,w.id LIMIT 21");
        $query->execute(['%' . strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']) . '%']);
        $matches = $query->fetchAll();
        if (!$matches): ?><p>No matching walk-ins.</p><?php endif; ?>
        <?php foreach (array_slice($matches,0,20) as $row): ?>
            <form method="post" class="mb-2">
                <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                <input type="hidden" name="walkin" value="<?= (int)$row['id'] ?>">
                <?= htmlspecialchars($row['name'].' · '.$row['address'].' · '.$row['phone']) ?>
                <button class="btn btn-outline-secondary" name="action" value="returning" <?= $row['stay'] ? 'disabled' : '' ?>><?= $row['stay'] ? 'Already staying' : 'Check in again' ?></button>
            </form>
        <?php endforeach; ?>
        <?php if (count($matches)>20): ?><p>Refine the name to see more matches.</p><?php endif; ?>
        <details <?= (!$matches || $error) ? 'open' : '' ?>><summary>Register a new walk-in</summary>
            <form method="post" class="col-md-7">
                <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                <label class="d-block">Full name<input class="form-control" name="name" maxlength="100" required value="<?= htmlspecialchars(posted('name') ?: $search) ?>"></label>
                <label class="d-block">Home address or landmark<input class="form-control" name="address" maxlength="255" required value="<?= htmlspecialchars(posted('address')) ?>"></label>
                <label class="d-block mb-2">Mobile number (personal or guardian)<input class="form-control" type="tel" name="phone" maxlength="13" required value="<?= htmlspecialchars(posted('phone')) ?>"></label>
                <button class="btn btn-primary" name="action" value="new">Register and check in</button>
            </form>
        </details>
    <?php endif; ?>
<?php else:
    $where = $tab === 'current' ? 's.left_at IS NULL' : 's.left_at IS NOT NULL';
    $query = $pdo->prepare("SELECT COUNT(*) FROM evacuation_stays s WHERE s.center_id=? AND $where");
    $query->execute([$id]);
    $pages = max(1,(int)ceil($query->fetchColumn()/10));
    $page = min($pages,max(1,(int)filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT)));
    $offset = ($page-1)*10;
    $query = $pdo->prepare("SELECT s.*,COALESCE(m.full_name,w.name) AS person FROM evacuation_stays s
        LEFT JOIN household_members m ON m.id=s.member_id LEFT JOIN evacuation_walkins w ON w.id=s.walkin_id
        WHERE s.center_id=? AND $where ORDER BY s.id DESC LIMIT 10 OFFSET $offset");
    $query->execute([$id]);
    $stays = $query->fetchAll();
?>
    <h2><?= $tab === 'current' ? 'Currently staying' : 'Completed stays' ?></h2>
    <?php if (!$stays): ?><p>No stays to show.</p><?php endif; ?>
    <?php foreach ($stays as $stay): ?>
        <div class="border-bottom py-2"><strong><?= htmlspecialchars($stay['person']) ?></strong>
            · Arrived <?= htmlspecialchars($stay['arrived_at']) ?>
            <?php if ($stay['left_at']): ?> · Left <?= htmlspecialchars($stay['left_at']) ?>
            <?php else: ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                    <input type="hidden" name="stay" value="<?= (int)$stay['id'] ?>">
                    <button class="btn btn-outline-secondary" name="action" value="checkout">Check out</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($page > 1): ?><a href="?id=<?= $id ?>&amp;tab=<?= $tab ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?><a href="?id=<?= $id ?>&amp;tab=<?= $tab ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
