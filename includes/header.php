<?php
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Atok MDRRM System';
$base = h(rtrim($baseUrl, '/'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | Atok MDRRM System</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/site.css?v=5">
</head>
<body>
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<header class="site-masthead">
    <div class="container py-3">
        <p class="masthead-locality mb-1">Republic of the Philippines · Province of Benguet</p>
        <div class="office-name">Municipality of Atok</div>
        <p class="masthead-office mb-0">Municipal Disaster Risk Reduction and Management Office</p>
    </div>
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
                <?php if ($user['role'] === 'resident'): ?>
                    <a class="nav-link text-white" href="<?= $base ?>/map/index.php">Community map</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($user['role'] === 'staff' && !$user['must_change']): ?>
                <div class="dropdown">
                    <button class="btn btn-link nav-link dropdown-toggle text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">Operations</button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $base ?>/evacuation/index.php">Evacuation centers</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/relief_distribution.php">Relief distribution</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/relief_inventory/index.php">Relief inventory</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/response_teams.php">Response teams</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/map/index.php">Community map</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/summaries.php">Disaster summaries</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-link nav-link dropdown-toggle text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">Registry &amp; admin</button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $base ?>/households/index.php">Households</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/announcement.php">Announcements</a></li>
                        <li><a class="dropdown-item" href="<?= $base ?>/accounts.php">Manage accounts</a></li>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= $base ?>/logout.php" class="nav-logout">
                <?= csrfField() ?>
                <button class="btn btn-outline-light">Log out</button>
            </form>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container py-3" id="main">
