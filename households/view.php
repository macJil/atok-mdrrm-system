<?php
require __DIR__ . '/household_function.php';
$id = householdId();
$household = findHousehold($id);
$query = $pdo->prepare('SELECT m.*,r.member_id AS representative,e.account_id,a.email,a.active AS account_active FROM household_members m
    LEFT JOIN household_representatives r ON r.member_id=m.id
    LEFT JOIN resident_enrollments e ON e.member_id=m.id
    LEFT JOIN accounts a ON a.id=e.account_id
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
<?php if (isset($_SESSION['notice'])): ?>
    <?php $notice = $_SESSION['notice']; unset($_SESSION['notice']); ?>
    <?php if (is_array($notice) && is_string($notice['temporary_password'] ?? null)): ?>
        <section class="temporary-password-notice" role="status" aria-labelledby="resident-password-heading">
            <h2 class="h5" id="resident-password-heading"><?= !empty($notice['password_reset']) ? 'Resident password reset · shown once' : 'Resident login created · temporary password shown once' ?></h2>
            <p>Login email: <strong><?= h($notice['login_email'] ?? '') ?></strong></p>
            <p class="temporary-password-value"><code><?= h($notice['temporary_password']) ?></code></p>
            <p class="mb-0">Give these details to the resident privately. They must choose a new password after signing in. Do not print or share them in a group message.</p>
        </section>
    <?php elseif (is_string($notice)): ?>
        <p role="status"><?= h($notice) ?></p>
    <?php endif; ?>
<?php endif; ?>
<section class="household-profile" aria-label="Household information">
    <dl class="household-profile-grid">
        <div><dt>Barangay</dt><dd><?= h($household['barangay']) ?></dd></div>
        <div><dt>Address or landmark</dt><dd><?= h($household['address']) ?></dd></div>
        <div><dt>Emergency contact</dt><dd><?= h($household['emergency_name']) ?></dd></div>
        <div><dt>Contact number</dt><dd><?= h($household['emergency_phone']) ?></dd></div>
        <div><dt>Registry status</dt><dd><?= h($household['registry_reason']) ?></dd></div>
        <div><dt>Assistance assessment</dt><dd><?= h($household['vulnerability']) ?></dd></div>
        <div class="household-profile-wide"><dt>Hazard notes</dt><dd><?= h($household['hazard_notes'] ?: 'None recorded') ?></dd></div>
    </dl>
    <div class="household-profile-actions">
        <a class="btn btn-outline-primary" href="form.php?id=<?= $id ?>">Edit household</a>
        <a class="btn btn-outline-danger" href="delete.php?id=<?= $id ?>">Delete household</a>
        <a class="btn btn-outline-secondary" href="index.php">Return to registry</a>
    </div>
</section>
<section class="household-members" aria-labelledby="household-members-heading">
    <div class="household-members-heading">
        <div><h2 id="household-members-heading">Household members</h2><p><?= $count ?> active member<?= $count === 1 ? '' : 's' ?></p></div>
        <a class="btn btn-primary" href="member.php?id=<?= $id ?>">Add member</a>
    </div>
<ul class="household-member-list">
    <?php foreach ($members as $member): ?>
        <li>
            <div class="household-member-info">
                <strong><?= h($member['full_name']) ?></strong>
                <span class="household-member-badges">
                    <span class="status-badge status-<?= $member['active'] ? 'available' : 'neutral' ?>"><?= $member['active'] ? 'Active member' : 'Inactive member' ?></span>
                    <?php if ($member['representative']): ?><span class="household-representative-badge">Household representative</span><?php endif; ?>
                </span>
                <?php if ($member['account_id']): ?>
                    <span class="household-member-account"><?= h($member['email']) ?> · <?= $member['account_active'] ? 'Sign-in active' : 'Sign-in inactive' ?></span>
                <?php else: ?>
                    <span class="household-member-account">No resident sign-in account</span>
                <?php endif; ?>
            </div>
            <div class="household-member-actions">
                <a class="btn btn-outline-secondary btn-sm" href="member.php?id=<?= $id ?>&amp;member=<?= (int)$member['id'] ?>">Manage member</a>
                <?php if (!$member['account_id'] && $member['active']): ?>
                    <a class="btn btn-outline-primary btn-sm" href="enroll.php?id=<?= $id ?>&amp;member=<?= (int)$member['id'] ?>">Enroll resident</a>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
