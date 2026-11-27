<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$error = '';
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
        go('accounts/index.php');
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof DomainException ? $exception->getMessage() : ($exception->getCode() === '23000' ? 'Email already registered.' : 'Account update failed.');
    }
}
$pageTitle = 'Manage accounts';
require __DIR__ . '/../includes/header.php';
?>
<h1>Manage accounts</h1>
<?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (isset($_SESSION['notice'])): ?>
    <p role="status"><?= htmlspecialchars($_SESSION['notice']) ?></p>
    <?php unset($_SESSION['notice']); ?>
<?php endif; ?>
<a href="../change_password.php">Change my password</a>
<h2 class="h4 mt-4">Add staff account</h2>
<form method="post" class="col-md-6">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block">Full name<input name="name" class="form-control" maxlength="100" required></label>
    <label class="d-block mb-2">Email<input name="email" class="form-control" type="email" required></label>
    <button class="btn btn-primary" name="action" value="create">Create staff account</button>
</form>
<h2 class="h4 mt-4">Accounts</h2>
<?php
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$term = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$where = " WHERE full_name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!'";
$args = [$term, $term];
$query = $pdo->prepare('SELECT COUNT(*) FROM accounts' . $where);
$query->execute($args);
$total = (int)$query->fetchColumn();
$pages = max(1, (int)ceil($total / 10));
$page = min($pages, max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)));
$offset = ($page - 1) * 10;
$query = $pdo->prepare('SELECT id,full_name,email,role,active FROM accounts' . $where . " ORDER BY full_name,id LIMIT 10 OFFSET $offset");
$query->execute($args);
?>
<form method="get" class="mb-3">
    <label>Search name or email<input class="form-control" name="q" value="<?= htmlspecialchars($search) ?>"></label>
    <button class="btn btn-outline-secondary">Search</button>
</form>
<p><?= $total ?> matching records · Page <?= $page ?> of <?= $pages ?></p>

<?php foreach ($query as $row): ?>
    <section class="border-top py-3">
        <h3 class="h5"><?= htmlspecialchars($row['full_name']) ?></h3>
        <p><?= htmlspecialchars($row['email']) ?> · <?= htmlspecialchars($row['role']) ?> · <?= $row['active'] ? 'Active' : 'Inactive' ?></p>
        <form method="post">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-outline-secondary" name="action" value="<?= $row['active'] ? 'deactivate' : 'activate' ?>"><?= $row['active'] ? 'Deactivate' : 'Activate' ?></button>
            <?php if ($row['active'] && $row['id'] != $user['id']): ?>
                <label><input type="checkbox" name="verified" value="yes"> I verified this person's identity.</label>
                <button class="btn btn-outline-secondary" name="action" value="reset">Issue temporary password</button>
            <?php endif; ?>
        </form>
    </section>
<?php endforeach; ?>
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?q=<?= urlencode($search) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
<a class="btn btn-outline-secondary my-2" href="../dashboard/staff.php">Return to dashboard</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
