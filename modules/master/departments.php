<?php
$pageTitle = 'Departemen';
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['hr']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            flash('error', 'Nama departemen wajib diisi.');
        } elseif ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO departments (name) VALUES (?)');
            $stmt->execute([$name]);
            flash('success', 'Departemen berhasil ditambahkan.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE departments SET name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            flash('success', 'Departemen berhasil diperbarui.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $check = $pdo->prepare('SELECT COUNT(*) AS jumlah FROM positions WHERE department_id = ?');
        $check->execute([$id]);

        if ((int) $check->fetch()['jumlah'] > 0) {
            flash('error', 'Departemen tidak bisa dihapus karena masih memiliki jabatan terkait.');
        } else {
            $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
            flash('success', 'Departemen berhasil dihapus.');
        }
    }

    redirect('modules/master/departments.php');
}

$departments = $pdo->query(
    'SELECT d.*, (SELECT COUNT(*) FROM positions p WHERE p.department_id = d.id) AS total_posisi
     FROM departments d ORDER BY d.name'
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div x-data="{ open: false, editId: null, editName: '' }">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Data Departemen</h2>
      <p class="text-sm text-slate-500">Kelola daftar departemen dalam organisasi.</p>
    </div>
    <button @click="open = true; editId = null; editName = ''"
            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
      + Tambah Departemen
    </button>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-left">
        <tr>
          <th class="px-4 py-3 font-medium">Nama Departemen</th>
          <th class="px-4 py-3 font-medium">Jumlah Jabatan</th>
          <th class="px-4 py-3 font-medium text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($departments as $dept): ?>
        <tr>
          <td class="px-4 py-3 text-slate-700"><?= e($dept['name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= (int) $dept['total_posisi'] ?> jabatan</td>
          <td class="px-4 py-3 text-right space-x-3">
            <button
              @click="open = true; editId = <?= (int) $dept['id'] ?>; editName = '<?= e(addslashes($dept['name'])) ?>'"
              class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
            <form method="POST" class="inline" onsubmit="return confirm('Hapus departemen ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $dept['id'] ?>">
              <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($departments)): ?>
        <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">Belum ada data departemen.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div x-show="open" x-cloak class="fixed inset-0 bg-slate-900/40 flex items-center justify-center px-4 z-50">
    <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="editId ? 'Edit Departemen' : 'Tambah Departemen'"></h3>
      <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" :value="editId ? 'update' : 'create'">
        <input type="hidden" name="id" :value="editId">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nama Departemen</label>
          <input type="text" name="name" x-model="editName" required
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
