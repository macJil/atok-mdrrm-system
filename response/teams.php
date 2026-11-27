<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$editing = isset($_GET['id']);
$team = ['name'=>'','contact_phone'=>'','active'=>1];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($editing) {
    $query = $pdo->prepare('SELECT * FROM response_teams WHERE id=?');
    $query->execute([$id]);
    $team = $query->fetch();
    if (!$team) {
        http_response_code(404);
        exit('Team not found.');
    }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        if ($editing) {
            $query = $pdo->prepare('SELECT id FROM response_teams WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            if (!$query->fetchColumn()) {
                throw new DomainException('Team no longer exists.');
            }
        }
        if ($editing && posted('action') === 'delete') {
            if (posted('confirm') !== 'yes') {
                throw new DomainException('Confirm permanent deletion first.');
            }
            $query = $pdo->prepare('DELETE FROM response_teams WHERE id=?');
            $query->execute([$id]);
        } elseif (posted('action') === 'save') {
            $team['name'] = trim(posted('name'));
            $team['contact_phone'] = trim(posted('phone'));
            $team['active'] = (int)(posted('active') === 'yes');
            if ($team['name'] === '' || mb_strlen($team['name']) > 100) {
                throw new DomainException('Enter a team name of up to 100 characters.');
            }
            if ($team['contact_phone'] !== '' && !preg_match('/^(09|[+]639)\d{9}$/D', $team['contact_phone'])) {
                throw new DomainException('Enter a valid mobile number or leave it blank.');
            }
            $args = [$team['name'],$team['contact_phone'],$team['active']];
            if ($editing) {
                if (!$team['active']) {
                    $query = $pdo->prepare("SELECT COUNT(*) FROM incident_assignments WHERE team_id=? AND status='Assigned'");
                    $query->execute([$id]);
                    if ($query->fetchColumn() > 0) {
                        throw new DomainException('Complete or cancel outstanding assignments first.');
                    }
                }
                $query = $pdo->prepare('UPDATE response_teams SET name=?,contact_phone=?,active=? WHERE id=?');
                $args[] = $id;
            } else {
                $query = $pdo->prepare('INSERT INTO response_teams(name,contact_phone,active) VALUES(?,?,?)');
            }
            $query->execute($args);
        } else {
            throw new DomainException('Choose a listed action.');
        }
        $pdo->commit();
        go('response/teams.php');
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Could not save. Names must be unique; teams with incident history cannot be deleted.';
    }
}
$pageTitle = 'Response teams';
require __DIR__ . '/../includes/header.php';
?>
<h1>Response teams</h1>
<?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<h2 class="h5"><?= $editing ? 'Edit team' : 'Add team' ?></h2>
<form method="post" class="col-md-6 mb-4">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block mb-2">Team name<input class="form-control" name="name" maxlength="100" required value="<?= htmlspecialchars($team['name']) ?>"></label>
    <label class="d-block mb-2">Contact number (optional)<input class="form-control" name="phone" type="tel" maxlength="13" value="<?= htmlspecialchars($team['contact_phone']) ?>"></label>
    <label class="d-block mb-2"><input name="active" type="checkbox" value="yes" <?= $team['active'] ? 'checked' : '' ?>> Available for dispatch</label>
    <button class="btn btn-primary" name="action" value="save">Save team</button>
</form>
<?php if ($editing): ?>
    <form method="post" class="mb-3">
        <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
        <label><input name="confirm" type="checkbox" value="yes" required> Confirm permanent deletion of this unused team</label>
        <button class="btn btn-outline-danger" name="action" value="delete">Delete team</button>
    </form>
    <a class="btn btn-outline-secondary" href="teams.php">Return to teams</a>
<?php else: ?>

<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$where = " WHERE name LIKE ? ESCAPE '!'";
$args = [$term];
$query = $pdo->prepare('SELECT COUNT(*) FROM response_teams' . $where);
$query->execute($args);
$total = (int)$query->fetchColumn();
$pages = max(1, (int)ceil($total / 10));
$page = min($pages, max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)));
$offset = ($page - 1) * 10;
$query = $pdo->prepare('SELECT * FROM response_teams' . $where . " ORDER BY name LIMIT 10 OFFSET $offset");
$query->execute($args);
?>
<form method="get" class="mb-3">
    <label>Search team name<input class="form-control" name="q" value="<?= htmlspecialchars($search) ?>"></label>
    <button class="btn btn-outline-secondary">Search</button>
</form>
<p><?= $total ?> matching records · Page <?= $page ?> of <?= $pages ?></p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Team</th><th>Mobile</th><th>Availability</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($query as $row): ?>
            <tr><td><?= htmlspecialchars($row['name']) ?></td><td><?= htmlspecialchars($row['contact_phone']) ?></td><td><?= $row['active'] ? 'Active' : 'Inactive' ?></td>
                <td><a class="btn btn-outline-secondary" href="?id=<?= (int)$row['id'] ?>">Edit</a></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>

<?php endif; ?>
<a class="btn btn-outline-secondary my-2" href="../dashboard/staff.php">Return to dashboard</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
