<?php
require __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/password.php';
$user = requireRole('staff');
$error = '';
$adding = ($_GET['action'] ?? '') === 'add';
$editingAccount = ($_GET['action'] ?? '') === 'edit';
$confirmingDeactivation = ($_GET['action'] ?? '') === 'deactivate';
$deactivationTarget = null;
if ($confirmingDeactivation || $editingAccount) {
    $targetId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $query = $pdo->prepare("SELECT id,full_name,email,role,active FROM accounts WHERE id=? AND role='staff'");
    $query->execute([$targetId]);
    $deactivationTarget = $query->fetch();
    if (!$deactivationTarget || ($confirmingDeactivation && !$deactivationTarget['active'])) {
        http_response_code(404);
        exit('Staff account not found.');
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
            $temporary = temporaryPassword();
            $temporaryEmail = $email;
            $query = $pdo->prepare("INSERT INTO accounts(full_name,email,password_hash,role,must_change) VALUES(?,?,?,'staff',1)");
            $query->execute([$name, $email, password_hash($temporary, PASSWORD_DEFAULT)]);
        } else {
            $query = $pdo->prepare('SELECT * FROM accounts WHERE id=? FOR UPDATE');
            $query->execute([posted('id')]);
            $target = $query->fetch();
            if (!$target || $target['role'] !== 'staff') {
                throw new DomainException('Manage resident accounts from their household record.');
            }
            if ($action === 'edit') {
                $name = trim(posted('name'));
                $email = trim(posted('email'));
                if ($name === '' || mb_strlen($name) > 100 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new DomainException('Enter a name and a valid email address.');
                }
                $query = $pdo->prepare('UPDATE accounts SET full_name=?,email=? WHERE id=? AND role=\'staff\'');
                $query->execute([$name,$email,$target['id']]);
            } elseif ($action === 'reset' && $target['active'] && posted('verified') === 'yes') {
                if ($target['id'] == $user['id']) {
                    throw new DomainException('Use Change my password for your own account.');
                }
                $temporary = temporaryPassword();
                $temporaryEmail = $target['email'];
                $query = $pdo->prepare('UPDATE accounts SET password_hash=?, must_change=1, session_version=session_version+1 WHERE id=? AND role=\'staff\' AND active=1');
                $query->execute([password_hash($temporary, PASSWORD_DEFAULT), $target['id']]);
            } elseif (in_array($action, ['activate','deactivate'], true) && $target['role'] === 'staff') {
                if ($action === 'deactivate' && $target['id'] == $user['id']) {
                    throw new DomainException('You cannot deactivate your own staff account.');
                }
                if ($action === 'deactivate' && $target['active'] && array_sum(array_column($staff, 'active')) <= 1) {
                    throw new DomainException('Keep at least one active staff account.');
                }
                $query = $pdo->prepare('UPDATE accounts SET active=?, session_version=session_version+1 WHERE id=? AND role=\'staff\'');
                $query->execute([(int)($action === 'activate'), $target['id']]);
            } else {
                throw new DomainException('Verify the identity of an active account holder before resetting their password.');
            }
        }
        $pdo->commit();
        $_SESSION['notice'] = isset($temporary)
            ? ['login_email'=>$temporaryEmail, 'temporary_password'=>$temporary]
            : 'Account updated.';
        go('accounts.php');
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof DomainException ? $exception->getMessage() : ($exception->getCode() === '23000' ? 'Email already registered.' : 'Account update failed.');
    }
}
$pageTitle = 'Staff accounts';
require __DIR__ . '/includes/header.php';
?>
<h1>Staff accounts</h1>
<p class="account-guidance">Manage staff names, login emails, access, and verified password resets here. Resident accounts are managed from their household records. Account roles cannot be changed and records are retained to preserve system history.</p>
<?php if ($error): ?><p class="feedback-error" role="alert"><strong>Account update failed:</strong> <?= h($error) ?></p><?php endif; ?>
<?php if (isset($_SESSION['notice'])): ?>
    <?php $notice = $_SESSION['notice']; unset($_SESSION['notice']); ?>
    <?php if (is_array($notice) && is_string($notice['temporary_password'] ?? null)): ?>
        <section class="temporary-password-notice" role="status" aria-labelledby="temporary-password-heading">
            <h2 class="h5" id="temporary-password-heading">Temporary password · shown once</h2>
            <p>Login email: <strong><?= h($notice['login_email'] ?? '') ?></strong></p>
            <p class="temporary-password-value"><code><?= h($notice['temporary_password']) ?></code></p>
            <p class="mb-0">Give this password to the account holder privately. They must choose a new password after signing in. Do not print or share it in a group message.</p>
        </section>
    <?php else: ?>
        <p class="feedback-notice" role="status"><?= h(is_string($notice) ? $notice : 'Account updated.') ?></p>
    <?php endif; ?>
<?php endif; ?>
<a href="change_password.php">Change my password</a>
<?php if (!$adding && !$editingAccount && !$confirmingDeactivation): ?><p><a class="btn btn-primary" href="?action=add">Add staff account</a></p><?php elseif ($adding): ?>
<h2 class="h4 mt-4">Add staff account</h2>
<form method="post" class="col-md-6">
    <?= csrfField() ?>
    <label class="d-block">Full name<input name="name" class="form-control" maxlength="100" required></label>
    <label class="d-block mb-2">Email<input name="email" class="form-control" type="email" required></label>
    <button class="btn btn-primary" name="action" value="create">Create staff account</button>
    <a class="btn btn-outline-secondary" href="accounts.php">Cancel</a>
</form>
<?php endif; ?>
<?php if ($editingAccount): ?>
    <h2 class="h4 mt-4">Edit staff account</h2>
    <form method="post" class="form-panel col-md-7">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$deactivationTarget['id'] ?>">
        <label class="d-block mb-3">Full name
            <input name="name" class="form-control" maxlength="100" required value="<?= h($deactivationTarget['full_name']) ?>">
        </label>
        <label class="d-block mb-3">Login email
            <input name="email" class="form-control" type="email" maxlength="254" required autocomplete="email" value="<?= h($deactivationTarget['email']) ?>">
        </label>
        <button class="btn btn-primary" name="action" value="edit">Save staff account</button>
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
<?php if (!$adding && !$editingAccount && !$confirmingDeactivation): ?>
<h2 class="h4 mt-4">Staff accounts</h2>
<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = searchPattern($search);
$where = " WHERE role='staff' AND (full_name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
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
<p class="results-summary"><?= $total ?> matching staff accounts · Page <?= $page ?> of <?= $pages ?></p>
<?php if (!$total): ?><p class="empty-state">No staff accounts match this search.</p><?php else: ?>
<div class="table-responsive">
    <table class="table account-table">
        <thead><tr><th>Name and email</th><th>Status</th><th>Edit</th><th>Account access</th><th>Password reset</th></tr></thead>
        <tbody>
        <?php foreach ($query as $row): ?>
            <tr>
                <td><strong><?= h($row['full_name']) ?></strong><br><span class="text-secondary"><?= h($row['email']) ?></span></td>
                <td><span class="status-badge status-<?= $row['active'] ? 'available' : 'neutral' ?>"><?= $row['active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <a class="btn btn-outline-primary btn-sm" href="?action=edit&amp;id=<?= (int)$row['id'] ?>">Edit</a>
                </td>
                <td>
                    <?php if ($row['active'] && $row['id'] != $user['id']): ?>
                        <a class="btn btn-outline-danger btn-sm" href="?action=deactivate&amp;id=<?= (int)$row['id'] ?>">Deactivate</a>
                    <?php elseif (!$row['active']): ?>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-outline-secondary btn-sm" name="action" value="activate">Activate</button>
                        </form>
                    <?php else: ?>
                        <span class="text-secondary">Current account</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['active'] && $row['id'] != $user['id']): ?>
                        <form method="post" class="account-actions">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <label class="account-verify"><input type="checkbox" name="verified" value="yes"> Identity verified</label>
                            <button class="btn btn-outline-secondary btn-sm" name="action" value="reset">Issue temporary password</button>
                        </form>
                    <?php else: ?>
                        <span class="text-secondary">Not available</span>
                    <?php endif; ?>
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
