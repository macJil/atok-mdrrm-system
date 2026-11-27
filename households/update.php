<?php
require __DIR__ . '/household_function.php';
$id = householdId();
$form = findHousehold($id);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = readHouseholdForm($form);
    if ($error === '') {
        $query = $pdo->prepare('UPDATE households SET barangay=?,address=?,emergency_name=?,emergency_phone=?,registry_reason=?,vulnerability=?,hazard_notes=? WHERE id=?');
        $query->execute([...array_values($form), $id]);
        go('households/view.php?id=' . $id);
    }
}
$pageTitle = 'Edit household';
require __DIR__ . '/form.php';
