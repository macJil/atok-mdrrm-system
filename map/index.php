<?php
require __DIR__ . '/../includes/session.php';
$user = requireLogin();
$user = requireRole($user['role']);
require_once __DIR__ . '/boundary.php';

$points = [];
// Only these columns are sent to the map. A resident never receives a household row.
$queries = [
    'incidents' => "SELECT incident_type AS label, status, barangay, latitude, longitude FROM incidents
        WHERE status IN ('Verified','Responding','Resolved') AND latitude IS NOT NULL AND longitude IS NOT NULL",
    'centers' => "SELECT name AS label,status,barangay,latitude,longitude FROM evacuation_centers
        WHERE status='Open' AND latitude IS NOT NULL AND longitude IS NOT NULL",
    'relief_locations' => "SELECT name AS label,'Distribution point' AS status,barangay,latitude,longitude FROM relief_points
        WHERE active=1 AND latitude IS NOT NULL AND longitude IS NOT NULL",
];
if ($user['role'] === 'staff') {
    $queries['households'] = "SELECT registry_reason AS label,'Household' AS status,barangay,latitude,longitude FROM households
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
}
foreach ($queries as $kind => $sql) {
    $rows = $pdo->query($sql);
    foreach ($rows as $row) {
        $row['kind'] = $kind;
        $points[] = $row;
    }
}
$pageTitle = 'Community map';
require __DIR__ . '/../includes/header.php';
?>
<h1>Community map</h1>
<p>Open around Sayangan, Paoay. The outline is approximate.</p>
<?php require __DIR__ . '/../includes/map_icons.php'; ?>
<ul class="map-legend" aria-label="Map legend">
    <li><span class="map-legend-symbol"><?= mapIcon('flood') ?></span> Flooding</li>
    <li><span class="map-legend-symbol"><?= mapIcon('fire') ?></span> Fire</li>
    <li><span class="map-legend-symbol"><?= mapIcon('landslide') ?></span> Landslide</li>
    <li><span class="map-legend-symbol"><?= mapIcon('road') ?></span> Road obstruction</li>
    <li><span class="map-legend-symbol"><?= mapIcon('emergency') ?></span> Other emergency</li>
    <li><span class="map-legend-symbol"><?= mapIcon('center') ?></span> Open evacuation center</li>
    <li><span class="map-legend-symbol"><?= mapIcon('relief') ?></span> Active relief point</li>
    <?php if ($user['role'] === 'staff'): ?><li><span class="map-legend-symbol"><?= mapIcon('household') ?></span> Household (staff only)</li><?php endif; ?>
</ul>
<div class="d-flex flex-wrap gap-2 mb-2">
    <button class="btn btn-outline-secondary" id="show-atok" disabled>Show all Atok</button>
    <button class="btn btn-outline-secondary" id="show-locations" disabled>Show all mapped locations</button>
</div>
<div id="community-map" aria-label="Map of saved locations"></div>
<p id="map-status" role="status"></p>
<h2 class="h5">Mapped locations (<?= count($points) ?>)</h2>
<?php if (!$points): ?><p>No eligible locations have a saved pin yet.</p><?php endif; ?>
<ul class="dashboard-list">
    <?php foreach ($points as $row): ?>
        <li><?= h($row['label'] . ' · ' . ($row['barangay'] ?? 'Barangay awaiting confirmation') . ' · ' . $row['status']) ?></li>
    <?php endforeach; ?>
</ul>
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<script src="../assets/vendor/leaflet/leaflet.js"></script>
<script src="../assets/js/map.js?v=3"></script>
<script>
if (window.L) {
    const boundary = <?= json_encode(atokBoundary(), JSON_HEX_TAG) ?>;
    const map = L.map('community-map').setView(boundary.properties.opening_view, 14);
    drawAtok(map, boundary, document.querySelector('#show-atok'));
    const markers = L.featureGroup().addTo(map);
    const showLocations = document.querySelector('#show-locations');
    showLocations.addEventListener('click', () => {
        if (markers.getLayers().length) map.fitBounds(markers.getBounds(), { padding: [20, 20], maxZoom: 16 });
    });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).on('tileerror', () => document.querySelector('#map-status').textContent = 'Map tiles unavailable. See the locations listed below.').addTo(map);
    for (const point of <?= json_encode($points, JSON_HEX_TAG | JSON_HEX_AMP) ?>) {
        const text = document.createElement('span');
        text.textContent = [point.label, point.barangay, point.status].filter(Boolean).join(' · ');
        L.marker([point.latitude, point.longitude], {
            title: text.textContent,
            icon: recordPin(point.kind, point.kind === 'centers' ? point.status : point.label)
        }).bindPopup(text).addTo(markers);
    }
    showLocations.disabled = markers.getLayers().length === 0;
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
