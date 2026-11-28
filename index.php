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
<h1>Atok MDRRMO public information and services</h1>
<div class="login-page-grid">
    <section class="login-panel" aria-labelledby="sign-in-heading">
        <h2 id="sign-in-heading">Sign in</h2>
        <p>Use your resident or staff account to access this system.</p>
        <?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post" action="login.php">
            <?= csrfField() ?>
            <label class="d-block mb-3">Email<input class="form-control" name="email" type="email" value="<?= h($email) ?>" required autocomplete="username"></label>
            <label class="d-block mb-3">Password<input class="form-control" name="password" type="password" required autocomplete="current-password"></label>
            <button class="btn btn-primary">Sign in</button>
        </form>
        <p class="mt-3 mb-0">Forgot your password? Contact MDRRMO staff to verify your identity and request a temporary password.</p>
        <div class="privacy-notice mt-3">
            <h3 class="h6">Privacy and responsible use</h3>
            <p class="mb-0">Use this system for Atok MDRRMO services. Information you submit, including personal details, is used by the office for incident response and official records. Do not share your password.</p>
        </div>
    </section>
    <aside class="public-info" aria-label="Emergency information">
        <section class="hotline-panel" aria-labelledby="hotline-heading">
            <h2 class="h4" id="hotline-heading">Emergency hotlines</h2>
            <p class="hotline-notice">For urgent assistance, call the appropriate service directly. Do not wait for an online response.</p>
            <dl class="hotline-list">
                <div><dt>Municipal DRRM Office</dt><dd><a href="tel:+639218717244">0921 871 7244</a></dd></div>
                <div><dt>Atok Municipal Police Station</dt><dd><a href="tel:+639214801585">0921 480 1585</a> · <a href="tel:+639985987781">0998 598 7781</a></dd></div>
                <div><dt>Atok Super Health Center</dt><dd>Globe: <a href="tel:+639542265559">0954 226 5559</a><br>Smart: <a href="tel:+639984674411">0998 467 4411</a></dd></div>
                <div><dt>Municipal Mayor’s Office</dt><dd><a href="tel:+639319357993">0931 935 7993</a></dd></div>
                <div><dt>Atok District Hospital</dt><dd><a href="tel:+639185006111">0918 500 6111</a></dd></div>
                <div><dt>BFP Atok Main Station · Sayangan, Paoay</dt><dd><a href="tel:+639081513371">0908 151 3371</a></dd></div>
                <div><dt>BFP Atok Sub-Station · Km. 24, Caliking</dt><dd><a href="tel:+639109706412">0910 970 6412</a></dd></div>
            </dl>
        </section>
    </aside>
</div>
<?php
$notices = $pdo->query("SELECT title,category,message,published_at FROM announcements WHERE status='Published' AND show_on_login=1 ORDER BY published_at DESC,id DESC LIMIT 3")->fetchAll();
?>
<?php if ($notices): ?>
    <section class="login-announcements mt-4" aria-labelledby="notices">
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
