<?php
require __DIR__ . '/household_function.php';
$id = householdId();
$household = findHousehold($id);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (posted('confirm_delete') !== 'yes') {
        $error = 'Confirm deletion before continuing.';
    } else {
        try {
            $query = $pdo->prepare('DELETE FROM households WHERE id=?');
            $query->execute([$id]);
            go('households/index.php');
        } catch (PDOException $exception) {
            $error = 'This household could not be deleted. Households linked to members or relief distributions must be retained.';
        }
    }
}
$pageTitle = 'Delete household';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete household</h1>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="form-panel col-lg-7">
    <p>Delete the household for <strong><?= h($household['address']) ?>, <?= h($household['barangay']) ?></strong>?</p>
    <p>Households linked to members or relief distributions cannot be deleted.</p>
    <form method="post">
        <?= csrfField() ?>
        <label class="d-block mb-3"><input type="checkbox" name="confirm_delete" value="yes" required> I confirm deletion of this unused household.</label>
        <button class="btn btn-danger">Delete household</button>
        <a class="btn btn-outline-secondary" href="view.php?id=<?= (int)$id ?>">Cancel</a>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
