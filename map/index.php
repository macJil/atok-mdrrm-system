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
$layers = [];
foreach (INCIDENT_TYPES as $type) {
    $layers[$type] = ['incidents', $type];
}
$layers['Open evacuation centers'] = ['centers', 'Open'];
$layers['Active relief points'] = ['relief_locations', 'Distribution point'];
if ($user['role'] === 'staff') {
    $layers['Households (staff only)'] = ['households', 'Household'];
}
$pageTitle = 'Community map';
require __DIR__ . '/../includes/header.php';
?>
<h1>Community map</h1>
<p>Open around Sayangan, Paoay. The outline is approximate.</p>
<div class="d-flex flex-wrap gap-2 mb-2">
    <button class="btn btn-outline-secondary" id="show-atok" disabled>Show all Atok</button>
    <button class="btn btn-outline-secondary" id="show-locations" disabled>Show all mapped locations</button>
</div>
<div id="community-map" aria-label="Map of saved locations"></div>
<p id="map-status" role="status"></p>
<fieldset class="mt-3">
    <legend class="h5">Visible map layers</legend>
    <div class="map-legend">
        <?php foreach ($layers as $label=>[$kind,$type]): ?>
            <label><input type="checkbox" data-kind="<?= h($kind) ?>" data-type="<?= h($type) ?>" checked disabled>
                <span class="legend-pin" aria-hidden="true"></span> <?= h($label) ?>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>
<h2 class="h5">Mapped locations (<span id="visible-count"><?= count($points) ?></span>)</h2>
<?php if (!$points): ?><p>No eligible locations have a saved pin yet.</p><?php endif; ?>
<ul class="dashboard-list">
    <?php foreach ($points as $row): ?>
        <li data-layer="<?= h($row['kind'] === 'incidents' ? $row['label'] : $row['kind']) ?>"><?= h($row['label'] . ' · ' . ($row['barangay'] ?? 'Barangay awaiting confirmation') . ' · ' . $row['status']) ?></li>
    <?php endforeach; ?>
</ul>
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
    showLocations.addEventListener('click', () => {
        if (markers.getLayers().length) map.fitBounds(markers.getBounds(), { padding: [20, 20], maxZoom: 16 });
    });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).on('tileerror', () => document.querySelector('#map-status').textContent = 'Map tiles unavailable. See the locations listed below.').addTo(map);
    const points = <?= json_encode($points, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const toggles = document.querySelectorAll('[data-kind][type="checkbox"]');
    function displayLocations() {
        const enabled = new Set();
        for (const toggle of toggles) {
            if (toggle.checked) enabled.add(toggle.dataset.kind === 'incidents' ? toggle.dataset.type : toggle.dataset.kind);
        }
        markers.clearLayers();
        const groups = new Map();
        let visible = 0;
        for (const point of points) {
            if (!enabled.has(point.kind === 'incidents' ? point.label : point.kind)) continue;
            const key = point.latitude + ',' + point.longitude;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(point);
            visible++;
        }
        for (const records of groups.values()) {
            const first = records[0];
            const list = document.createElement('ul');
            for (const point of records) {
                const item = document.createElement('li');
                item.textContent = [point.label, point.barangay, point.status].filter(Boolean).join(' · ');
                list.appendChild(item);
            }
            const title = records.length === 1 ? list.textContent : records.length + ' records at this point';
            L.marker([first.latitude, first.longitude], {
                title,
                icon: recordPin(first.kind, first.kind === 'centers' ? first.status : first.label, records.length)
            }).bindPopup(list, {maxHeight: 180}).addTo(markers);
        }
        for (const item of document.querySelectorAll('[data-layer]')) item.hidden = !enabled.has(item.dataset.layer);
        document.getElementById('visible-count').textContent = visible;
        showLocations.disabled = visible === 0;
    }
    for (const toggle of toggles) {
        const icon = recordPin(toggle.dataset.kind, toggle.dataset.type);
        const sample = toggle.nextElementSibling;
        sample.className = 'legend-pin ' + icon.options.className;
        sample.innerHTML = icon.options.html;
        toggle.disabled = false;
        toggle.addEventListener('change', displayLocations);
    }
    displayLocations();
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
