<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
requireRole($user['role']);
$types = ['Flooding', 'Fire', 'Landslide', 'Road obstruction', 'Other emergency'];
$areas = ['Abiang', 'Caliking', 'Cattubo', 'Naguey', 'Paoay', 'Pasdong', 'Poblacion', 'Topdac'];

function readIncident($pdo, $user, $id)
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
    if (!$report) {
        http_response_code(404);
        exit('Report not found.');
    }
    return $report;
}
