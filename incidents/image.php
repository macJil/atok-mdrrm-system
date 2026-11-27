<?php
require __DIR__ . '/access.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$report = readIncident($pdo, $user, $id);
$query = $pdo->prepare('SELECT mime,image_data FROM incident_images WHERE incident_id=?');
$query->execute([$id]);
$image = $query->fetch();
if (!$image) {
    http_response_code(404);
    exit('Image not found.');
}
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $image['mime']);
echo $image['image_data'];
