<?php
$host = '127.0.0.1';
$port = 8889;
$database = 'atok_mdrrm_rebuild';
$username = 'root';
$password = 'root';
$baseUrl = '/atok-mdrrm-system';
$att = [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
];


try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, $att);
} catch (PDOException $error) {
    http_response_code(503);
    exit('Database unavailable. Check the local setup and refresh.');
}
