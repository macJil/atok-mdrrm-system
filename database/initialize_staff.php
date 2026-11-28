<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/password.php';
$email = $argv[1] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Supply the initial staff email.\n");
}
$password = temporaryPassword();
$query = $pdo->prepare("INSERT INTO accounts(full_name,email,password_hash,role,must_change)
    SELECT 'MDRRMO', ?, ?, 'staff', 1 WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE role='staff')");
$query->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
echo $query->rowCount() ? "Login: $email\nTemporary password: $password\n" : "Staff account already exists.\n";
