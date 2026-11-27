<?php
// Default XAMPP settings. Individual machines can override them in config.local.php.
$settings = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'atok_mdrrm_rebuild',
    'username' => 'root',
    'password' => '',
    'base_url' => '/atok-mdrrm-system',
];

if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    $settings = array_replace($settings, $local);
}

function db(): PDO
{
    global $settings;
    static $connection = null;
    if ($connection === null) {
        $dsn = "mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['database']};charset=utf8mb4";
        $connection = new PDO($dsn, $settings['username'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $connection;
}
