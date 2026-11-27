<?php
require __DIR__ . '/../includes/session.php';
requireRole('staff');
$areas = ['Abiang','Caliking','Cattubo','Naguey','Paoay','Pasdong','Poblacion','Topdac'];
$area = in_array($_GET['barangay'] ?? '', $areas, true) ? $_GET['barangay'] : '';
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
if (!$error) {
    $from = $dates['from'] ?: '1000-01-01';
    $through = $dates['through'] ?: '9999-12-31';
    $query = $pdo->prepare("SELECT COUNT(*) FROM households WHERE registry_reason IN ('Affected','Monitored and affected')
        AND (?='' OR barangay=?)");
    $query->execute([$area,$area]);
    $affected = $query->fetchColumn();
    $query = $pdo->prepare("SELECT c.name,c.status,c.capacity,
        COUNT(CASE WHEN s.left_at IS NULL THEN s.id END) AS staying,
        COUNT(CASE WHEN DATE(s.arrived_at) BETWEEN ? AND ? THEN s.id END) AS arrivals
        FROM evacuation_centers c LEFT JOIN evacuation_stays s ON c.id=s.center_id
        WHERE (?='' OR c.barangay=?) GROUP BY c.id ORDER BY c.name");
    $query->execute([$from,$through,$area,$area]);
    $centers = $query->fetchAll();
    $query = $pdo->prepare("SELECT incident_type,status,COUNT(*) AS total FROM incidents
        WHERE DATE(reported_at) BETWEEN ? AND ? AND (?='' OR barangay=?) GROUP BY incident_type,status ORDER BY incident_type,status");
    $query->execute([$from,$through,$area,$area]);
    $incidents = $query->fetchAll();
    $query = $pdo->prepare("SELECT g.name,g.unit,SUM(i.quantity) AS issued FROM distribution_items i
        JOIN relief_goods g ON g.id=i.good_id JOIN relief_distributions d ON d.id=i.distribution_id
        JOIN households h ON h.id=d.household_id WHERE DATE(d.distributed_at) BETWEEN ? AND ? AND (?='' OR h.barangay=?)
        GROUP BY g.id ORDER BY g.name,g.unit");
    $query->execute([$from,$through,$area,$area]);
    $goods = $query->fetchAll();
}
$export = $_GET['export'] ?? '';
if (!$error && in_array($export, ['pdf','csv'], true)) {
    $period = ($dates['from'] ?: 'Earliest record') . ' through ' . ($dates['through'] ?: 'latest record');
    $place = $area ?: 'All barangays';
    $rows = [['Households','Affected now','households',$affected]];
    foreach ($centers as $center) {
        foreach (['capacity'=>'Capacity now','staying'=>'Staying now','arrivals'=>'Period arrivals'] as $key=>$label) {
            $rows[] = [$label,$center['name'],'people',$center[$key]];
        }
    }
    foreach ($incidents as $row) {
        $rows[] = ['Period incidents',$row['incident_type'].' / '.$row['status'],'reports',$row['total']];
    }
    foreach ($goods as $row) {
        $rows[] = ['Period distributions',$row['name'],$row['unit'],$row['issued']];
    }
    if (!$centers) {
        $rows[] = ['Centers','Registered','centers',0];
    }
    if (!$incidents) {
        $rows[] = ['Period incidents','All types','reports',0];
    }
    if (!$goods) {
        $rows[] = ['Period distributions','No recorded distributions','distributions',0];
    }
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="atok-disaster-summary.csv"');
        $file = fopen('php://output','wb');
        fputcsv($file,['Barangay','Period','Measure','Record','Unit','Total'], ',', '"', '');
        foreach ($rows as $row) {
            $fields = array_merge([$place,$period],$row);
            foreach ($fields as &$value) {
                if (preg_match('/^\s*[=+@-]/u', (string)$value)) {
                    $value = "'" . $value;
                }
            }
            unset($value);
            fputcsv($file,$fields, ',', '"', '');
        }
        fclose($file);
    } else {
        require_once __DIR__ . '/../includes/report_pdf.php';
        $pdf = startOfficeReport('Disaster summary');
        $pdf->lineText('Reference: ATOK-SUM / '.$place.' / '.$period);
        $pdf->lineText('Current registry and occupancy are measured at generation. Period counts use the selected dates.');
        $section = '';
        foreach ($rows as [$measure,$record,$unit,$quantity]) {
            if ($section !== $measure) {
                $pdf->section($measure);
                $section = $measure;
            }
            $pdf->lineText($record.'  |  '.$quantity.' '.$unit);
        }
        $pdf->Output('D','atok-disaster-summary.pdf');
    }
    exit;
}
$pageTitle = 'Disaster summaries';
require __DIR__ . '/../includes/header.php';
?>
<h1>Disaster summaries</h1>
<form method="get" class="mb-3">
    <label>From <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($dates['from']) ?>"></label>
    <label>Through (inclusive) <input type="date" class="form-control" name="through" value="<?= htmlspecialchars($dates['through']) ?>"></label>
    <label>Barangay <select class="form-select" name="barangay">
        <option value="">All barangays</option>
        <?php foreach ($areas as $name): ?><option <?= $area === $name ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
    </select></label>
    <button class="btn btn-primary">Apply filters</button>
</form>
<?php if ($error): ?>
    <p role="alert"><?= htmlspecialchars($error) ?></p>
<?php else: ?>
    <p>
        <a class="btn btn-outline-primary" href="?<?= htmlspecialchars(http_build_query($dates + ['barangay'=>$area,'export'=>'pdf'])) ?>">Download summary PDF</a>
        <a class="btn btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query($dates + ['barangay'=>$area,'export'=>'csv'])) ?>">Download totals CSV</a>
    </p>
    <h2 class="h4">Affected households now: <?= (int)$affected ?></h2>
    <p>Based on the current household registry. The dates below apply to activity.</p>
    <h2 class="h4">Evacuation center occupancy and arrivals</h2>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Center</th><th>Status now</th><th>Person capacity</th><th>People staying now</th><th>Arrivals during selected period</th></tr></thead>
        <tbody>
            <?php foreach ($centers as $row): ?>
                <tr><td><?= htmlspecialchars($row['name']) ?></td><td><?= $row['status'] ?></td><td><?= (int)$row['capacity'] ?></td><td><?= (int)$row['staying'] ?></td><td><?= (int)$row['arrivals'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$centers): ?><p>No centers for this selection.</p><?php endif; ?>
    <h2 class="h4">Incidents reported during selected period</h2>
    <?php if (!$incidents): ?><p>No reports in this period.</p><?php endif; ?>
    <?php foreach ($incidents as $row): ?><p><?= htmlspecialchars($row['incident_type'].' · '.$row['status'].' · '.$row['total']) ?></p><?php endforeach; ?>
    <h2 class="h4">Goods distributed during selected period</h2>
    <p>Grouped by good and unit, using the recipient household’s barangay.</p>
    <?php if (!$goods): ?><p>No goods distributed in this period.</p><?php endif; ?>
    <?php foreach ($goods as $row): ?><p><?= htmlspecialchars($row['name'].' · '.$row['issued'].' '.$row['unit']) ?></p><?php endforeach; ?>
<?php endif; ?>
<a class="btn btn-outline-secondary" href="../dashboard/staff.php">Return to dashboard</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
