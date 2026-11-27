<?php
require __DIR__ . '/household_function.php';
$id = householdId();
$saving = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($saving) {
    $pdo->beginTransaction();
}
$household = findHousehold($id, $saving);
$editing = isset($_GET['member']);
$member = ['full_name'=>'', 'active'=>1, 'representative'=>null];
if ($editing) {
    $query = $pdo->prepare('SELECT m.*,r.member_id AS representative,e.account_id FROM household_members m
        LEFT JOIN household_representatives r ON r.member_id=m.id
        LEFT JOIN resident_enrollments e ON e.member_id=m.id WHERE m.household_id=? AND m.id=?');
    $query->execute([$id, filter_input(INPUT_GET, 'member', FILTER_VALIDATE_INT)]);
    $member = $query->fetch();
    if (!$member) {
        http_response_code(404);
        exit('Member not found in this household.');
    }
}
$error = '';
if ($saving) {
    $name = trim(posted('name'));
    $active = posted('active') === 'yes';
    $represent = posted('represent') === 'yes';
    if ($name === '' || mb_strlen($name) > 100) {
        $error = 'Enter a member name of up to 100 characters.';
    } elseif (!$active && $member['representative']) {
        $error = 'Select another active member as representative first.';
    } elseif (!$active && ($represent || !empty($member['account_id']))) {
        $error = 'Representatives and enrolled members must remain active. Manage account access on the Accounts page.';
    } else {
        if ($editing) {
            $memberId = $member['id'];
            $query = $pdo->prepare('UPDATE household_members SET full_name=?,active=? WHERE id=? AND household_id=?');
            $query->execute([$name,(int)$active,$memberId,$id]);
            if ($member['account_id']) {
                $query = $pdo->prepare('UPDATE accounts SET full_name=? WHERE id=?');
                $query->execute([$name,$member['account_id']]);
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
        go('households/view.php?id=' . $id);
    }
    $pdo->rollBack();
    $member['full_name'] = $name;
}
$pageTitle = $editing ? 'Manage member' : 'Add member';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= h($pageTitle) ?></h1>
<p><?= h($household['barangay']) ?> · <?= h($household['address']) ?></p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="col-md-6">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block mb-2">Full name<input class="form-control" name="name" maxlength="100" required value="<?= h($member['full_name']) ?>"></label>
    <label class="d-block mb-2"><input type="checkbox" name="active" value="yes" <?= $member['active'] ? 'checked' : '' ?>> Active member</label>
    <?php if ($member['representative']): ?>
        <p>Current representative. To replace, open another member and select them as representative.</p>
    <?php else: ?>
        <label class="d-block mb-3"><input type="checkbox" name="represent" value="yes"> Select as household representative</label>
    <?php endif; ?>
    <button class="btn btn-primary">Save member</button>
    <a class="btn btn-outline-secondary" href="view.php?id=<?= $id ?>">Return to household</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
