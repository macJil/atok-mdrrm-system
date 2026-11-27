<?php
require __DIR__ . '/household_function.php';
$form = ['barangay'=>'', 'address'=>'', 'emergency_name'=>'', 'emergency_phone'=>'', 'registry_reason'=>'Monitored', 'vulnerability'=>'Not assessed', 'hazard_notes'=>''];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = readHouseholdForm($form);
    if ($error === '') {
        $query = $pdo->prepare('INSERT INTO households(barangay,address,emergency_name,emergency_phone,registry_reason,vulnerability,hazard_notes) VALUES(?,?,?,?,?,?,?)');
        $query->execute(array_values($form));
        $id = $pdo->lastInsertId();
        go('households/view.php?id=' . $id);
    }
}
$pageTitle = 'Add household';
require __DIR__ . '/form.php';
