<?php
require __DIR__ . '/household_function.php';
$id = householdId();
$household = findHousehold($id);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && posted('confirm_delete') === 'yes') {
    try {
        $query = $pdo->prepare('DELETE FROM households WHERE id=?');
        $query->execute([$id]);
        go('households/index.php');
    } catch (PDOException $exception) {
        $error = 'This household could not be deleted. Households linked to members or relief distributions must be retained.';
    }
}

$query = $pdo->prepare('SELECT m.*,r.member_id AS representative,e.account_id FROM household_members m
    LEFT JOIN household_representatives r ON r.member_id=m.id
    LEFT JOIN resident_enrollments e ON e.member_id=m.id
    WHERE m.household_id=? ORDER BY m.active DESC,m.full_name,m.id');
$query->execute([$id]);
$members = $query->fetchAll();
$representative = 'Representative not selected';
$count = 0;
foreach ($members as $member) {
    $count += (int)$member['active'];
    if ($member['representative']) {
        $representative = $member['full_name'];
    }
}
$pageTitle = 'Household details';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= h($representative) ?></h1>
<?php if (isset($_SESSION["notice"])): ?>
    <p role="status"><?= h($_SESSION["notice"]) ?></p>
    <?php unset($_SESSION["notice"]); ?>
<?php endif; ?>
<p><?= h($household['barangay']) ?> · <?= h($household['address']) ?></p>
<p>Emergency contact: <?= h($household['emergency_name']) ?> · <?= h($household['emergency_phone']) ?></p>
<p><?= h($household['registry_reason']) ?> · <?= h($household['vulnerability']) ?></p>
<p>Hazard notes: <?= h($household['hazard_notes'] ?: 'None recorded') ?></p>
<a class="btn btn-outline-secondary" href="form.php?id=<?= $id ?>">Edit household</a>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<details class="my-2">
    <summary>Delete household</summary>
    <form method="post">
        <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
        <label><input type="checkbox" name="confirm_delete" value="yes" required> Confirm permanent deletion of this unused household.</label>
        <button class="btn btn-danger">Delete household</button>
    </form>
</details>
<a class="btn btn-outline-secondary" href="index.php">Return to registry</a>
<h2 class="mt-4">Members · <?= $count ?> active</h2>
<a class="btn btn-primary" href="member.php?id=<?= $id ?>">Add member</a>
<ul class="list-group mt-2">
    <?php foreach ($members as $member): ?>
        <li class="list-group-item">
            <?= h($member['full_name']) ?> · <?= $member['active'] ? 'Active' : 'Inactive' ?>
            <?= $member['representative'] ? '· Representative' : '' ?>
            <a class="btn btn-outline-secondary" href="member.php?id=<?= $id ?>&amp;member=<?= (int)$member['id'] ?>">Manage member</a>
            <?php if ($member['account_id']): ?>
                · Account issued
            <?php elseif ($member['active']): ?>
                <a class="btn btn-outline-secondary" href="enroll.php?id=<?= $id ?>&amp;member=<?= (int)$member['id'] ?>">Enroll resident</a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/../includes/footer.php'; ?>
