<?php
if (!isset($pickerKind, $point)) {
    http_response_code(404);
    exit;
}
$boundary = atokBoundary();
$assetBase = h(rtrim($baseUrl, '/'), ENT_QUOTES, 'UTF-8');
$addressField = $pickerKind === 'incidents' ? 'location' : 'address';
?>
<fieldset class="location-picker mb-3" data-kind="<?= h($pickerKind) ?>" data-type="<?= h($point['incident_type'] ?? '') ?>" data-address-url="https://photon.komoot.io/reverse">
    <legend>Location</legend>
    <div class="row g-3">
        <div class="col-md-5">
            <label class="d-block mb-2">Written address or nearby landmark
                <input class="form-control" name="<?= h($addressField) ?>" maxlength="255" value="<?= h($point[$addressField] ?? '') ?>" required />
            </label>
            <p class="small">Type the address or place a pin inside Atok. Address suggestions are optional. Coordinates are sent to Photon only when you choose to request a suggestion; you can always enter the address manually.</p>
            <button type="button" class="btn btn-outline-secondary mb-2" data-suggest-address disabled>Suggest address from selected pin</button>
            <p data-address-status role="status" class="small"></p>
            <button type="button" class="btn btn-outline-secondary" data-locate>Use my current location</button>
            <button type="button" class="btn btn-outline-secondary" data-clear>Clear pin</button>
            <details>
                <summary>Enter coordinates manually</summary>
                <?php foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit): ?>
                    <label class="d-block"><?= h(ucfirst($field)) ?>
                        <input class="form-control" data-coordinate="<?= h($field) ?>" name="<?= h($field) ?>" type="number" step="any" min="<?= -$limit ?>" max="<?= $limit ?>" value="<?= h($point[$field] ?? '') ?>" />
                    </label>
                <?php endforeach; ?>
            </details>

            <p class="small">If requested, address data is provided by Photon and © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>.</p>

        </div>
        <div class="col-md-7">
            <div data-picker-map aria-label="Select a location in Atok" data-boundary="<?= h(json_encode($boundary)) ?>" data-lat="<?= h($boundary['properties']['opening_view'][0]) ?>" data-lng="<?= h($boundary['properties']['opening_view'][1]) ?>" data-zoom="14" data-min-zoom="8"></div>
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
<link rel="stylesheet" href="<?= $assetBase ?>/assets/vendor/leaflet/leaflet.css">
<script src="<?= $assetBase ?>/assets/vendor/leaflet/leaflet.js"></script>
<script src="<?= $assetBase ?>/assets/js/map.js?v=4"></script>
<script src="<?= $assetBase ?>/assets/js/location-picker.js?v=2" defer></script>
