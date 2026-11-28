<?php
require __DIR__ . '/includes/session.php';
$user = requireRole('staff');
$error = '';
$adding = ($_GET['action'] ?? '') === 'add';
$confirmingDeactivation = ($_GET['action'] ?? '') === 'deactivate';
$deactivationTarget = null;
if ($confirmingDeactivation) {
    $targetId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $query = $pdo->prepare('SELECT id,full_name,email,role,active FROM accounts WHERE id=?');
    $query->execute([$targetId]);
    $deactivationTarget = $query->fetch();
    if (!$deactivationTarget || !$deactivationTarget['active']) {
        http_response_code(404);
        exit('Active account not found.');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = posted('action');
    try {
        $pdo->beginTransaction();
        // Lock staff rows before counting so concurrent requests cannot disable the last one.
        $staff = $pdo->query("SELECT id, active, session_version FROM accounts WHERE role='staff' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_UNIQUE);
        if (empty($staff[$user['id']]['active']) || (int)$staff[$user['id']]['session_version'] !== $_SESSION['version']) {
            throw new DomainException('Sign in again.');
        }
        if ($action === 'create') {
            $name = trim(posted('name'));
            $email = trim(posted('email'));
            if ($name === '' || mb_strlen($name) > 100 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('Enter a name and a valid email.');
            }
            $temporary = bin2hex(random_bytes(12));
            $query = $pdo->prepare("INSERT INTO accounts(full_name,email,password_hash,role) VALUES(?,?,?,'staff')");
            $query->execute([$name, $email, password_hash($temporary, PASSWORD_DEFAULT)]);
        } else {
            $query = $pdo->prepare('SELECT * FROM accounts WHERE id=? FOR UPDATE');
            $query->execute([posted('id')]);
            $target = $query->fetch();
            if (!$target) {
                throw new DomainException('Account not found.');
            }
            if ($action === 'reset' && $target['active'] && posted('verified') === 'yes') {
                $temporary = bin2hex(random_bytes(12));
                $query = $pdo->prepare('UPDATE accounts SET password_hash=?, must_change=1, session_version=session_version+1 WHERE id=?');
                if ($target['id'] == $user['id']) {
                    throw new DomainException('Use Change my password for your own account.');
                }
                $query->execute([password_hash($temporary, PASSWORD_DEFAULT), $target['id']]);
            } elseif (in_array($action, ['activate','deactivate'], true)) {
                if ($action === 'deactivate' && $target['role'] === 'staff' && $target['active'] && array_sum(array_column($staff, 'active')) <= 1) {
                    throw new DomainException('Keep at least one active staff account.');
                }
                $query = $pdo->prepare('UPDATE accounts SET active=?, session_version=session_version+1 WHERE id=?');
                $query->execute([(int)($action === 'activate'), $target['id']]);
            } else {
                throw new DomainException('Verify the identity of an active account holder before resetting their password.');
            }
        }
        $pdo->commit();
        $_SESSION['notice'] = isset($temporary) ? 'Give this temporary password privately. It is shown once: ' . $temporary : 'Account updated.';
        go('accounts.php');
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof DomainException ? $exception->getMessage() : ($exception->getCode() === '23000' ? 'Email already registered.' : 'Account update failed.');
    }
}
$pageTitle = 'Manage accounts';
require __DIR__ . '/includes/header.php';
?>
<h1>Manage accounts</h1>
<?php if ($error): ?><p class="feedback-error" role="alert"><strong>Account update failed:</strong> <?= h($error) ?></p><?php endif; ?>
<?php if (isset($_SESSION['notice'])): ?>
    <p class="feedback-notice" role="status"><?= h($_SESSION['notice']) ?></p>
    <?php unset($_SESSION['notice']); ?>
<?php endif; ?>
<a href="change_password.php">Change my password</a>
<?php if (!$adding && !$confirmingDeactivation): ?><p><a class="btn btn-primary" href="?action=add">Add staff account</a></p><?php elseif ($adding): ?>
<h2 class="h4 mt-4">Add staff account</h2>
<form method="post" class="col-md-6">
    <?= csrfField() ?>
    <label class="d-block">Full name<input name="name" class="form-control" maxlength="100" required></label>
    <label class="d-block mb-2">Email<input name="email" class="form-control" type="email" required></label>
    <button class="btn btn-primary" name="action" value="create">Create staff account</button>
    <a class="btn btn-outline-secondary" href="accounts.php">Cancel</a>
</form>
<?php endif; ?>
<?php if ($confirmingDeactivation): ?>
    <section class="form-panel col-lg-7">
        <h2 class="h4">Confirm account deactivation</h2>
        <p>Deactivate <strong><?= h($deactivationTarget['full_name']) ?></strong> (<?= h($deactivationTarget['email']) ?>)? This blocks sign-in and preserves the account and its history.</p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$deactivationTarget['id'] ?>">
            <button class="btn btn-danger" name="action" value="deactivate">Deactivate account</button>
            <a class="btn btn-outline-secondary" href="accounts.php">Cancel</a>
        </form>
    </section>
<?php endif; ?>
<?php if (!$adding && !$confirmingDeactivation): ?>
<h2 class="h4 mt-4">Accounts</h2>
<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = searchPattern($search);
$where = " WHERE full_name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!'";
$args = [$term, $term];
$query = $pdo->prepare('SELECT COUNT(*) FROM accounts' . $where);
$query->execute($args);
$total = (int)$query->fetchColumn();
[$page, $offset, $pages] = pageWindow($total, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$query = $pdo->prepare('SELECT id,full_name,email,role,active FROM accounts' . $where . " ORDER BY full_name,id LIMIT 10 OFFSET $offset");
$query->execute($args);
?>
<form method="get" class="mb-3">
    <label>Search name or email<input class="form-control" name="q" value="<?= h($search) ?>"></label>
    <button class="btn btn-outline-secondary">Search</button>
</form>
<p class="results-summary"><?= $total ?> matching accounts · Page <?= $page ?> of <?= $pages ?></p>
<?php if (!$total): ?><p class="empty-state">No accounts match this search.</p><?php else: ?>
<div class="table-responsive">
    <table class="table account-table">
        <thead><tr><th>Name and email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($query as $row): ?>
            <tr>
                <td><strong><?= h($row['full_name']) ?></strong><br><span class="text-secondary"><?= h($row['email']) ?></span></td>
                <td><?= h(ucfirst($row['role'])) ?></td>
                <td><span class="status-badge status-<?= $row['active'] ? 'available' : 'neutral' ?>"><?= $row['active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <form method="post" class="account-actions">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <?php if ($row['active']): ?>
                            <a class="btn btn-outline-danger btn-sm" href="?action=deactivate&amp;id=<?= (int)$row['id'] ?>">Deactivate</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm" name="action" value="activate">Activate</button>
                        <?php endif; ?>
                        <?php if ($row['active'] && $row['id'] != $user['id']): ?>
                            <label class="account-verify"><input type="checkbox" name="verified" value="yes"> Identity verified</label>
                            <button class="btn btn-outline-secondary btn-sm" name="action" value="reset">Issue temporary password</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
<a class="btn btn-outline-secondary my-2" href="dashboard/staff.php">Return to dashboard</a>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
