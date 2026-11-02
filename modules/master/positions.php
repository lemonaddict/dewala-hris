<?php
$pageTitle = 'Jabatan';
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['hr']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name         = trim($_POST['name'] ?? '');
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $level        = (int) ($_POST['level'] ?? 1);

        if ($name === '' || $departmentId <= 0) {
            flash('error', 'Nama jabatan dan departemen wajib diisi.');
        } elseif ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO positions (department_id, name, level) VALUES (?, ?, ?)');
            $stmt->execute([$departmentId, $name, $level]);
            flash('success', 'Jabatan berhasil ditambahkan.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE positions SET department_id = ?, name = ?, level = ? WHERE id = ?');
            $stmt->execute([$departmentId, $name, $level, $id]);
            flash('success', 'Jabatan berhasil diperbarui.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $check = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM employees WHERE position_id = ?) AS total_karyawan,
                (SELECT COUNT(*) FROM career_paths WHERE from_position_id = ? OR to_position_id = ?) AS total_path'
        );
        $check->execute([$id, $id, $id]);
        $row = $check->fetch();

        if ((int) $row['total_karyawan'] > 0) {
            flash('error', 'Jabatan tidak bisa dihapus karena masih digunakan oleh karyawan.');
        } elseif ((int) $row['total_path'] > 0) {
            flash('error', 'Jabatan tidak bisa dihapus karena masih terhubung dalam Career Path.');
        } else {
            $pdo->prepare('DELETE FROM positions WHERE id = ?')->execute([$id]);
            flash('success', 'Jabatan berhasil dihapus.');
        }
    }

    redirect('modules/master/positions.php');
}

$positions = $pdo->query(
    'SELECT p.*, d.name AS department_name,
        (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.id) AS total_karyawan
     FROM positions p
     JOIN departments d ON d.id = p.department_id
     ORDER BY d.name, p.level'
)->fetchAll();

$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div x-data="{ open: false, editId: null, editName: '', editDept: '', editLevel: 1 }">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Data Jabatan</h2>
      <p class="text-sm text-slate-500">Kelola daftar jabatan (career title) per departemen.</p>
    </div>
    <button @click="open = true; editId = null; editName = ''; editDept = ''; editLevel = 1"
            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
      + Tambah Jabatan
    </button>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-left">
        <tr>
          <th class="px-4 py-3 font-medium">Nama Jabatan</th>
          <th class="px-4 py-3 font-medium">Departemen</th>
          <th class="px-4 py-3 font-medium">Level</th>
          <th class="px-4 py-3 font-medium">Karyawan</th>
          <th class="px-4 py-3 font-medium text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($positions as $pos): ?>
        <tr>
          <td class="px-4 py-3 text-slate-700"><?= e($pos['name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($pos['department_name']) ?></td>
          <td class="px-4 py-3 text-slate-500">Level <?= (int) $pos['level'] ?></td>
          <td class="px-4 py-3 text-slate-500"><?= (int) $pos['total_karyawan'] ?> orang</td>
          <td class="px-4 py-3 text-right space-x-3">
            <button
              @click="open = true; editId = <?= (int) $pos['id'] ?>; editName = '<?= e(addslashes($pos['name'])) ?>'; editDept = '<?= (int) $pos['department_id'] ?>'; editLevel = <?= (int) $pos['level'] ?>"
              class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
            <form method="POST" class="inline" onsubmit="return confirm('Hapus jabatan ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $pos['id'] ?>">
              <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($positions)): ?>
        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada data jabatan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div x-show="open" x-cloak class="fixed inset-0 bg-slate-900/40 flex items-center justify-center px-4 z-50">
    <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="editId ? 'Edit Jabatan' : 'Tambah Jabatan'"></h3>
      <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" :value="editId ? 'update' : 'create'">
        <input type="hidden" name="id" :value="editId">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nama Jabatan</label>
          <input type="text" name="name" x-model="editName" required
                 class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Departemen</label>
          <select name="department_id" x-model="editDept" required
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">-- Pilih Departemen --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Level Jabatan (urutan hierarki)</label>
          <input type="number" name="level" x-model="editLevel" min="1" required
                 class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
