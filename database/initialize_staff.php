<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config.php';
$email = $argv[1] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Supply the initial staff email.\n");
}
$password = bin2hex(random_bytes(12));
$query = $pdo->prepare("INSERT INTO accounts(full_name,email,password_hash,role)
    SELECT 'MDRRMO', ?, ?, 'staff' WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE role='staff')");
$query->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
echo $query->rowCount() ? "Login: $email\nTemporary password: $password\n" : "Staff account already exists.\n";
