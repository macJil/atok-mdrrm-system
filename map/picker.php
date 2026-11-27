<?php
if (!isset($pickerKind, $point)) {
    http_response_code(404);
    exit;
}
$boundary = atokBoundary();
$addressField = $pickerKind === 'incidents' ? 'location' : 'address';
?>
<fieldset class="location-picker mb-3" data-kind="<?= htmlspecialchars($pickerKind) ?>" data-type="<?= htmlspecialchars($point['incident_type'] ?? '') ?>" data-address-url="https://photon.komoot.io/reverse">
    <legend>Location</legend>
    <div class="row g-3">
        <div class="col-md-5">
            <label class="d-block mb-2">Written address or nearby landmark
                <input class="form-control" name="<?= htmlspecialchars($addressField) ?>" maxlength="255" value="<?= htmlspecialchars($point[$addressField] ?? '') ?>" required />
            </label>
            <p class="small">Type the address or place a pin inside Atok. Check the suggested address before saving.</p>
            <p data-address-status role="status" class="small"></p>
            <button type="button" class="btn btn-outline-secondary" data-locate>Use my current location</button>
            <button type="button" class="btn btn-outline-secondary" data-clear>Clear pin</button>
            <details>
                <summary>Enter coordinates manually</summary>
                <?php foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit): ?>
                    <label class="d-block"><?= htmlspecialchars(ucfirst($field)) ?>
                        <input class="form-control" data-coordinate="<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>" type="number" step="any" min="<?= -$limit ?>" max="<?= $limit ?>" value="<?= htmlspecialchars($point[$field] ?? '') ?>" />
                    </label>
                <?php endforeach; ?>
            </details>

            <details class="small mt-2">
                <summary>About address suggestions</summary>
                Selected coordinates are sent to Photon. Address data © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>.
            </details>

        </div>
        <div class="col-md-7">
            <div data-picker-map aria-label="Select a location in Atok" data-boundary="<?= htmlspecialchars(json_encode($boundary)) ?>" data-lat="<?= htmlspecialchars($boundary['properties']['opening_view'][0]) ?>" data-lng="<?= htmlspecialchars($boundary['properties']['opening_view'][1]) ?>" data-zoom="14" data-min-zoom="8"></div>
            <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-show-atok disabled>Show all Atok</button>
                <small>Approximate municipal boundary</small>
            </div>
            <p data-picker-status role="status" class="small"></p>
            <p data-picker-availability role="status" class="small"></p>
            <noscript>Type an address or enter coordinates manually.</noscript>
        </div>
    </div>
</fieldset>
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<script src="../assets/vendor/leaflet/leaflet.js"></script>
<script src="../assets/js/map.js"></script>
<script src="../assets/js/location-picker.js" defer></script>
