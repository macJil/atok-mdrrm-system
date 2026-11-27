<?php
require __DIR__ . '/household_function.php';
$editing = isset($_GET['id']);
$form = ['barangay'=>'', 'address'=>'', 'emergency_name'=>'', 'emergency_phone'=>'', 'registry_reason'=>'Monitored', 'vulnerability'=>'Not assessed', 'hazard_notes'=>''];
if ($editing) {
    $id = householdId();
    $form = findHousehold($id);
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [];
    foreach (['barangay','address','emergency_name','emergency_phone','registry_reason','vulnerability','hazard_notes'] as $field) {
        $form[$field] = trim(posted($field));
    }
    if (!in_array($form['barangay'], BARANGAYS, true) || !in_array($form['registry_reason'], REASONS, true) || !in_array($form['vulnerability'], VULNERABILITY, true)) {
        $error = 'Choose a valid barangay, registry reason and assessment.';
    } elseif ($form['address'] === '' || mb_strlen($form['address']) > 255 || $form['emergency_name'] === '' || mb_strlen($form['emergency_name']) > 100 || mb_strlen($form['hazard_notes']) > 500) {
        $error = 'Enter an address and emergency contact name within the field limits.';
    } elseif (!preg_match('/^(?:09[0-9]{9}|[+]639[0-9]{9})$/D', $form['emergency_phone'])) {
        $error = 'Enter a mobile number starting with 09 or +639 followed by nine digits.';
    }
    if ($error === '') {
        $values = array_values($form);
        if ($editing) {
            $query = $pdo->prepare('UPDATE households SET barangay=?,address=?,emergency_name=?,emergency_phone=?,registry_reason=?,vulnerability=?,hazard_notes=? WHERE id=?');
            $values[] = $id;
        } else {
            $query = $pdo->prepare('INSERT INTO households(barangay,address,emergency_name,emergency_phone,registry_reason,vulnerability,hazard_notes) VALUES(?,?,?,?,?,?,?)');
        }
        $query->execute($values);
        if (!$editing) {
            $id = $pdo->lastInsertId();
        }
        go('households/view.php?id=' . $id);
    }
}
$pageTitle = $editing ? 'Edit household' : 'Add household';
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
