<?php
require __DIR__ . '/access.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$report = readIncident($pdo, $user, $id);
$reference = 'ATOK-IR-' . substr($report['reported_at'], 0, 4) . '-' . str_pad($report['id'], 4, '0', STR_PAD_LEFT);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['copy'])) {
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
    <?php
    exit;
}
$staff = $user['role'] === 'staff';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('staff');
    $action = posted('action');
    $remarks = trim(posted('remarks'));
    $teamId = null;
    try {
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT status FROM incidents WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $status = $query->fetchColumn();
        if ($remarks === '' || mb_strlen($remarks) > 500) {
            throw new DomainException('Record a short action remark (up to 500 characters).');
        }
        if (in_array($action, ['Verify only','Reject'], true) && $status === 'Pending') {
            $status = $action === 'Reject' ? 'Rejected' : 'Verified';
        } elseif (($action === 'Verify and assign' && $status === 'Pending') ||
                  ($action === 'Assign team' && in_array($status, ['Verified','Responding'], true))) {
            $teamId = filter_var(posted('team'), FILTER_VALIDATE_INT);
            $query = $pdo->prepare('SELECT active FROM response_teams WHERE id=? FOR UPDATE');
            $query->execute([$teamId]);
            if (!$query->fetchColumn()) {
                throw new DomainException('Select an active team.');
            }
            $query = $pdo->prepare('INSERT INTO incident_assignments(incident_id,team_id) VALUES(?,?)');
            $query->execute([$id,$teamId]);
            $status = 'Responding';
        } elseif (in_array($action, ['Complete','Cancel','Resume','Record progress'], true) && in_array($status, ['Responding','Verified'], true)) {
            $teamId = filter_var(posted('team'), FILTER_VALIDATE_INT);
            $query = $pdo->prepare('SELECT active FROM response_teams WHERE id=? FOR UPDATE');
            $query->execute([$teamId]);
            if (!$query->fetchColumn()) {
                throw new DomainException('Activate the team before resuming dispatch.');
            }
            $query = $pdo->prepare('SELECT status FROM incident_assignments WHERE incident_id=? AND team_id=?');
            $query->execute([$id,$teamId]);
            $previous = $query->fetchColumn();
            if (($action === 'Resume' && $previous !== 'Cancelled') || ($action !== 'Resume' && $previous !== 'Assigned')) {
                throw new DomainException('This assignment has changed. Review its current status.');
            }
            $next = 'Assigned';
            if ($action === 'Complete') {
                $next = 'Completed';
            } elseif ($action === 'Cancel') {
                $next = 'Cancelled';
            }
            $query = $pdo->prepare('UPDATE incident_assignments SET status=? WHERE incident_id=? AND team_id=?');
            $query->execute([$next,$id,$teamId]);
            $query = $pdo->prepare('SELECT status FROM incident_assignments WHERE incident_id=?');
            $query->execute([$id]);
            $statuses = $query->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('Assigned', $statuses, true)) {
                $status = 'Responding';
            } elseif (in_array('Cancelled', $statuses, true)) {
                $status = 'Verified';
            } else {
                $status = 'Resolved';
            }
        } else {
            throw new DomainException('That action is unavailable for the current incident status.');
        }
        $query = $pdo->prepare('UPDATE incidents SET status=? WHERE id=?');
        $query->execute([$status,$id]);
        $query = $pdo->prepare('INSERT INTO response_actions(incident_id,team_id,staff_id,action_taken,remarks,incident_status) VALUES(?,?,?,?,?,?)');
        $query->execute([$id,$teamId,$user['id'],$action,$remarks,$status]);
        $pdo->commit();
        go('incidents/view.php?id=' . $id);
    } catch (DomainException | PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Update failed. Each team can be assigned only once per incident.';
    }
}
$pageTitle = $reference;
require __DIR__ . '/../includes/header.php';
?>
<h1><?= $reference ?></h1>
<p><?= htmlspecialchars($report['incident_type']) ?> · <?= htmlspecialchars($report['status']) ?> · Reported <?= htmlspecialchars($report['reported_at']) ?></p>
<h2 class="h5">Location</h2>
<p><?= htmlspecialchars($report['barangay'] ?? 'Awaiting confirmation') ?> · <?= htmlspecialchars($report['location']) ?></p>
<h2 class="h5">Description</h2>
<p><?= nl2br(htmlspecialchars($report['description'])) ?></p>
<h2 class="h5">Reporter</h2>
<p><?= htmlspecialchars($report['contact_name']) ?> · <?= htmlspecialchars($report['contact_phone']) ?> · <?= htmlspecialchars($report['source']) ?></p>
<?php if ($report['has_image']): ?><p><a class="btn btn-outline-secondary" href="image.php?id=<?= $id ?>">View attached image</a></p><?php endif; ?>
<a class="btn btn-primary" href="?id=<?= $id ?>&amp;copy=1">Open report copy</a>
<a class="btn btn-outline-secondary" href="index.php">Return to reports</a>
<?php if ($staff): ?>
    <h2 class="h4 mt-4">Incident response</h2>
    <?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if (in_array($report['status'], ['Pending','Verified','Responding'], true)): ?>
        <form method="post" class="col-lg-7 mb-3">
            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
            <label class="d-block mb-2">Available team
                <select class="form-select" name="team">
                    <option value="">Choose for dispatch</option>
                    <?php
                    $teams = $pdo->prepare('SELECT id,name FROM response_teams WHERE active=1 AND id NOT IN
                        (SELECT team_id FROM incident_assignments WHERE incident_id=?) ORDER BY name');
                    $teams->execute([$id]);
                    foreach ($teams as $team): ?>
                        <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="d-block mb-2">Action remarks<input class="form-control" name="remarks" maxlength="500" required></label>
            <?php if ($report['status'] === 'Pending'): ?>
                <button class="btn btn-primary" name="action" value="Verify and assign">Verify and assign</button>
                <button class="btn btn-outline-secondary" name="action" value="Verify only">Verify only</button>
                <button class="btn btn-outline-danger" name="action" value="Reject">Reject</button>
            <?php else: ?>
                <button class="btn btn-primary" name="action" value="Assign team">Assign team</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    <?php
    $query = $pdo->prepare('SELECT a.team_id,a.status,t.name FROM incident_assignments a JOIN response_teams t ON t.id=a.team_id WHERE incident_id=? ORDER BY t.name');
    $query->execute([$id]);
    foreach ($query->fetchAll() as $assignment): ?>
        <h3 class="h5"><?= htmlspecialchars($assignment['name']) ?> · <?= htmlspecialchars($assignment['status']) ?></h3>
        <?php if ($assignment['status'] !== 'Completed'): ?>
            <form method="post" class="col-lg-7 mb-3">
                <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                <input type="hidden" name="team" value="<?= (int)$assignment['team_id'] ?>">
                <label class="d-block mb-2">Team update<input class="form-control" name="remarks" maxlength="500" required></label>
                <?php if ($assignment['status'] === 'Cancelled'): ?>
                    <p>Resume this assignment when work can continue. Every assignment must complete before resolution.</p>
                    <button class="btn btn-outline-primary" name="action" value="Resume">Resume assignment</button>
                <?php else: ?>
                    <button class="btn btn-primary" name="action" value="Complete">Complete assignment</button>
                    <button class="btn btn-outline-secondary" name="action" value="Record progress">Record progress</button>
                    <button class="btn btn-outline-danger" name="action" value="Cancel">Cancel assignment</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
    <h2 class="h4">Response history</h2>
    <ul>
        <?php
        $history = $pdo->prepare('SELECT a.*,t.name,s.full_name FROM response_actions a LEFT JOIN response_teams t ON t.id=a.team_id JOIN accounts s ON s.id=a.staff_id WHERE incident_id=? ORDER BY a.id DESC');
        $history->execute([$id]);
        foreach ($history as $entry): ?>
            <li><?= htmlspecialchars($entry['recorded_at'] . ' · ' . $entry['full_name'] . ' · ' . $entry['action_taken'] . ' ' . ($entry['name'] ?? '') . ' · ' . $entry['incident_status']) ?><br><?= htmlspecialchars($entry['remarks']) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
