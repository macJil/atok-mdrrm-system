<?php
require __DIR__ . '/access.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$sql = 'SELECT i.*, EXISTS(SELECT 1 FROM incident_images p WHERE p.incident_id=i.id) AS has_image FROM incidents i WHERE i.id=?';
$args = [$id];
if ($user['role'] === 'resident') {
    $sql .= " AND i.submitted_by=? AND i.source='Resident'";
    $args[] = $user['id'];
}
$query = $pdo->prepare($sql);
$query->execute($args);
$report = $query->fetch();
if (!$report) {
    http_response_code(404);
    exit('Report not found.');
}
$reference = 'ATOK-IR-' . substr($report['reported_at'], 0, 4) . '-' . str_pad($report['id'], 4, '0', STR_PAD_LEFT);
