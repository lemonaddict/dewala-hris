<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$employeeId = (int) ($_GET['employee_id'] ?? 0);

if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'employee_id tidak valid']);
    exit;
}

$role = $_SESSION['role'];

// Otorisasi cakupan: staff hanya boleh cek dirinya sendiri; manajer hanya tim-nya; HR bebas.
if ($role === 'staff' && $employeeId !== (int) $_SESSION['employee_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Tidak memiliki akses ke data karyawan ini']);
    exit;
}

if ($role === 'manager') {
    $isSelf = $employeeId === (int) $_SESSION['employee_id'];
    $stmt = $pdo->prepare('SELECT manager_id FROM employees WHERE id = ?');
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch();
    $isTeam = $row && (int) $row['manager_id'] === (int) $_SESSION['employee_id'];

    if (!$isSelf && !$isTeam) {
        http_response_code(403);
        echo json_encode(['error' => 'Tidak memiliki akses ke data karyawan ini']);
        exit;
    }
}

$result = PromotionEngine::checkEligibility($pdo, $employeeId);
echo json_encode($result);
