<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['manager', 'hr']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/promotion/dashboard.php');
}
verify_csrf();

$employeeId = (int) ($_POST['employee_id'] ?? 0);

// Otorisasi cakupan untuk manajer: hanya boleh mengajukan promosi anak buahnya sendiri
if ($_SESSION['role'] === 'manager') {
    $stmt = $pdo->prepare('SELECT manager_id FROM employees WHERE id = ?');
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['manager_id'] !== (int) $_SESSION['employee_id']) {
        flash('error', 'Anda tidak berwenang mengajukan promosi untuk karyawan ini.');
        redirect('modules/promotion/dashboard.php');
    }
}

// Validasi ulang di server: status tombol UI tidak pernah dipercaya begitu saja
$eligibility = PromotionEngine::checkEligibility($pdo, $employeeId);

if (!$eligibility['eligible']) {
    flash('error', 'Pengajuan ditolak sistem: karyawan belum memenuhi seluruh syarat promosi.');
    redirect('modules/promotion/dashboard.php');
}

$check = $pdo->prepare("SELECT COUNT(*) AS jumlah FROM promotions WHERE employee_id = ? AND status = 'pending'");
$check->execute([$employeeId]);
if ((int) $check->fetch()['jumlah'] > 0) {
    flash('error', 'Sudah ada pengajuan promosi yang masih menunggu untuk karyawan ini.');
    redirect('modules/promotion/dashboard.php');
}

$stmt = $pdo->prepare(
    "INSERT INTO promotions (employee_id, from_position_id, to_position_id, status, requested_by, requested_at)
     VALUES (?, ?, ?, 'pending', ?, NOW())"
);
$stmt->execute([
    $employeeId,
    $eligibility['employee']['current_position_id'],
    $eligibility['target_position']['id'],
    $_SESSION['employee_id'],
]);

flash('success', 'Pengajuan promosi berhasil dikirim dan menunggu finalisasi HR.');
redirect('modules/promotion/dashboard.php');
