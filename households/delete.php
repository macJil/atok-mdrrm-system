<?php
require __DIR__ . '/household_function.php';
$id = householdId();
$household = findHousehold($id);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $query = $pdo->prepare('DELETE FROM households WHERE id=?');
        $query->execute([$id]);
        go('households/index.php');
    } catch (PDOException $exception) {
        $error = 'This household could not be deleted. Households with member records must be retained.';
    }
}
$pageTitle = 'Delete household';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete household?</h1>
<p><?= h($household['barangay']) ?> · <?= h($household['address']) ?></p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <button class="btn btn-danger">Confirm permanent deletion</button>
    <a class="btn btn-outline-secondary" href="view.php?id=<?= $id ?>">Cancel</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
