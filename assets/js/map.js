// The same municipal outline is embedded by PHP on each map page.
function drawAtok(map, boundary, button) {
    const layer = L.geoJSON(boundary, {
        interactive: false,
        style: { color: "#13675c", weight: 2, fillOpacity: 0.035 }
    }).addTo(map);
    button.disabled = false;
    button.addEventListener("click", () => {
        map.fitBounds(layer.getBounds(), { padding: [12, 12] });
    });
}

function insideAtok(lat, lng, boundary) {
    const ring = boundary.geometry.coordinates[0];
    let inside = false;
    for (let i = 1; i < ring.length; i++) {
        const [x1, y1] = ring[i - 1];
        const [x2, y2] = ring[i];
        if ((y1 > lat) !== (y2 > lat)) {
            const crossing = x1 + (lat - y1) * (x2 - x1) / (y2 - y1);
            if (lng < crossing) inside = !inside;
        }
    }
    return inside;
}

function recordPin(kind, type) {
    const symbols = {Flooding:'≈', Fire:'🔥', Landslide:'◩', 'Road obstruction':'⛔', 'Other emergency':'⚠'};
    let symbol = symbols[type] || '⚠';
    if (kind === 'centers') symbol = type === 'Closed' ? '⊠' : '⌂';
    if (kind === 'households') symbol = '🏠';
    if (kind === 'relief_locations') symbol = '📦';
    return L.divIcon({className:'record-pin', html:symbol, iconSize:[30,30], iconAnchor:[15,15]});
}
