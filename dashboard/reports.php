<?php
require __DIR__ . '/../includes/session.php';
$user = requireRole('staff');
$area = in_array($_GET['barangay'] ?? '', BARANGAYS, true) ? $_GET['barangay'] : '';
$export = is_string($_GET['export'] ?? null) ? $_GET['export'] : '';
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
$affected = 0;
$affectedTotal = 0;
$householdSummary = [];
$centers = [];
$stayingTotal = 0;
$incidents = [];
$incidentTotal = 0;
$goods = [];
$distributionPoints = [];
$distributionSummary = ['receipts'=>0,'households'=>0];
$unconfirmed = 0;
$periodLabel = ($dates['from'] ?: 'Earliest record') . ' through ' . ($dates['through'] ?: 'Latest record');
$placeLabel = $area ?: 'All barangays';
if (!$error) {
    $query = $pdo->prepare("SELECT COUNT(*) FROM households WHERE registry_reason IN ('Affected','Monitored and affected')
        AND (?='' OR barangay=?)");
    $query->execute([$area,$area]);
    $affected = $query->fetchColumn();
    $query = $pdo->prepare("SELECT registry_reason,vulnerability,COUNT(*) AS total FROM households
        WHERE registry_reason IN ('Affected','Monitored and affected') AND (?='' OR barangay=?)
        GROUP BY registry_reason,vulnerability ORDER BY registry_reason,vulnerability");
    $query->execute([$area,$area]);
    $householdSummary = $query->fetchAll();
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
    $affectedTotal = (int)$affected;
    $stayingTotal = array_sum(array_map(static fn($row): int => (int)$row['staying'], $centers));
    $incidentTotal = array_sum(array_map(static fn($row): int => (int)$row['total'], $incidents));
}
if (!$error && $export === 'pdf') {
    require_once __DIR__ . '/../includes/report_pdf.php';
    $pdf = startOfficeReport('Disaster summary');
    $pdf->fields(['Report reference'=>'ATOK-SUM', 'Barangay'=>$placeLabel, 'Selected period'=>$periodLabel]);
    $pdf->lineText('Current registry and occupancy figures are measured at generation. Incident and distribution activity uses the selected period. This aggregate report does not include personal or household-identifying details.');
    $pdf->fields([
        'Affected households'=>$affectedTotal,
        'Staying now'=>$stayingTotal,
        'Incidents'=>$incidentTotal,
        'Receipts'=>$distributionSummary['receipts'],
    ]);
    $pdf->section('I. Affected households');
    $householdRows = array_map(static fn($row): array => [
        $row['registry_reason'], $row['vulnerability'], (int)$row['total'],
    ], $householdSummary);
    if ($householdRows) {
        $pdf->table(['Registry classification', 'Assistance assessment', 'Households'], $householdRows, [66, 78, 32]);
    } else {
        $pdf->lineText('No affected household summary for this selection.');
    }
    $pdf->section('II. Evacuation center occupancy');
    $centerRows = array_map(static fn($center): array => [
        $center['name'], $center['barangay'], $center['status'], (int)$center['capacity'],
        (int)$center['staying'], (int)$center['arrivals'],
    ], $centers);
    if ($centerRows) {
        $pdf->table(['Center', 'Barangay', 'Status', 'Capacity', 'Staying now', 'Arrivals'], $centerRows, [50, 34, 28, 20, 22, 22]);
    } else {
        $pdf->lineText('No centers registered for this selection.');
    }
    $pdf->section('III. Incidents reported during the period');
    $incidentRows = array_map(static fn($row): array => [
        $row['incident_type'], $row['status'], (int)$row['total'],
    ], $incidents);
    if ($incidentRows) {
        $pdf->table(['Incident type', 'Status', 'Reports'], $incidentRows, [78, 66, 32]);
    } else {
        $pdf->lineText('Reports during the period: 0');
    }
    $pdf->section('IV. Distribution activity by location');
    $pdf->fields(['Receipts'=>$distributionSummary['receipts'], 'Households issued aid'=>$distributionSummary['households']]);
    $pointRows = array_map(static fn($row): array => [
        $row['name'], $row['barangay'] ?? 'Unconfirmed', (int)$row['receipts'], (int)$row['households'],
    ], $distributionPoints);
    if ($pointRows) {
        $pdf->table(['Distribution point', 'Barangay at issue', 'Receipts', 'Households'], $pointRows, [62, 54, 30, 30]);
    } else {
        $pdf->lineText('No distribution receipts for this selection.');
    }
    $pdf->section('V. Relief goods distributed during the period');
    $goodRows = array_map(static fn($row): array => [
        $row['name'], (int)$row['issued'], $row['unit'],
    ], $goods);
    if ($goodRows) {
        $pdf->table(['Relief good', 'Quantity', 'Unit'], $goodRows, [92, 42, 42]);
    } else {
        $pdf->lineText('Recorded distributions for this selection: 0');
    }
    if ($unconfirmed) {
        $pdf->lineText('Municipal distributions with unconfirmed barangay (included only in All barangays): '.$unconfirmed);
    }
    $pdf->signatureBlock(['Prepared by', 'Reviewed by', 'Approved by']);
    $pdf->Output('I','atok-disaster-summary.pdf');
    exit;
}
$pageTitle = 'Disaster Reports · Staff dashboard';
require __DIR__ . '/../includes/header.php';
?>
<?php require __DIR__ . '/tabs.php'; ?>
<p class="summary-intro">Review evacuation, incident, household, and relief activity. Choose a barangay and date range to focus the report.</p>
<form method="get" class="summary-filter filter-panel">
    <h2 class="h5">Report filters</h2>
    <div class="summary-filter-grid">
        <label>From <input type="date" class="form-control" name="from" value="<?= h($dates['from']) ?>"></label>
        <label>Through (inclusive) <input type="date" class="form-control" name="through" value="<?= h($dates['through']) ?>"></label>
        <label>Barangay <select class="form-select" name="barangay">
            <option value="">All barangays</option>
            <?php foreach (BARANGAYS as $name): ?><option <?= $area === $name ? 'selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?>
        </select></label>
    </div>
    <div class="summary-filter-actions">
        <button class="btn btn-primary">Apply filters</button>
        <button class="btn btn-outline-primary" name="export" value="pdf" formtarget="_blank">Print PDF</button>
        <a class="btn btn-outline-secondary" href="reports.php">Clear filters</a>
    </div>
</form>
<?php if ($error): ?>
    <p role="alert"><?= h($error) ?></p>
<?php else: ?>
    <section class="summary-selection" aria-label="Current report selection">
        <div><span class="summary-selection-label">Barangay</span><strong><?= h($placeLabel) ?></strong></div>
        <div><span class="summary-selection-label">Activity period</span><strong><?= h($periodLabel) ?></strong></div>
    </section>
    <section class="summary-metrics" aria-label="Summary overview">
        <div><span class="summary-metric-value"><?= $affectedTotal ?></span><span class="summary-metric-label">Affected households</span><span class="summary-metric-note">Current registry</span></div>
        <div><span class="summary-metric-value"><?= $stayingTotal ?></span><span class="summary-metric-label">People staying in centers</span><span class="summary-metric-note">Current occupancy</span></div>
        <div><span class="summary-metric-value"><?= $incidentTotal ?></span><span class="summary-metric-label">Incident reports</span><span class="summary-metric-note">Selected period</span></div>
        <div><span class="summary-metric-value"><?= (int)$distributionSummary['receipts'] ?></span><span class="summary-metric-label">Relief distribution receipts</span><span class="summary-metric-note"><?= (int)$distributionSummary['households'] ?> unique households · selected period</span></div>
    </section>
    <details class="summary-section">
        <summary>Evacuation center occupancy and arrivals</summary>
    <p class="results-summary">Capacity and people staying are current values. Arrivals use the selected date period.</p>
    <div class="table-responsive"><table class="table summary-table">
        <thead><tr><th>Center</th><th>Barangay</th><th>Status now</th><th>Person capacity</th><th>People staying now</th><th>Arrivals during selected period</th></tr></thead>
        <tbody>
            <?php foreach ($centers as $row): ?>
                <tr><td><?= h($row['name']) ?></td><td><?= h($row['barangay']) ?></td><td><span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span></td><td class="summary-count"><?= (int)$row['capacity'] ?></td><td class="summary-count"><?= (int)$row['staying'] ?></td><td class="summary-count"><?= (int)$row['arrivals'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$centers): ?><p class="empty-state">No centers for this selection.</p><?php endif; ?>
    </details>
    <details class="summary-section">
        <summary>Incidents reported during selected period</summary>
    <?php if (!$incidents): ?><p class="empty-state">No reports in this period.</p><?php endif; ?>
    <?php if ($incidents): ?><div class="table-responsive"><table class="table summary-table">
        <thead><tr><th>Incident type</th><th>Status</th><th>Reports</th></tr></thead>
        <tbody><?php foreach ($incidents as $row): ?>
            <tr><td><?= h($row['incident_type']) ?></td><td><span class="status-badge status-<?= strtolower(h($row['status'])) ?>"><?= h($row['status']) ?></span></td><td class="summary-count"><?= (int)$row['total'] ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </details>
    <details class="summary-section">
        <summary>Affected household classifications</summary>
    <p>Current registry counts, not restricted to the selected activity dates.</p>
    <?php if (!$householdSummary): ?><p class="empty-state">No affected household summary for this selection.</p><?php else: ?>
        <div class="table-responsive"><table class="table record-list-table summary-table">
            <thead><tr><th>Registry classification</th><th>Additional assistance</th><th>Households</th></tr></thead>
            <tbody><?php foreach ($householdSummary as $row): ?>
                <tr><td><?= h($row['registry_reason']) ?></td><td><?= h($row['vulnerability']) ?></td><td class="summary-count"><?= (int)$row['total'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    </details>
    <details class="summary-section">
        <summary>Distribution activity by location</summary>
    <p class="results-summary">During the selected period: <?= (int)$distributionSummary['receipts'] ?> receipts · <?= (int)$distributionSummary['households'] ?> unique households.</p>
    <?php if (!$distributionPoints): ?><p class="empty-state">No distribution receipts for this selection.</p><?php else: ?>
        <div class="table-responsive"><table class="table record-list-table summary-table">
            <thead><tr><th>Distribution point</th><th>Barangay at issue</th><th>Receipts</th><th>Households</th></tr></thead>
            <tbody><?php foreach ($distributionPoints as $row): ?>
                <tr><td><?= h($row['name']) ?></td><td><?= h($row['barangay'] ?? 'Unconfirmed') ?></td><td class="summary-count"><?= (int)$row['receipts'] ?></td><td class="summary-count"><?= (int)$row['households'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    </details>
    <details class="summary-section">
        <summary>Goods distributed during selected period</summary>
    <p>Grouped by good and unit, using the barangay recorded at issue. Older distributions with an unconfirmed barangay appear only under All barangays.</p>
    <?php if ($unconfirmed): ?><p>Municipal distributions with unconfirmed barangay in this period: <?= $unconfirmed ?>.</p><?php endif; ?>
    <?php if (!$goods): ?><p class="empty-state">No goods distributed in this period.</p><?php endif; ?>
    <?php if ($goods): ?><ul class="summary-period-list"><?php foreach ($goods as $row): ?><li><?= h($row['name']) ?> · <?= (int)$row['issued'] ?> <?= h($row['unit']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </details>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
