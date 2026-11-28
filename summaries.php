<?php
require __DIR__ . '/includes/session.php';
$user = requireRole('staff');
$area = in_array($_GET['barangay'] ?? '', BARANGAYS, true) ? $_GET['barangay'] : '';
$dates = [];
$error = '';
foreach (['from','through'] as $field) {
    $date = $_GET[$field] ?? '';
    if (!is_string($date) || strlen($date) > 10 || str_contains($date, chr(0))) {
        $date = '';
        $error = 'Enter valid dates.';
    }
    if ($date !== '') {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $error = 'Enter valid dates.';
        }
    }
    $dates[$field] = $date;
}
if ($dates['from'] && $dates['through'] && $dates['from'] > $dates['through']) {
    $error = 'The end date must be on or after the start date.';
}
$dateRange = static function (string $column) use ($dates): array {
    $conditions = [];
    $arguments = [];
    if ($dates['from'] !== '') {
        $conditions[] = $column . ' >= ?';
        $arguments[] = $dates['from'] . ' 00:00:00';
    }
    if ($dates['through'] !== '') {
        $conditions[] = $column . ' <= ?';
        $arguments[] = $dates['through'] . ' 23:59:59';
    }
    return [$conditions ? implode(' AND ', $conditions) : '1=1', $arguments];
};
if (!$error) {
    $query = $pdo->prepare("SELECT COUNT(*) FROM households WHERE registry_reason IN ('Affected','Monitored and affected')
        AND (?='' OR barangay=?)");
    $query->execute([$area,$area]);
    $affected = $query->fetchColumn();
    $query = $pdo->prepare("SELECT registry_reason,vulnerability,COUNT(*) AS total FROM households
        WHERE registry_reason IN ('Affected','Monitored and affected') AND (?='' OR barangay=?)
        GROUP BY registry_reason,vulnerability ORDER BY registry_reason,vulnerability");
    $query->execute([$area,$area]);
    $householdDetails = $query->fetchAll();
    [$arrivalRange, $arrivalArgs] = $dateRange('arrived_at');
    $query = $pdo->prepare("SELECT c.name,c.barangay,c.status,c.capacity,
        COALESCE(current_stays.staying,0) AS staying,COALESCE(period_arrivals.arrivals,0) AS arrivals
        FROM evacuation_centers c
        LEFT JOIN (SELECT center_id,COUNT(*) AS staying FROM evacuation_stays
            WHERE left_at IS NULL GROUP BY center_id) current_stays ON current_stays.center_id=c.id
        LEFT JOIN (SELECT center_id,COUNT(*) AS arrivals FROM evacuation_stays
            WHERE $arrivalRange GROUP BY center_id) period_arrivals ON period_arrivals.center_id=c.id
        WHERE (?='' OR c.barangay=?) ORDER BY c.name");
    $query->execute([...$arrivalArgs,$area,$area]);
    $centers = $query->fetchAll();
    [$incidentRange, $incidentArgs] = $dateRange('reported_at');
    $query = $pdo->prepare("SELECT incident_type,status,COUNT(*) AS total FROM incidents
        WHERE $incidentRange AND (?='' OR barangay=?) GROUP BY incident_type,status ORDER BY incident_type,status");
    $query->execute([...$incidentArgs,$area,$area]);
    $incidents = $query->fetchAll();
    [$goodsRange, $goodsArgs] = $dateRange('d.distributed_at');
    $query = $pdo->prepare("SELECT g.name,g.unit,SUM(i.quantity) AS issued FROM distribution_items i
        JOIN relief_goods g ON g.id=i.good_id JOIN relief_distributions d ON d.id=i.distribution_id
        WHERE $goodsRange AND (?='' OR d.barangay_at_issue=?)
        GROUP BY g.id ORDER BY g.name,g.unit");
    $query->execute([...$goodsArgs,$area,$area]);
    $goods = $query->fetchAll();
    [$pointDistributionRange, $pointDistributionArgs] = $dateRange('d.distributed_at');
    $query = $pdo->prepare("SELECT p.name,d.barangay_at_issue AS barangay,
            COUNT(*) AS receipts,COUNT(DISTINCT d.household_id) AS households
        FROM relief_distributions d JOIN relief_points p ON p.id=d.point_id
        WHERE $pointDistributionRange AND (?='' OR d.barangay_at_issue=?)
        GROUP BY p.id,p.name,d.barangay_at_issue ORDER BY p.name,barangay");
    $query->execute([...$pointDistributionArgs,$area,$area]);
    $distributionPoints = $query->fetchAll();
    [$distributionSummaryRange, $distributionSummaryArgs] = $dateRange('distributed_at');
    $query = $pdo->prepare("SELECT COUNT(*) AS receipts,COUNT(DISTINCT household_id) AS households
        FROM relief_distributions WHERE $distributionSummaryRange AND (?='' OR barangay_at_issue=?)");
    $query->execute([...$distributionSummaryArgs,$area,$area]);
    $distributionSummary = $query->fetch();
    [$unconfirmedRange, $unconfirmedArgs] = $dateRange('distributed_at');
    $query = $pdo->prepare("SELECT COUNT(*) FROM relief_distributions WHERE barangay_at_issue IS NULL AND $unconfirmedRange");
    $query->execute($unconfirmedArgs);
    $unconfirmed = (int)$query->fetchColumn();
}
$export = $_GET['export'] ?? '';
if (!$error && $export === 'pdf') {
    $period = ($dates['from'] ?: 'Earliest record') . ' through ' . ($dates['through'] ?: 'latest record');
    $place = $area ?: 'All barangays';
    require_once __DIR__ . '/includes/report_pdf.php';
    $pdf = startOfficeReport('Disaster summary');
    $pdf->fields(['Report reference'=>'ATOK-SUM', 'Barangay'=>$place, 'Selected period'=>$period]);
    $pdf->lineText('Current registry and occupancy are measured at generation. Period counts use the selected dates.');
    $pdf->section('I. Affected households');
    $pdf->lineText('Registered as affected now: ' . $affected);
    foreach ($householdDetails as $row) {
        $pdf->fields(['Registry classification'=>$row['registry_reason'], 'Additional assistance'=>$row['vulnerability'], 'Households'=>$row['total']]);
    }
    if (!$householdDetails) {
        $pdf->lineText('No affected household records in this selection.');
    }
    $pdf->section('II. Evacuation center occupancy');
    foreach ($centers as $center) {
        $pdf->fields(['Center'=>$center['name'], 'Barangay'=>$center['barangay'], 'Status'=>$center['status']]);
        $pdf->fields(['Person capacity'=>$center['capacity'], 'People staying now'=>$center['staying'], 'Arrivals in period'=>$center['arrivals']]);
    }
    if (!$centers) {
        $pdf->lineText('No centers registered for this selection.');
    }
    $pdf->section('III. Incidents reported during the period');
    foreach ($incidents as $row) {
        $pdf->fields(['Incident type / status'=>$row['incident_type'].' / '.$row['status'], 'Reports'=>$row['total']]);
    }
    if (!$incidents) {
        $pdf->lineText('Reports during the period: 0');
    }
    $pdf->section('IV. Distribution activity by location');
    $pdf->fields(['Receipts'=>$distributionSummary['receipts'], 'Households issued aid'=>$distributionSummary['households']]);
    foreach ($distributionPoints as $row) {
        $pdf->fields(['Distribution point'=>$row['name'], 'Barangay'=>$row['barangay'] ?? 'Unconfirmed',
            'Receipts / households'=>$row['receipts'].' / '.$row['households']]);
    }
    if (!$distributionPoints) {
        $pdf->lineText('No distribution receipts for this selection.');
    }
    $pdf->section('V. Relief goods distributed during the period');
    foreach ($goods as $row) {
        $pdf->lineText($row['name'].': '.$row['issued'].' '.$row['unit']);
    }
    if (!$goods) {
        $pdf->lineText('Recorded distributions for this selection: 0');
    }
    if ($unconfirmed) {
        $pdf->lineText('Municipal distributions with unconfirmed barangay (included only in All barangays): '.$unconfirmed);
    }
    $pdf->signatureBlock(['Prepared by', 'Reviewed by', 'Approved by']);
    $pdf->Output('I','atok-disaster-summary.pdf');
    exit;
}
// Individual records are for the guarded staff screen only.
if (!$error) {
    $query = $pdo->prepare("SELECT h.barangay,h.address,h.registry_reason,h.vulnerability,m.full_name AS representative
        FROM households h
        LEFT JOIN household_representatives r ON r.household_id=h.id
        LEFT JOIN household_members m ON m.id=r.member_id
        WHERE h.registry_reason IN ('Affected','Monitored and affected') AND (?='' OR h.barangay=?)
        ORDER BY h.barangay,h.address,h.id");
    $query->execute([$area,$area]);
    $affectedHouseholds = $query->fetchAll();
    [$incidentDetailRange, $incidentDetailArgs] = $dateRange('reported_at');
    $query = $pdo->prepare("SELECT id,incident_type,status,barangay,location,description,contact_name,contact_phone,source,reported_at
        FROM incidents WHERE $incidentDetailRange AND (?='' OR barangay=?)
        ORDER BY reported_at DESC,id DESC");
    $query->execute([...$incidentDetailArgs,$area,$area]);
    $incidentDetails = $query->fetchAll();
    [$distributionRecordRange, $distributionRecordArgs] = $dateRange('d.distributed_at');
    $query = $pdo->prepare("SELECT d.id,d.distributed_at,d.receiver,d.barangay_at_issue,h.address,
            p.name AS point_name,m.full_name AS representative,g.name AS good_name,g.unit,i.quantity
        FROM relief_distributions d
        JOIN households h ON h.id=d.household_id
        JOIN relief_points p ON p.id=d.point_id
        LEFT JOIN household_representatives r ON r.household_id=h.id
        LEFT JOIN household_members m ON m.id=r.member_id
        LEFT JOIN distribution_items i ON i.distribution_id=d.id
        LEFT JOIN relief_goods g ON g.id=i.good_id
        WHERE $distributionRecordRange AND (?='' OR d.barangay_at_issue=?)
        ORDER BY d.distributed_at DESC,d.id DESC,g.name,g.unit");
    $query->execute([...$distributionRecordArgs,$area,$area]);
    $distributionRecords = [];
    foreach ($query as $row) {
        $id = (int)$row['id'];
        if (!isset($distributionRecords[$id])) {
            $distributionRecords[$id] = [
                'distributed_at'=>$row['distributed_at'],
                'receiver'=>$row['receiver'],
                'barangay'=>$row['barangay_at_issue'],
                'address'=>$row['address'],
                'point_name'=>$row['point_name'],
                'representative'=>$row['representative'],
                'goods'=>[],
            ];
        }
        if ($row['good_name'] !== null) {
            $distributionRecords[$id]['goods'][] = $row['good_name'] . ' · ' . $row['quantity'] . ' ' . $row['unit'];
        }
    }
}
$pageTitle = 'Disaster summaries';
require __DIR__ . '/includes/header.php';
?>
<h1>Disaster summaries</h1>
<form method="get" class="mb-3 filter-panel">
    <label>From <input type="date" class="form-control" name="from" value="<?= h($dates['from']) ?>"></label>
    <label>Through (inclusive) <input type="date" class="form-control" name="through" value="<?= h($dates['through']) ?>"></label>
    <label>Barangay <select class="form-select" name="barangay">
        <option value="">All barangays</option>
        <?php foreach (BARANGAYS as $name): ?><option <?= $area === $name ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
    </select></label>
    <button class="btn btn-primary">Apply filters</button>
</form>
<?php if ($error): ?>
    <p role="alert"><?= h($error) ?></p>
<?php else: ?>
    <p>
        <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="?<?= h(http_build_query($dates + ['barangay'=>$area,'export'=>'pdf'])) ?>">Print PDF disaster summary</a>
    </p>
    <h2 class="h4">Evacuation center occupancy and arrivals</h2>
    <p class="results-summary">Capacity and people staying are current values; arrivals use the selected date period.</p>
    <div class="table-responsive"><table class="table record-list-table">
        <thead><tr><th>Center</th><th>Barangay</th><th>Status now</th><th>Person capacity</th><th>People staying now</th><th>Arrivals during selected period</th></tr></thead>
        <tbody>
            <?php foreach ($centers as $row): ?>
                <tr><td><?= h($row['name']) ?></td><td><?= h($row['barangay']) ?></td><td><span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span></td><td><?= (int)$row['capacity'] ?></td><td><?= (int)$row['staying'] ?></td><td><?= (int)$row['arrivals'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$centers): ?><p>No centers for this selection.</p><?php endif; ?>
    <h2 class="h4 mt-4">Incidents reported during selected period</h2>
    <?php if (!$incidents): ?><p>No reports in this period.</p><?php endif; ?>
    <?php if ($incidents): ?><div class="table-responsive"><table class="table">
        <thead><tr><th>Incident type</th><th>Status</th><th>Reports</th></tr></thead>
        <tbody><?php foreach ($incidents as $row): ?>
            <tr><td><?= h($row['incident_type']) ?></td><td><span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span></td><td><?= (int)$row['total'] ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    <h3 class="h5 mt-3">Individual incident records</h3>
    <?php if (!$incidentDetails): ?><p>No individual incident records for this period.</p><?php else: ?>
        <div class="table-responsive"><table class="table record-list-table">
            <thead><tr><th>Reference / date</th><th>Type / status</th><th>Barangay</th><th>Location and report details</th></tr></thead>
            <tbody><?php foreach ($incidentDetails as $incident): ?>
                <?php $reference = 'ATOK-IR-' . substr($incident['reported_at'], 0, 4) . '-' . str_pad($incident['id'], 4, '0', STR_PAD_LEFT); ?>
                <tr>
                    <td><strong><?= h($reference) ?></strong><br><?= h($incident['reported_at']) ?></td>
                    <td><?= h($incident['incident_type']) ?><br><span class="status-badge status-<?= strtolower(h($incident['status'])) ?>"><?= h($incident['status']) ?></span></td>
                    <td><?= h($incident['barangay'] ?? 'Awaiting confirmation') ?></td>
                    <td>
                        <strong><?= h($incident['location']) ?></strong>
                        <details class="record-details">
                            <summary>View reporter and description</summary>
                            <p><strong>Reporter:</strong> <?= h($incident['contact_name']) ?> · <?= h($incident['contact_phone']) ?> · <?= h($incident['source']) ?></p>
                            <p><?= nl2br(h($incident['description'])) ?></p>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <h2 class="h4 mt-4">Affected household classifications</h2>
    <p>Current registry counts, not restricted to the selected activity dates.</p>
    <?php if (!$householdDetails): ?><p>No affected household records in this selection.</p><?php else: ?>
        <div class="table-responsive"><table class="table record-list-table">
            <thead><tr><th>Registry classification</th><th>Additional assistance</th><th>Households</th></tr></thead>
            <tbody><?php foreach ($householdDetails as $row): ?>
                <tr><td><?= h($row['registry_reason']) ?></td><td><?= h($row['vulnerability']) ?></td><td><?= (int)$row['total'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <h3 class="h5 mt-3">Affected household records</h3>
    <?php if (!$affectedHouseholds): ?><p>No households are currently classified as affected in this selection.</p><?php else: ?>
        <div class="table-responsive"><table class="table record-list-table">
            <thead><tr><th>Representative</th><th>Barangay</th><th>Address</th><th>Registry classification</th><th>Assistance assessment</th></tr></thead>
            <tbody><?php foreach ($affectedHouseholds as $household): ?>
                <tr>
                    <td><?= h($household['representative'] ?? 'Not selected') ?></td>
                    <td><?= h($household['barangay']) ?></td>
                    <td><?= h($household['address']) ?></td>
                    <td><?= h($household['registry_reason']) ?></td>
                    <td><?= h($household['vulnerability']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <h2 class="h4 mt-4">Distribution activity by location</h2>
    <p><?= (int)$distributionSummary['receipts'] ?> receipts · <?= (int)$distributionSummary['households'] ?> unique households during selected period.</p>
    <?php if (!$distributionPoints): ?><p>No distribution receipts for this selection.</p><?php else: ?>
        <div class="table-responsive"><table class="table record-list-table">
            <thead><tr><th>Distribution point</th><th>Barangay at issue</th><th>Receipts</th><th>Households</th></tr></thead>
            <tbody><?php foreach ($distributionPoints as $row): ?>
                <tr><td><?= h($row['name']) ?></td><td><?= h($row['barangay'] ?? 'Unconfirmed') ?></td><td><?= (int)$row['receipts'] ?></td><td><?= (int)$row['households'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <h3 class="h5 mt-3">Itemized distribution receipts</h3>
    <?php if (!$distributionRecords): ?><p>No itemized receipts recorded for this period.</p><?php else: ?>
        <div class="table-responsive"><table class="table record-list-table">
            <thead><tr><th>Date</th><th>Household</th><th>Barangay at issue</th><th>Received by / point</th><th>Goods issued</th></tr></thead>
            <tbody><?php foreach ($distributionRecords as $record): ?>
                <tr>
                    <td><?= h($record['distributed_at']) ?></td>
                    <td><?= h(($record['representative'] ?? 'Representative not selected').' · '.$record['address']) ?></td>
                    <td><?= h($record['barangay'] ?? 'Unconfirmed') ?></td>
                    <td><?= h($record['receiver'].' · '.$record['point_name']) ?></td>
                    <td><ul class="mb-0"><?php foreach ($record['goods'] as $good): ?><li><?= h($good) ?></li><?php endforeach; ?></ul></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <h2 class="h4 mt-4">Goods distributed during selected period</h2>
    <p>Grouped by good and unit, using the barangay recorded at issue. Older distributions with an unconfirmed barangay appear only under All barangays.</p>
    <?php if ($unconfirmed): ?><p>Municipal distributions with unconfirmed barangay in this period: <?= $unconfirmed ?>.</p><?php endif; ?>
    <?php if (!$goods): ?><p class="empty-state">No goods distributed in this period.</p><?php endif; ?>
    <?php if ($goods): ?><ul class="summary-period-list"><?php foreach ($goods as $row): ?><li><?= h($row['name']) ?> · <?= (int)$row['issued'] ?> <?= h($row['unit']) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php endif; ?>
<a class="btn btn-outline-secondary" href="dashboard/staff.php">Return to dashboard</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
