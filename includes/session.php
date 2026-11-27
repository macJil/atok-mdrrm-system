<?php
require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ATOKREBUILD');
    session_start();
}

function requireRole(string $role): void
{
    global $settings;
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $settings['base_url'] . '/index.php', true, 303);
        exit;
    }
    if (!in_array($role, ['staff', 'resident'], true) || ($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}
