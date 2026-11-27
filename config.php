<?php
$host = '127.0.0.1';
$port = 3306;
$database = 'atok_mdrrm_rebuild';
$username = 'root';
$password = '';
$baseUrl = '/atok-mdrrm-system';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $error) {
    http_response_code(503);
    exit('Database unavailable. Check the local setup and refresh.');
}
