<?php
require __DIR__ . '/household_function.php';
require_once __DIR__ . '/../includes/password.php';
$id = householdId();
$saving = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($saving) {
    $pdo->beginTransaction();
}
$household = findHousehold($id, $saving);
$editing = isset($_GET['member']);
$member = ['full_name'=>'', 'active'=>1, 'representative'=>null, 'account_id'=>null, 'account_active'=>null, 'email'=>''];
if ($editing) {
    $query = $pdo->prepare('SELECT m.*,r.member_id AS representative,a.id AS account_id,a.email,a.active AS account_active FROM household_members m
        LEFT JOIN household_representatives r ON r.member_id=m.id
        LEFT JOIN resident_enrollments e ON e.member_id=m.id
        LEFT JOIN accounts a ON a.id=e.account_id AND a.role=\'resident\' WHERE m.household_id=? AND m.id=?');
    $query->execute([$id, filter_input(INPUT_GET, 'member', FILTER_VALIDATE_INT)]);
    $member = $query->fetch();
    if (!$member) {
        http_response_code(404);
        exit('Member not found in this household.');
    }
}
$member['email'] = $member['email'] ?? '';
$error = '';
$name = '';
$email = $member['email'];
$active = (bool)$member['active'];
if ($saving) {
    $accountAction = posted('action');
    if (in_array($accountAction, ['reset_password','activate_account'], true)) {
        if (empty($member['account_id']) || !$member['active'] || !$member['account_active']) {
            if ($accountAction === 'activate_account' && !empty($member['account_id']) && $member['active'] && !$member['account_active']) {
                if (posted('verified') !== 'yes') {
                    $error = 'Verify the resident’s identity before restoring account access.';
                } else {
                    try {
                        $query = $pdo->prepare('UPDATE accounts SET active=1,session_version=session_version+1 WHERE id=? AND role=\'resident\' AND active=0');
                        $query->execute([$member['account_id']]);
                        if ($query->rowCount() !== 1) {
                            $error = 'The resident account changed. Reload this page and try again.';
                        } else {
                            $pdo->commit();
                            $_SESSION['notice'] = 'Resident sign-in access restored. If they do not know their password, issue a separate reset.';
                            go('households/view.php?id=' . $id);
                        }
                    } catch (PDOException $exception) {
                        $error = 'Account reactivation failed. Reload the page and try again.';
                    }
                }
            } else {
                $error = 'Password reset is available only for an active resident account.';
            }
        } elseif (posted('verified') !== 'yes') {
            $error = 'Verify the resident’s identity before resetting their password.';
        } else {
            try {
                $temporary = temporaryPassword();
                $query = $pdo->prepare('UPDATE accounts SET password_hash=?,must_change=1,session_version=session_version+1 WHERE id=? AND role=\'resident\' AND active=1');
                $query->execute([password_hash($temporary, PASSWORD_DEFAULT),$member['account_id']]);
                if ($query->rowCount() !== 1) {
                    $error = 'The resident account changed. Reload this page and try again.';
                } else {
                    $pdo->commit();
                    $_SESSION['notice'] = [
                        'login_email'=>$member['email'],
                        'temporary_password'=>$temporary,
                        'password_reset'=>true,
                    ];
                    go('households/view.php?id=' . $id);
                }
            } catch (PDOException $exception) {
                $error = 'Password reset failed. Reload the page and try again.';
            }
        }
    } else {
        $name = trim(posted('name'));
        $email = trim(posted('email'));
        $active = posted('active') === 'yes';
        $represent = posted('represent') === 'yes';
        if ($name === '' || mb_strlen($name) > 100) {
            $error = 'Enter a member name of up to 100 characters.';
        } elseif ($member['account_id'] && (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $error = 'Enter a valid login email address.';
        } elseif (!$active && ($member['representative'] || $represent)) {
            $error = 'Select another active member as representative first.';
        } else {
            try {
                if ($editing) {
                    $memberId = $member['id'];
                    $query = $pdo->prepare('UPDATE household_members SET full_name=?,active=? WHERE id=? AND household_id=?');
                    $query->execute([$name,(int)$active,$memberId,$id]);
                    if ($member['account_id']) {
                        if ($active) {
                            $query = $pdo->prepare('UPDATE accounts SET full_name=?,email=? WHERE id=? AND role=\'resident\'');
                            $query->execute([$name,$email,$member['account_id']]);
                        } else {
                            $query = $pdo->prepare('UPDATE accounts SET full_name=?,email=?,active=0,session_version=session_version+1 WHERE id=? AND role=\'resident\'');
                            $query->execute([$name,$email,$member['account_id']]);
                        }
                    }
                } else {
                    $query = $pdo->prepare('INSERT INTO household_members(household_id,full_name,active) VALUES(?,?,?)');
                    $query->execute([$id,$name,(int)$active]);
                    $memberId = $pdo->lastInsertId();
                }
                if ($represent) {
                    $query = $pdo->prepare('INSERT INTO household_representatives(household_id,member_id) VALUES(?,?)
                        ON DUPLICATE KEY UPDATE member_id=?');
                    $query->execute([$id,$memberId,$memberId]);
                }
                $pdo->commit();
                if (!$active && !empty($member['account_id'])) {
                    $_SESSION['notice'] = 'Member marked inactive. Their linked account was deactivated; household, stay and distribution history remain.';
                } elseif ($active && !empty($member['account_id'])) {
                    $_SESSION['notice'] = !empty($member['account_active'])
                        ? 'Member saved and resident login email updated. Account access remains active.'
                        : 'Member saved and resident login email updated. Account access remains inactive until reactivated here.';
                }
                go('households/view.php?id=' . $id);
            } catch (PDOException $exception) {
                $error = $exception->getCode() === '23000' ? 'That login email is already used by another account.' : 'Member could not be saved. Try again.';
            }
        }
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!in_array($accountAction, ['reset_password','activate_account'], true)) {
        $member['full_name'] = $name;
        $member['email'] = $email;
        $member['active'] = $active;
    }
}
$pageTitle = $editing ? 'Manage member' : 'Add member';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= h($pageTitle) ?></h1>
<p class="household-context"><?= h($household['barangay']) ?> · <?= h($household['address']) ?></p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($editing && !empty($member['account_id'])): ?>
    <p class="household-guidance">Deactivating this member also deactivates their linked sign-in account. Their past stays and distributions remain on record. Reactivating the member does not reactivate account access.</p>
<?php endif; ?>
<form method="post" class="household-member-form form-panel workflow-form">
    <?= csrfField() ?>
    <label class="d-block mb-2">Full name<input class="form-control" name="name" maxlength="100" required value="<?= h($member['full_name']) ?>"></label>
    <?php if (!empty($member['account_id'])): ?>
        <label class="household-login-email d-block mb-2">Resident login email
            <input class="form-control" type="email" name="email" maxlength="254" required autocomplete="email" value="<?= h($member['email']) ?>">
        </label>
    <?php endif; ?>
    <label class="d-block mb-2"><input type="checkbox" name="active" value="yes" <?= $member['active'] ? 'checked' : '' ?>> Active member</label>
    <?php if ($member['representative']): ?>
        <p>Current representative. To replace, open another member and select them as representative.</p>
    <?php else: ?>
        <label class="d-block mb-3"><input type="checkbox" name="represent" value="yes"> Select as household representative</label>
    <?php endif; ?>
    <button class="btn btn-primary">Save member</button>
    <a class="btn btn-outline-secondary" href="view.php?id=<?= $id ?>">Return to household</a>
</form>
<?php if ($editing && !empty($member['account_id'])): ?>
    <section class="household-password-reset form-panel" aria-labelledby="password-reset-heading">
        <h2 class="h5" id="password-reset-heading">Resident password help</h2>
        <?php if (empty($member['account_active']) && !empty($member['active'])): ?>
            <p>This resident account is inactive. Verify the resident before restoring access. A password reset is a separate action.</p>
            <form method="post">
                <?= csrfField() ?>
                <label class="d-block mb-3"><input type="checkbox" name="verified" value="yes" required> I verified this resident’s identity.</label>
                <button class="btn btn-outline-primary" name="action" value="activate_account">Restore resident sign-in access</button>
            </form>
        <?php elseif (!empty($member['account_active']) && !empty($member['active'])): ?>
            <p>If the resident cannot sign in, verify their identity, then issue a temporary password. Their current sessions will be signed out and they must choose a new password at their next sign-in.</p>
            <form method="post">
                <?= csrfField() ?>
                <label class="d-block mb-3"><input type="checkbox" name="verified" value="yes" required> I verified this resident’s identity.</label>
                <button class="btn btn-outline-primary" name="action" value="reset_password">Issue temporary password</button>
            </form>
        <?php else: ?>
            <p>This household member is inactive. Mark the member active before managing resident sign-in access.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
