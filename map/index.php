<?php
require __DIR__ . '/../includes/session.php';
$user = requireLogin();
$user = requireRole($user['role']);
require_once __DIR__ . '/boundary.php';

$area = in_array($_GET['barangay'] ?? '', BARANGAYS, true) ? $_GET['barangay'] : '';
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
    if ($area !== '') {
        $sql .= ' AND barangay = ?';
    }
    $rows = $pdo->prepare($sql . ' ORDER BY id');
    $rows->execute($area === '' ? [] : [$area]);
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
<form class="dashboard-filter filter-panel" method="get">
    <label>Barangay
        <select class="form-select" name="barangay">
            <option value="">All barangays</option>
            <?php foreach (BARANGAYS as $name): ?>
                <option <?= $area === $name ? 'selected' : '' ?>><?= h($name) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn btn-primary">Apply filter</button>
</form>
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
<?php [$page, $offset, $pages] = pageWindow(count($points), (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT), 5); ?>
<details id="mapped-list" <?= isset($_GET['page']) || !$points ? 'open' : '' ?>>
<summary>Mapped locations (<?= count($points) ?>)</summary>
<?php if (!$points): ?><p>No mapped locations for <?= h($area ?: 'this selection') ?>.</p><?php endif; ?>
<ul class="dashboard-list">
    <?php foreach (array_slice($points, $offset, 5, true) as $index => $row): ?>
        <li><?= h($row['kind'] === 'households' ? 'Household · '.$row['barangay'].' · '.$row['label'] : $row['label'].' · '.($row['barangay'] ?? 'Barangay awaiting confirmation').' · '.$row['status']) ?>
            <button type="button" hidden class="btn btn-sm btn-outline-secondary" data-point="<?= $index ?>">Show on map</button>
        </li>
    <?php endforeach; ?>
</ul>
<?php if ($pages > 1): ?>
    Page <?= $page ?> of <?= $pages ?>
    <?php if ($page > 1): ?><a href="?barangay=<?= urlencode($area) ?>&amp;page=<?= $page-1 ?>#mapped-list">Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?><a href="?barangay=<?= urlencode($area) ?>&amp;page=<?= $page+1 ?>#mapped-list">Next</a><?php endif; ?>
<?php endif; ?>
</details>
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<script src="../assets/vendor/leaflet/leaflet.js"></script>
<script src="../assets/js/map.js?v=4"></script>
<script>
if (window.L) {
    const boundary = <?= json_encode(atokBoundary(), JSON_HEX_TAG) ?>;
    const map = L.map('community-map').setView(boundary.properties.opening_view, 14);
    drawAtok(map, boundary, document.querySelector('#show-atok'));
    const markers = L.featureGroup().addTo(map);
    const showLocations = document.querySelector('#show-locations');
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).on('tileerror', () => { document.querySelector('#mapped-list').open = true; document.querySelector('#map-status').textContent = 'Map tiles unavailable. See the locations listed below.'; }).addTo(map);
    const points = <?= json_encode($points, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const bounds = L.latLngBounds(points.map(point => [point.latitude, point.longitude]));
    const pointMarkers = new Map();
    function drawMarkers() {
        markers.clearLayers();
        pointMarkers.clear();
        const groups = [];
        for (const point of points) {
            const pixel = map.project([point.latitude, point.longitude]);
            let group = groups.find(item => item.pixel.distanceTo(pixel) < 36);
            if (!group) {
                group = {pixel, points: []};
                groups.push(group);
            }
            group.points.push(point);
        }
        for (const group of groups) {
            const first = group.points[0];
            const list = document.createElement('ul');
            for (const point of group.points) {
                const row = document.createElement('li');
                row.textContent = [point.label, point.barangay, point.status].filter(Boolean).join(' · ');
                list.appendChild(row);
            }
            const count = group.points.length;
            const type = first.kind === 'centers' ? first.status : first.label;
            const marker = L.marker([first.latitude, first.longitude], {
                title: count === 1 ? list.textContent : count + ' records near this point',
                icon: recordPin(first.kind, type, count)
            }).bindPopup(list, {maxHeight: 180}).addTo(markers);
            for (const point of group.points) pointMarkers.set(point, marker);
        }
    }
    map.on('zoomend', drawMarkers);
    drawMarkers();
    for (const button of document.querySelectorAll('[data-point]')) {
        button.hidden = false;
        button.addEventListener('click', () => {
            const point = points[Number(button.dataset.point)];
            map.setView([point.latitude, point.longitude], 18, {animate:false});
            pointMarkers.get(point).openPopup();
            map.getContainer().scrollIntoView({block:'center'});
        });
    }
    showLocations.disabled = !bounds.isValid();
    function fitLocations() {
        map.fitBounds(bounds, {padding: [24, 24], maxZoom: 16});
    }
    showLocations.addEventListener('click', fitLocations);
    if (bounds.isValid() && <?= isset($_GET['barangay']) ? 'true' : 'false' ?>) fitLocations();
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
