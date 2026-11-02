<?php
$pageTitle = 'Rekam Jejak Karyawan';
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['hr', 'manager']);

$role = $_SESSION['role'];

/**
 * Mengembalikan daftar ID karyawan yang boleh diakses oleh user saat ini.
 * null = tanpa batasan (HR).
 */
function scopedEmployeeIds(PDO $pdo, string $role, ?int $employeeId): ?array
{
    if ($role === 'hr') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM employees WHERE manager_id = ? OR id = ?');
    $stmt->execute([$employeeId, $employeeId]);
    return array_column($stmt->fetchAll(), 'id');
}

$scope = scopedEmployeeIds($pdo, $role, $_SESSION['employee_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $employeeId  = (int) ($_POST['employee_id'] ?? 0);
        $type        = $_POST['type'] ?? '';
        $recordDate  = $_POST['record_date'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        $validTypes = ['keterlambatan', 'sp1', 'sp2', 'sp3', 'catatan'];

        if (!in_array($type, $validTypes, true) || !$recordDate) {
            flash('error', 'Jenis rekam jejak dan tanggal wajib diisi.');
        } elseif ($scope !== null && !in_array($employeeId, $scope, true)) {
            flash('error', 'Anda tidak berwenang mencatat rekam jejak untuk karyawan ini.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO track_records (employee_id, type, record_date, description, is_active, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$employeeId, $type, $recordDate, $description ?: null, $isActive, $_SESSION['employee_id']]);
            flash('success', 'Rekam jejak berhasil dicatat.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT employee_id FROM track_records WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row || ($scope !== null && !in_array((int) $row['employee_id'], $scope, true))) {
            flash('error', 'Anda tidak berwenang menghapus data ini.');
        } else {
            $pdo->prepare('DELETE FROM track_records WHERE id = ?')->execute([$id]);
            flash('success', 'Rekam jejak berhasil dihapus.');
        }
    }

    redirect('modules/track-record/index.php');
}

$employeeQuery = 'SELECT e.id, e.name FROM employees e';
$params = [];
if ($scope !== null) {
    $scopeForQuery = empty($scope) ? [0] : $scope;
    $placeholders = implode(',', array_fill(0, count($scopeForQuery), '?'));
    $employeeQuery .= " WHERE e.id IN ($placeholders)";
    $params = $scopeForQuery;
}
$employeeQuery .= ' ORDER BY e.name';
$stmt = $pdo->prepare($employeeQuery);
$stmt->execute($params);
$scopedEmployees = $stmt->fetchAll();

$recordQuery = "SELECT tr.*, e.name AS employee_name, c.name AS created_by_name
                FROM track_records tr
                JOIN employees e ON e.id = tr.employee_id
                LEFT JOIN employees c ON c.id = tr.created_by";
$recordParams = [];
if ($scope !== null) {
    $scopeForQuery = empty($scope) ? [0] : $scope;
    $placeholders = implode(',', array_fill(0, count($scopeForQuery), '?'));
    $recordQuery .= " WHERE tr.employee_id IN ($placeholders)";
    $recordParams = $scopeForQuery;
}
$recordQuery .= ' ORDER BY tr.record_date DESC, tr.id DESC LIMIT 100';
$stmt = $pdo->prepare($recordQuery);
$stmt->execute($recordParams);
$records = $stmt->fetchAll();

$typeLabels = [
    'keterlambatan' => 'Keterlambatan',
    'sp1'           => 'SP 1',
    'sp2'           => 'SP 2',
    'sp3'           => 'SP 3',
    'catatan'       => 'Catatan',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div x-data="{ open: false }">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Rekam Jejak Karyawan</h2>
      <p class="text-sm text-slate-500">Catat keterlambatan, Surat Peringatan (SP), atau catatan kinerja lainnya.</p>
    </div>
    <button @click="open = true" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
      + Catat Rekam Jejak
    </button>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-left">
        <tr>
          <th class="px-4 py-3 font-medium">Karyawan</th>
          <th class="px-4 py-3 font-medium">Jenis</th>
          <th class="px-4 py-3 font-medium">Tanggal</th>
          <th class="px-4 py-3 font-medium">Keterangan</th>
          <th class="px-4 py-3 font-medium">Status</th>
          <th class="px-4 py-3 font-medium text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($records as $r): ?>
        <tr>
          <td class="px-4 py-3 text-slate-700 font-medium"><?= e($r['employee_name']) ?></td>
          <td class="px-4 py-3">
            <span class="text-xs font-medium px-2 py-1 rounded-full
              <?= str_starts_with($r['type'], 'sp') ? 'bg-red-50 text-red-700' : ($r['type'] === 'keterlambatan' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') ?>">
              <?= e($typeLabels[$r['type']] ?? $r['type']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-500"><?= formatTanggal($r['record_date']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($r['description'] ?: '-') ?></td>
          <td class="px-4 py-3 text-slate-500"><?= $r['is_active'] ? 'Aktif' : 'Tidak Aktif' ?></td>
          <td class="px-4 py-3 text-right">
            <form method="POST" class="inline" onsubmit="return confirm('Hapus rekam jejak ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($records)): ?>
        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada rekam jejak tercatat.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div x-show="open" x-cloak class="fixed inset-0 bg-slate-900/40 flex items-center justify-center px-4 z-50">
    <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4">Catat Rekam Jejak</h3>
      <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Karyawan</label>
          <select name="employee_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">-- Pilih Karyawan --</option>
            <?php foreach ($scopedEmployees as $emp): ?>
              <option value="<?= (int) $emp['id'] ?>"><?= e($emp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Jenis</label>
          <select name="type" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="keterlambatan">Keterlambatan</option>
            <option value="sp1">SP 1</option>
            <option value="sp2">SP 2</option>
            <option value="sp3">SP 3</option>
            <option value="catatan">Catatan</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal</label>
          <input type="date" name="record_date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Keterangan (opsional)</label>
          <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" name="is_active" checked class="rounded border-slate-300">
          Status aktif (berlaku sebagai pengurang nilai promosi)
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="open = false" class="px-4 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100">Batal</button>
          <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
