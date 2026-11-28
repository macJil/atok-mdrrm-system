<?php
require __DIR__ . '/includes/session.php';
$user = requireLogin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = posted('password');
    $query = $pdo->prepare('SELECT password_hash FROM accounts WHERE id=?');
    $query->execute([$user['id']]);
    $old = $query->fetchColumn();
    if (!password_verify(posted('current'), $old)) {
        $error = 'Enter your current password.';
    } elseif (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0") || $password !== posted('confirm') || password_verify($password, $old)) {
        $error = 'Choose a different password of 8–72 bytes and enter it twice.';
    } else {
        $query = $pdo->prepare('UPDATE accounts SET password_hash=?, must_change=0, session_version=session_version+1 WHERE id=? AND session_version=? AND active=1');
        $query->execute([password_hash($password, PASSWORD_DEFAULT), $user['id'], $_SESSION['version']]);
        if ($query->rowCount() === 1) {
            $_SESSION['version']++;
            session_regenerate_id(true);
        }
        go('index.php');
    }
}
$pageTitle = 'Set your password';
require __DIR__ . '/includes/header.php';
?>
<h1>Set your password</h1>
<p>Enter your current or temporary password, then choose your own.</p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="col-md-6">
    <?= csrfField() ?>
    <label class="d-block mb-2">Current password<input class="form-control" type="password" name="current" required autocomplete="current-password"></label>
    <label class="d-block mb-2">New password<input class="form-control" type="password" name="password" required autocomplete="new-password"></label>
    <label class="d-block mb-3">Confirm new password<input class="form-control" type="password" name="confirm" required autocomplete="new-password"></label>
    <button class="btn btn-primary">Save password</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
