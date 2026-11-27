<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('staff');
const BARANGAYS = ['Abiang', 'Caliking', 'Cattubo', 'Naguey', 'Paoay', 'Pasdong', 'Poblacion', 'Topdac'];
const REASONS = ['Monitored', 'Affected', 'Monitored and affected'];
const VULNERABILITY = ['Not assessed', 'No additional need identified', 'Additional assistance needed'];

function householdId(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Invalid household.');
    }
    return $id;
}

function findHousehold(int $id, bool $lock = false): array
{
    global $pdo;
    $sql = 'SELECT * FROM households WHERE id=?';
    $query = $pdo->prepare($lock ? $sql . ' FOR UPDATE' : $sql);
    $query->execute([$id]);
    $household = $query->fetch();
    if (!$household) {
        http_response_code(404);
        exit('Household not found.');
    }
    return $household;
}

function h($text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function readHouseholdForm(array &$form): string
{
    $form = [];
    foreach (['barangay','address','emergency_name','emergency_phone','registry_reason','vulnerability','hazard_notes'] as $field) {
        $form[$field] = trim(posted($field));
    }
    if (!in_array($form['barangay'], BARANGAYS, true) || !in_array($form['registry_reason'], REASONS, true) || !in_array($form['vulnerability'], VULNERABILITY, true)) {
        return 'Choose a valid barangay, registry reason and assessment.';
    }
    if ($form['address'] === '' || mb_strlen($form['address']) > 255 || $form['emergency_name'] === '' || mb_strlen($form['emergency_name']) > 100 || mb_strlen($form['hazard_notes']) > 500) {
        return 'Enter an address and emergency contact name within the field limits.';
    }
    if (!preg_match('/^(?:09[0-9]{9}|[+]639[0-9]{9})$/D', $form['emergency_phone'])) {
        return 'Enter a mobile number starting with 09 or +639 followed by nine digits.';
    }
    return '';
}
