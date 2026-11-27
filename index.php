<?php
require __DIR__ . '/includes/session.php';
if ($user) {
    go($user['must_change'] ? 'change_password.php' : 'dashboard/' . $user['role'] . '.php');
}
$error = '';
$email = trim(posted('email'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = $pdo->prepare('SELECT * FROM accounts WHERE email=? AND active=1');
    $query->execute([$email]);
    $account = $query->fetch();
    if ($account && password_verify(posted('password'), $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $account['id'];
        $_SESSION['version'] = (int)$account['session_version'];
        $_SESSION['token'] = bin2hex(random_bytes(32));
        go('index.php');
    }
    $error = 'Email or password is incorrect, or the account is inactive.';
}
$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<h1>Sign in</h1>
<?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="col-md-6">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block mb-3">Email<input class="form-control" name="email" type="email" value="<?= htmlspecialchars($email) ?>" required autocomplete="username"></label>
    <label class="d-block mb-3">Password<input class="form-control" name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary">Sign in</button>
</form>
<p class="mt-3">Forgot your password? Contact MDRRMO staff to verify your identity and request a temporary password.</p>
<?php
$notices = $pdo->query("SELECT title,category,message,published_at FROM announcements WHERE status='Published' AND show_on_login=1 ORDER BY published_at DESC,id DESC LIMIT 3")->fetchAll();
?>
<?php if ($notices): ?>
    <section class="mt-4" aria-labelledby="notices">
        <h2 id="notices">Public announcements</h2>
        <?php foreach ($notices as $notice): ?>
            <article class="mb-3">
                <h3 class="h5"><?= htmlspecialchars($notice['title']) ?></h3>
                <p class="mb-1"><?= htmlspecialchars($notice['category']) ?> · <?= htmlspecialchars($notice['published_at']) ?></p>
                <p><?= nl2br(htmlspecialchars($notice['message'])) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
