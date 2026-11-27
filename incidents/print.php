<?php
require __DIR__ . '/access.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$report = readIncident($pdo, $user, $id);
$reference = 'ATOK-IR-' . substr($report['reported_at'], 0, 4) . '-' . str_pad($report['id'], 4, '0', STR_PAD_LEFT);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $reference ?> · Incident report</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/site.css">
</head>
<body class="report-copy">
    <header>Municipality of Atok, Benguet<br>Municipal Disaster Risk Reduction and Management Office</header>
    <h1>Community emergency and incident report</h1>
    <p><?= $reference ?> · <?= $user['role'] === 'staff' ? 'Staff copy' : 'Resident copy' ?></p>
    <table>
        <?php foreach (['reported_at'=>'Date reported','source'=>'Reporting source','contact_name'=>'Reporter','contact_phone'=>'Contact number','barangay'=>'Barangay','location'=>'Written location','incident_type'=>'Classification','status'=>'Current status'] as $key=>$label): ?>
            <tr><th><?= $label ?></th><td><?= htmlspecialchars($report[$key] ?? 'Awaiting confirmation') ?></td></tr>
        <?php endforeach; ?>
    </table>
    <h2>Incident narrative</h2>
    <p><?= nl2br(htmlspecialchars($report['description'])) ?></p>
    <footer>Proposed incident report format</footer>
    <p class="screen-only"><a href="view.php?id=<?= $id ?>">Return to report</a></p>
</body>
</html>
