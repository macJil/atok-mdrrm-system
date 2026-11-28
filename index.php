<?php
require __DIR__ . '/includes/session.php';
if ($user) {
    go($user['must_change'] ? 'change_password.php' : 'dashboard/' . $user['role'] . '.php');
}
$email = is_string($_SESSION['login_email'] ?? null) ? $_SESSION['login_email'] : '';
$error = is_string($_SESSION['login_error'] ?? null) ? $_SESSION['login_error'] : '';
unset($_SESSION['login_email'], $_SESSION['login_error']);
$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<h1>Sign in</h1>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" action="login.php" class="col-md-6">
    <?= csrfField() ?>
    <label class="d-block mb-3">Email<input class="form-control" name="email" type="email" value="<?= h($email) ?>" required autocomplete="username"></label>
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
                <h3 class="h5"><?= h($notice['title']) ?></h3>
                <p class="mb-1"><?= h($notice['category']) ?> · <?= h($notice['published_at']) ?></p>
                <p><?= nl2br(h($notice['message'])) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
