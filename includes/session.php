<?php
require_once __DIR__ . '/../config.php';
session_name('ATOKREBUILD');
session_start();
header('Cache-Control: no-store');
$_SESSION['token'] ??= bin2hex(random_bytes(32));

function go(string $page): void
{
    global $baseUrl;
    header('Location: ' . $baseUrl . '/' . $page, true, 303);
    exit;
}

function posted(string $field): string
{
    return is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    exit('Upload could not be processed. Return to the form and choose an image no larger than 2 MiB.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['token'], posted('token'))) {
    http_response_code(403);
    exit('Please reopen the form and try again.');
}

$user = null;
if (isset($_SESSION['user_id'])) {
    $query = $pdo->prepare('SELECT id, full_name, role, active, must_change, session_version FROM accounts WHERE id=?');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user || !$user['active'] || (int)$user['session_version'] !== $_SESSION['version']) {
        $_SESSION = [];
        session_destroy();
        go('index.php');
    }
}

function requireLogin(): void
{
    global $user;
    if (!$user) {
        go('index.php');
    }
}

function requireRole(string $role): void
{
    global $user;
    requireLogin();
    if ($user['must_change']) {
        go('change_password.php');
    }
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}
