<?php
require_once __DIR__ . '/../includes/session.php';
$user = requireLogin();
$user = requireRole($user['role']);
$types = INCIDENT_TYPES;

function readIncident(PDO $pdo, array $user, int|false|null $id): array
{
    $sql = 'SELECT i.*, EXISTS(SELECT 1 FROM incident_images p WHERE p.incident_id=i.id) AS has_image FROM incidents i WHERE i.id=?';
    $args = [$id];
    if ($user['role'] === 'resident') {
        $sql .= " AND i.submitted_by=? AND i.source='Resident'";
        $args[] = $user['id'];
    }
    $query = $pdo->prepare($sql);
    $query->execute($args);
    $report = $query->fetch();
    if (!is_array($report)) {
        http_response_code(404);
        exit('Report not found.');
    }
    return $report;
}
