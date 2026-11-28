<?php
require __DIR__ . '/household_function.php';
require_once __DIR__ . '/../includes/password.php';
$id = householdId();
$saving = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($saving) {
    $pdo->beginTransaction();
}
$household = findHousehold($id, $saving);
$query = $pdo->prepare('SELECT m.id,m.full_name,m.active,e.account_id FROM household_members m
    LEFT JOIN resident_enrollments e ON e.member_id=m.id WHERE m.household_id=? AND m.id=?');
$query->execute([$id, filter_input(INPUT_GET, 'member', FILTER_VALIDATE_INT)]);
$member = $query->fetch();
if (!$member) {
    http_response_code(404);
    exit('Member not found in this household.');
}
$error = '';
$email = trim(posted('email'));
$phone = trim(posted('phone'));
if (!$member['active'] || $member['account_id']) {
    $error = 'Enrollment requires an active member without an existing account.';
} elseif ($saving) {
    if (posted('verified') !== 'yes' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Confirm residency and enter a valid login email.';
    } elseif ($phone !== '' && !validMobileNumber($phone)) {
        $error = 'Enter a valid mobile number or leave it blank.';
    } else {
        try {
            $temporary = temporaryPassword();
            $query = $pdo->prepare("INSERT INTO accounts(full_name,email,password_hash,phone,role,must_change) VALUES(?,?,?,?,'resident',1)");
            $query->execute([$member['full_name'],$email,password_hash($temporary,PASSWORD_DEFAULT),$phone ?: null]);
            $query = $pdo->prepare('INSERT INTO resident_enrollments(member_id,account_id) VALUES(?,?)');
            $query->execute([$member['id'],$pdo->lastInsertId()]);
            $pdo->commit();
            $_SESSION['notice'] = ['login_email'=>$email, 'temporary_password'=>$temporary];
            go('households/view.php?id=' . $id);
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000' ? 'Email or member already enrolled.' : 'Enrollment failed. Try again.';
        }
    }
}
if ($pdo->inTransaction()) {
    $pdo->rollBack();
}
$pageTitle = 'Enroll resident';
require __DIR__ . '/../includes/header.php';
?>
<h1>Enroll <?= h($member['full_name']) ?></h1>
<p><?= h($household['barangay']) ?> · <?= h($household['address']) ?></p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($member['active'] && !$member['account_id']): ?>
    <form method="post" class="household-enroll-form form-panel workflow-form">
        <?= csrfField() ?>
        <label class="d-block mb-2">Login email<input class="form-control" type="email" name="email" required value="<?= h($email) ?>"></label>
        <label class="d-block mb-2">Resident mobile number (optional)<input class="form-control" name="phone" type="tel" maxlength="13" value="<?= h($phone) ?>"></label>
        <label class="d-block mb-2"><input type="checkbox" name="verified" value="yes" required> I verified this person's identity and Atok residency.</label>
        <button class="btn btn-primary">Issue resident account</button>
    </form>
<?php endif; ?>
<a class="btn btn-outline-secondary mt-2" href="view.php?id=<?= $id ?>">Return to household</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
