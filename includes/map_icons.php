<?php
function mapIcon(string $name): string
{
    $paths = [
        'flood' => '<path d="M3 9c2-2 4-2 6 0s4 2 6 0 4-2 6 0M3 15c2-2 4-2 6 0s4 2 6 0 4-2 6 0M5 20c2-2 4-2 6 0s4 2 6 0"/>',
        'fire' => '<path d="M12 22c4.4 0 7-3 7-7 0-3.1-1.6-5.3-4.1-8-.2 2-1 3.2-2.1 4.1C12.4 7.3 10.9 4.5 8 2c.2 4.4-3 7.1-3 12.2C5 18.5 7.8 22 12 22Z"/><path d="M12 19c1.8 0 3-1.3 3-3.1 0-1.2-.7-2.3-1.8-3.5-.2 1.1-.7 1.6-1.4 2.1-.4-1.2-1-2-2-2.8-.2 2.3-1 3.1-1 4.4C8.8 17.8 10 19 12 19Z"/>',
        'landslide' => '<path d="m2 20 7-12 4 6 3-4 6 10H2Z"/><path d="m8 20 2-3 2 3m2-5 1-2m-7 1 1-2"/>',
        'road' => '<path d="M7 22 10 2m7 20L14 2M12 5v3m0 4v3m0 4v2M3 8h4m10 0h4M2 16h4m12 0h4"/>',
        'emergency' => '<path d="M12 3 2 21h20L12 3Z"/><path d="M12 9v5m0 3h.01"/>',
        'center' => '<path d="M3 21h18M5 21V5h14v16M9 21v-5h6v5M9 9h2m2 0h2m-6 3h2m2 0h2M10 2h4v3h-4z"/>',
        'closed-center' => '<path d="M3 21h18M5 21V5h14v16M9 21v-5h6v5M9 9h2m2 0h2m-6 3h2m2 0h2M4 4l16 16"/>',
        'relief' => '<path d="m3 7 9-4 9 4v11l-9 4-9-4V7Zm0 0 9 4 9-4M12 11v11m-4-7h2m4 0h2"/>',
        'household' => '<path d="m2 11 10-8 10 8M5 9v12h14V9M9 21v-7h6v7"/>',
        'person' => '<circle cx="12" cy="7" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/>',
    ];
    $icon = $paths[$name] ?? $paths['emergency'];
    $class = h('map-icon map-icon--' . $name);
    return '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icon . '</svg>';
}
