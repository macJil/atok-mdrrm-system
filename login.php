<?php
require __DIR__ . '/includes/session.php';

if ($user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    go('index.php');
}

$email = trim(posted('email'));
$attemptKey = hash('sha256', strtolower($email) . "\0" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$query = $pdo->prepare("SELECT failed_attempts FROM login_attempts
    WHERE attempt_key=? AND window_started_at >= CURRENT_TIMESTAMP - INTERVAL 15 MINUTE");
$query->execute([$attemptKey]);
$attempts = $query->fetchColumn();

if ($attempts === false || (int)$attempts < 5) {
    $query = $pdo->prepare('SELECT id,password_hash,session_version FROM accounts WHERE email=? AND active=1');
    $query->execute([$email]);
    $account = $query->fetch();
    if ($account && password_verify(posted('password'), $account['password_hash'])) {
        $query = $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?');
        $query->execute([$attemptKey]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $account['id'];
        $_SESSION['version'] = (int)$account['session_version'];
        $_SESSION['token'] = bin2hex(random_bytes(32));
        go('index.php');
    }

    $query = $pdo->prepare("INSERT INTO login_attempts(attempt_key,failed_attempts,window_started_at,updated_at)
        VALUES(?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            failed_attempts=IF(window_started_at < CURRENT_TIMESTAMP - INTERVAL 15 MINUTE,1,failed_attempts+1),
            window_started_at=IF(window_started_at < CURRENT_TIMESTAMP - INTERVAL 15 MINUTE,CURRENT_TIMESTAMP,window_started_at),
            updated_at=CURRENT_TIMESTAMP");
    $query->execute([$attemptKey]);
    $pdo->exec("DELETE FROM login_attempts
        WHERE updated_at < CURRENT_TIMESTAMP - INTERVAL 1 DAY LIMIT 100");
}

$_SESSION['login_email'] = $email;
$_SESSION['login_error'] = 'Email or password is incorrect, the account is inactive, or sign-in is temporarily unavailable.';
go('index.php');
