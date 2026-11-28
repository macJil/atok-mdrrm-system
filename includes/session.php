<?php
require_once __DIR__ . '/../config.php';
const BARANGAYS = ['Abiang', 'Caliking', 'Cattubo', 'Naguey', 'Paoay', 'Pasdong', 'Poblacion', 'Topdac'];
session_name('ATOKREBUILD');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$sessionCookie = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $sessionCookie['lifetime'],
    'path' => $sessionCookie['path'],
    'domain' => $sessionCookie['domain'],
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Cache-Control: no-store');
$_SESSION['token'] ??= bin2hex(random_bytes(32));

function go(string $page): never
{
    global $baseUrl;
    header('Location: ' . $baseUrl . '/' . $page, true, 303);
    exit;
}

function posted(string $field): string
{
    return is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
}

function searchPattern(string $value): string
{
    return '%' . strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
}

function validMobileNumber(string $value): bool
{
    return preg_match('/^(?:09[0-9]{9}|[+]639[0-9]{9})$/D', $value) === 1;
}

function pageWindow(int $total, int $requestedPage, int $perPage = 10): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, max(1, $requestedPage));
    return [$page, ($page - 1) * $perPage, $pages];
}

function h(mixed $value, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $encoding = 'UTF-8'): string
{
    return htmlspecialchars((string)$value, $flags, $encoding);
}

function csrfField(): string
{
    return '<input type="hidden" name="token" value="' .
        h($_SESSION['token']) . '">';
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
    $account = $query->fetch();
    if (
        !is_array($account) ||
        empty($account['active']) ||
        (int)$account['session_version'] !== (int)($_SESSION['version'] ?? 0)
    ) {
        $_SESSION = [];
        session_destroy();
        go('index.php');
    }
    $user = $account;
}

function requireLogin(): array
{
    global $user;
    if (!is_array($user)) {
        go('index.php');
    }
    return $user;
}

function requireRole(string $role): array
{
    $user = requireLogin();
    if ($user['must_change']) {
        go('change_password.php');
    }
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
    return $user;
}
