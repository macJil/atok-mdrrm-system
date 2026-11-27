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
