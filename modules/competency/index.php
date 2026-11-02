<?php
$pageTitle = 'Penilaian Kompetensi';
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['hr', 'manager']);

$role = $_SESSION['role'];

function scopedEmployeeIdsComp(PDO $pdo, string $role, ?int $employeeId): ?array
{
    if ($role === 'hr') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM employees WHERE manager_id = ?');
    $stmt->execute([$employeeId]);
    return array_column($stmt->fetchAll(), 'id');
}

$scope = scopedEmployeeIdsComp($pdo, $role, $_SESSION['employee_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $technical  = (float) ($_POST['technical_score'] ?? 0);
        $softskill  = (float) ($_POST['softskill_score'] ?? 0);
        $period     = trim($_POST['period'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');

        if ($period === '' || $technical < 0 || $technical > 100 || $softskill < 0 || $softskill > 100) {
            flash('error', 'Periode wajib diisi dan skor harus di antara 0-100.');
        } elseif ($scope !== null && !in_array($employeeId, $scope, true)) {
            flash('error', 'Anda tidak berwenang menilai karyawan ini.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO competency_scores (employee_id, technical_score, softskill_score, period, notes, scored_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$employeeId, $technical, $softskill, $period, $notes ?: null, $_SESSION['employee_id']]);
            flash('success', 'Penilaian kompetensi berhasil disimpan.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT employee_id FROM competency_scores WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row || ($scope !== null && !in_array((int) $row['employee_id'], $scope, true))) {
            flash('error', 'Anda tidak berwenang menghapus data ini.');
        } else {
            $pdo->prepare('DELETE FROM competency_scores WHERE id = ?')->execute([$id]);
            flash('success', 'Data penilaian berhasil dihapus.');
        }
    }

    redirect('modules/competency/index.php');
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

$scoreQuery = "SELECT cs.*, e.name AS employee_name, s.name AS scored_by_name
               FROM competency_scores cs
               JOIN employees e ON e.id = cs.employee_id
               LEFT JOIN employees s ON s.id = cs.scored_by";
$scoreParams = [];
if ($scope !== null) {
    $scopeForQuery = empty($scope) ? [0] : $scope;
    $placeholders = implode(',', array_fill(0, count($scopeForQuery), '?'));
    $scoreQuery .= " WHERE cs.employee_id IN ($placeholders)";
    $scoreParams = $scopeForQuery;
}
$scoreQuery .= ' ORDER BY cs.created_at DESC LIMIT 100';
$stmt = $pdo->prepare($scoreQuery);
$stmt->execute($scoreParams);
$scores = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div x-data="{ open: false }">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Penilaian Kompetensi</h2>
      <p class="text-sm text-slate-500">Skor teknikal &amp; soft-skill menjadi salah satu syarat kelayakan promosi.</p>
    </div>
    <button @click="open = true" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
      + Tambah Penilaian
    </button>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-left">
        <tr>
          <th class="px-4 py-3 font-medium">Karyawan</th>
          <th class="px-4 py-3 font-medium">Periode</th>
          <th class="px-4 py-3 font-medium">Teknikal</th>
          <th class="px-4 py-3 font-medium">Soft-skill</th>
          <th class="px-4 py-3 font-medium">Rata-rata</th>
          <th class="px-4 py-3 font-medium">Dinilai Oleh</th>
          <th class="px-4 py-3 font-medium text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($scores as $s): ?>
        <?php $avg = round(((float) $s['technical_score'] + (float) $s['softskill_score']) / 2, 1); ?>
        <tr>
          <td class="px-4 py-3 text-slate-700 font-medium"><?= e($s['employee_name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($s['period']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($s['technical_score']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($s['softskill_score']) ?></td>
          <td class="px-4 py-3 font-medium text-slate-700"><?= e($avg) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($s['scored_by_name'] ?? '-') ?></td>
          <td class="px-4 py-3 text-right">
            <form method="POST" class="inline" onsubmit="return confirm('Hapus data penilaian ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($scores)): ?>
        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada data penilaian.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div x-show="open" x-cloak class="fixed inset-0 bg-slate-900/40 flex items-center justify-center px-4 z-50">
    <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4">Tambah Penilaian Kompetensi</h3>
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
          <label class="block text-sm font-medium text-slate-700 mb-1">Periode (mis. 2026-Q3)</label>
          <input type="text" name="period" required placeholder="2026-Q3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Skor Teknikal</label>
            <input type="number" step="0.1" min="0" max="100" name="technical_score" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Skor Soft-skill</label>
            <input type="number" step="0.1" min="0" max="100" name="softskill_score" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Catatan (opsional)</label>
          <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="open = false" class="px-4 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100">Batal</button>
          <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
