<?php
require __DIR__ . '/includes/session.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$_SESSION = [];
session_destroy();
go('index.php');
