<?php
require_once __DIR__ . '/household_function.php';
if (!isset($form)) { http_response_code(404); exit; }
require __DIR__ . '/../includes/header.php';
?>
<h1><?= h($pageTitle) ?></h1>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="col-lg-7">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <label class="d-block mb-3">Barangay
        <select class="form-select" name="barangay" required>
            <option value="">Choose</option>
            <?php foreach (BARANGAYS as $value): ?>
                <option <?= $form['barangay'] === $value ? 'selected' : '' ?>><?= h($value) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="d-block mb-3">Written address or nearby landmark
        <input class="form-control" name="address" maxlength="255" required value="<?= h($form['address']) ?>">
    </label>
    <label class="d-block mb-3">Emergency contact name
        <input class="form-control" name="emergency_name" maxlength="100" required value="<?= h($form['emergency_name']) ?>">
    </label>
    <label class="d-block mb-3">Emergency contact number
        <input class="form-control" name="emergency_phone" maxlength="13" required value="<?= h($form['emergency_phone']) ?>">
    </label>
    <label class="d-block mb-3">Registry reason
        <select class="form-select" name="registry_reason" required>
            <option value="">Choose</option>
            <?php foreach (REASONS as $value): ?>
                <option <?= $form['registry_reason'] === $value ? 'selected' : '' ?>><?= h($value) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="d-block mb-3">Manual assistance assessment
        <select class="form-select" name="vulnerability" required>
            <option value="">Choose</option>
            <?php foreach (VULNERABILITY as $value): ?>
                <option <?= $form['vulnerability'] === $value ? 'selected' : '' ?>><?= h($value) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="d-block mb-3">Recorded hazard notes (optional)
        <input class="form-control" name="hazard_notes" maxlength="500" value="<?= h($form['hazard_notes']) ?>">
    </label>
    <button class="btn btn-primary">Save household</button>
    <a class="btn btn-outline-secondary" href="index.php">Return to registry</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
