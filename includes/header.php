<?php
$base = htmlspecialchars(rtrim($baseUrl, '/'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> | Atok MDRRM System</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/site.css">
</head>
<body>
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<header class="container py-4">
    <p class="mb-1">Municipality of Atok, Benguet</p>
    <div class="office-name">Municipal Disaster Risk Reduction and Management Office</div>
</header>
<?php if ($user): ?>
<nav class="navbar navbar-expand-md navbar-dark" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="<?= $base ?>/index.php">Atok MDRRM System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div id="menu" class="collapse navbar-collapse">
            <a class="nav-link text-white" href="<?= $base ?>/index.php">Home</a>
            <?php if (!$user['must_change']): ?>
                <a class="nav-link text-white" href="<?= $base ?>/incidents/index.php"><?= $user['role'] === 'staff' ? 'Incident reports' : 'My reports' ?></a>
            <?php endif; ?>
            <?php if ($user['role'] === 'staff' && !$user['must_change']): ?>
                <a class="nav-link text-white" href="<?= $base ?>/households/index.php">Households</a>
            <?php endif; ?>
                <form method="post" action="<?= $base ?>/logout.php">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                    <button class="btn btn-outline-light">Log out</button>
                </form>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container py-5" id="main">
