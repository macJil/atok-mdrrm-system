<?php
require __DIR__ . '/access.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$report = readIncident($pdo, $user, $id);
$reference = 'ATOK-IR-' . substr($report['reported_at'], 0, 4) . '-' . str_pad($report['id'], 4, '0', STR_PAD_LEFT);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['copy'])) {
    require_once __DIR__ . '/../includes/report_pdf.php';
    $pdf = startOfficeReport('Community emergency and incident report');
    $pdf->fields(['Control number'=>$reference, 'Reported'=>$report['reported_at'],
        'Copy'=>$user['role'] === 'staff' ? 'Staff copy' : 'Resident copy']);
    $pdf->fields(['Reporting source'=>$report['source'], 'Reporter'=>$report['contact_name'], 'Contact number'=>$report['contact_phone']]);
    $pdf->section('I. Incident location');
    $coordinates = $report['latitude'] === null ? 'Not recorded' : $report['latitude'] . ', ' . $report['longitude'];
    $pdf->fields(['Barangay'=>$report['barangay'] ?? 'Awaiting confirmation', 'Coordinates'=>$coordinates]);
    $pdf->lineText('Address or landmark: ' . $report['location']);
    $pdf->section('II. Nature and classification of incident');
    $pdf->fields(['Incident type'=>$report['incident_type'], 'Current status'=>$report['status']]);
    $pdf->section('III. Affected persons and damages');
    $pdf->lineText('Injury, casualty and damage totals: not collected on this report.');
    $pdf->section('IV. Incident narrative');
    $pdf->lineText($report['description']);
    if ($user['role'] === 'staff') {
        $pdf->section('Response actions');
        $query = $pdo->prepare('SELECT a.recorded_at,a.action_taken,a.remarks,t.name FROM response_actions a
            LEFT JOIN response_teams t ON t.id=a.team_id WHERE a.incident_id=? ORDER BY a.recorded_at,a.id');
        $query->execute([$id]);
        $actions = $query->fetchAll();
        if (!$actions) {
            $pdf->lineText('No response actions recorded.');
        }
        foreach ($actions as $action) {
            $pdf->lineText($action['recorded_at'].' | '.$action['action_taken'].' | '.($action['name'] ?? 'Staff review').' | '.$action['remarks']);
        }
    }
    if ($user['role'] === 'staff') {
        $pdf->signatureBlock(['Prepared by', 'Verified by', 'Noted by']);
    }
    $pdf->Output('I', $reference . '.pdf');
    exit;
}
$staff = $user['role'] === 'staff';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireRole('staff');
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
<div class="record-summary">
    <div><span class="metric-label">Type</span><br><strong><?= h($report['incident_type']) ?></strong></div>
    <div><span class="metric-label">Status</span><br><span class="status-badge status-<?= strtolower(h($report['status'])) ?>"><?= h($report['status']) ?></span></div>
    <div><span class="metric-label">Reported</span><br><strong><?= h($report['reported_at']) ?></strong></div>
</div>
<section class="content-card p-3 p-md-4 mb-3">
    <h2 class="h5">Location</h2>
    <p><?= h($report['barangay'] ?? 'Awaiting confirmation') ?> · <?= h($report['location']) ?></p>
    <h2 class="h5">Description</h2>
    <p><?= nl2br(h($report['description'])) ?></p>
    <h2 class="h5">Reporter</h2>
    <p class="mb-0"><?= h($report['contact_name']) ?> · <?= h($report['contact_phone']) ?> · <?= h($report['source']) ?></p>
</section>
<?php if ($report['has_image']): ?><p><a class="btn btn-outline-secondary" href="image.php?id=<?= $id ?>">View attached image</a></p><?php endif; ?>
<a class="btn btn-primary" target="_blank" rel="noopener" href="?id=<?= $id ?>&amp;copy=1">Print incident report</a>
<a class="btn btn-outline-secondary" href="index.php">Return to reports</a>
<?php if ($staff): ?>
    <?php
    $query = $pdo->prepare('SELECT a.team_id,a.status,t.name FROM incident_assignments a JOIN response_teams t ON t.id=a.team_id WHERE incident_id=? ORDER BY t.name');
    $query->execute([$id]);
    $assignments = $query->fetchAll();
    $hasActiveAssignment = (bool)array_filter($assignments, static fn($assignment) => $assignment['status'] === 'Assigned');
    ?>
    <?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
    <?php if (!$hasActiveAssignment && in_array($report['status'], ['Pending','Verified','Responding'], true)): ?>
        <section aria-labelledby="incident-response">
            <h2 class="h4 mt-4" id="incident-response">Incident response</h2>
            <form method="post" class="col-lg-8 mb-3 form-panel workflow-form">
                <?= csrfField() ?>
                <label class="d-block mb-2">Available team
                    <select class="form-select" name="team">
                        <option value="">Choose for dispatch</option>
                        <?php
                        $teams = $pdo->prepare('SELECT id,name FROM response_teams WHERE active=1 AND id NOT IN
                            (SELECT team_id FROM incident_assignments WHERE incident_id=?) ORDER BY name');
                        $teams->execute([$id]);
                        foreach ($teams as $team): ?>
                            <option value="<?= (int)$team['id'] ?>"><?= h($team['name']) ?></option>
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
        </section>
    <?php endif; ?>
    <?php if ($assignments): ?><h2 class="h4 mt-4">Assigned response teams</h2><?php endif; ?>
    <?php foreach ($assignments as $assignment): ?>
        <h3 class="h5"><?= h($assignment['name']) ?> · <?= h($assignment['status']) ?></h3>
        <?php if ($assignment['status'] !== 'Completed'): ?>
            <form method="post" class="col-lg-8 mb-3 form-panel workflow-form">
                <?= csrfField() ?>
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
    <ul class="response-history">
        <?php
        $history = $pdo->prepare('SELECT a.*,t.name,s.full_name FROM response_actions a LEFT JOIN response_teams t ON t.id=a.team_id JOIN accounts s ON s.id=a.staff_id WHERE incident_id=? ORDER BY a.id DESC');
        $history->execute([$id]);
        foreach ($history as $entry): ?>
            <li>
                <strong><?= h($entry['action_taken']) ?></strong>
                <?php if ($entry['name']): ?> · <?= h($entry['name']) ?><?php endif; ?>
                · <span class="status-badge status-<?= strtolower(h($entry['incident_status'])) ?>"><?= h($entry['incident_status']) ?></span>
                <p><span class="log-time"><?= h($entry['recorded_at']) ?></span> · Recorded by <?= h($entry['full_name']) ?></p>
                <p><?= h($entry['remarks']) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
