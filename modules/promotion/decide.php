<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['hr']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/promotion/dashboard.php');
}
verify_csrf();

$promotionId = (int) ($_POST['promotion_id'] ?? 0);
$decision    = $_POST['decision'] ?? '';

if (!in_array($decision, ['approved', 'rejected'], true)) {
    flash('error', 'Keputusan tidak valid.');
    redirect('modules/promotion/dashboard.php');
}

$stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ? AND status = 'pending'");
$stmt->execute([$promotionId]);
$promo = $stmt->fetch();

if (!$promo) {
    flash('error', 'Data pengajuan tidak ditemukan atau sudah diproses.');
    redirect('modules/promotion/dashboard.php');
}

try {
    $pdo->beginTransaction();

    $update = $pdo->prepare('UPDATE promotions SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ?');
    $update->execute([$decision, $_SESSION['employee_id'], $promotionId]);

    if ($decision === 'approved') {
        $updateEmp = $pdo->prepare('UPDATE employees SET position_id = ?, position_start_date = CURDATE() WHERE id = ?');
        $updateEmp->execute([$promo['to_position_id'], $promo['employee_id']]);
    }

    $pdo->commit();
    flash('success', $decision === 'approved'
        ? 'Promosi disetujui dan jabatan karyawan diperbarui.'
        : 'Pengajuan promosi ditolak.');
} catch (Exception $e) {
    $pdo->rollBack();
    flash('error', 'Gagal memproses keputusan: ' . $e->getMessage());
}

redirect('modules/promotion/dashboard.php');
