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
    $statuses = ['Pending','Verified','Responding','Resolved','Rejected'];
    $counts = array_fill_keys(INCIDENT_TYPES, array_fill_keys($statuses, 0));
    foreach ($query as $row) {
        $counts[$row['incident_type']][$row['status']] = (int)$row['total'];
    }
    [$goodsRange, $goodsArgs] = $dateRange('d.distributed_at');
    $query = $pdo->prepare("SELECT g.name,g.unit,SUM(i.quantity) AS issued FROM distribution_items i
        JOIN relief_goods g ON g.id=i.good_id JOIN relief_distributions d ON d.id=i.distribution_id
        WHERE $goodsRange AND (?='' OR d.barangay_at_issue=?)
        GROUP BY g.id ORDER BY g.name,g.unit");
    $query->execute([...$goodsArgs,$area,$area]);
    $goods = $query->fetchAll();
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
    $pdf->section('II. Evacuation center occupancy');
    foreach ($centers as $center) {
        $pdf->fields(['Center'=>$center['name'], 'Barangay'=>$center['barangay'], 'Status'=>$center['status']]);
        $pdf->fields(['Person capacity'=>$center['capacity'], 'People staying now'=>$center['staying'], 'Arrivals in period'=>$center['arrivals']]);
    }
    if (!$centers) {
        $pdf->lineText('No centers registered for this selection.');
    }
    if ($pdf->GetY() > 205) {
        $pdf->AddPage();
    }
    $pdf->section('III. Incidents reported during the period');
    $widths = [38,22,22,25,24,23,22];
    $pdf->SetFont('Helvetica','B',9);
    foreach (array_merge(['Type'], $statuses, ['Total']) as $column=>$label) {
        $pdf->Cell($widths[$column],7,$label,1);
    }
    $pdf->Ln();
    $pdf->SetFont('Helvetica','',9);
    foreach ($counts as $type=>$numbers) {
        $pdf->Cell($widths[0],7,$type,1);
        foreach ($statuses as $column=>$status) {
            $pdf->Cell($widths[$column+1],7,(string)$numbers[$status],1);
        }
        $pdf->Cell(22,7,(string)array_sum($numbers),1,1);
    }
    $pdf->section('IV. Relief distributed during selected period');
    $pdf->fields(['Receipts'=>$distributionSummary['receipts'], 'Households issued aid'=>$distributionSummary['households']]);
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
    <button class="btn btn-outline-primary" name="export" value="pdf" formtarget="_blank">Print summary</button>
</form>
<?php if ($error): ?>
    <p role="alert"><?= h($error) ?></p>
<?php else: ?>
    <h2 class="h4">Households currently marked affected: <?= (int)$affected ?></h2>
    <h2 class="h4" id="occupancy">Evacuation center occupancy and arrivals</h2>
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
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Type</th><?php foreach ($statuses as $status): ?><th><?= h($status) ?></th><?php endforeach; ?><th>Total</th></tr></thead>
        <tbody>
            <?php foreach ($counts as $type=>$numbers): ?>
                <tr><th scope="row"><?= h($type) ?></th>
                    <?php foreach ($statuses as $status): ?><td><?= $numbers[$status] ?></td><?php endforeach; ?>
                    <td><?= array_sum($numbers) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <h2 class="h4 mt-4">Goods distributed during selected period</h2>
    <p><?= (int)$distributionSummary['receipts'] ?> distributions · <?= (int)$distributionSummary['households'] ?> distinct households served</p>
    <p>Grouped by good and unit, using the barangay recorded at issue. Older distributions with an unconfirmed barangay appear only under All barangays.</p>
    <?php if ($unconfirmed): ?><p>Municipal distributions with unconfirmed barangay in this period: <?= $unconfirmed ?>.</p><?php endif; ?>
    <?php if (!$goods): ?><p class="empty-state">No goods distributed in this period.</p><?php endif; ?>
    <?php if ($goods): ?><ul class="summary-period-list"><?php foreach ($goods as $row): ?><li><?= h($row['name']) ?> · <?= (int)$row['issued'] ?> <?= h($row['unit']) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php endif; ?>
<a class="btn btn-outline-secondary" href="dashboard/staff.php">Return to dashboard</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
