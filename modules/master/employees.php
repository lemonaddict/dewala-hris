<?php
$pageTitle = 'Karyawan';
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['hr']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name              = trim($_POST['name'] ?? '');
        $email             = trim($_POST['email'] ?? '');
        $departmentId      = (int) ($_POST['department_id'] ?? 0);
        $positionId        = (int) ($_POST['position_id'] ?? 0);
        $managerId         = $_POST['manager_id'] !== '' ? (int) $_POST['manager_id'] : null;
        $hireDate          = $_POST['hire_date'] ?? null;
        $positionStartDate = $_POST['position_start_date'] ?? null;
        $username          = trim($_POST['username'] ?? '');
        $password          = $_POST['password'] ?? '';
        $accountRole       = $_POST['account_role'] ?? 'staff';

        if ($name === '' || $email === '' || $departmentId <= 0 || $positionId <= 0 || !$hireDate || !$positionStartDate) {
            flash('error', 'Nama, email, departemen, jabatan, tanggal masuk, dan tanggal mulai jabatan wajib diisi.');
            redirect('modules/master/employees.php');
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO employees (department_id, position_id, manager_id, name, email, hire_date, position_start_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$departmentId, $positionId, $managerId, $name, $email, $hireDate, $positionStartDate]);
            $employeeId = (int) $pdo->lastInsertId();
            flash('success', 'Karyawan berhasil ditambahkan.');
        } else {
            $employeeId = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE employees SET department_id = ?, position_id = ?, manager_id = ?, name = ?, email = ?, hire_date = ?, position_start_date = ?
                 WHERE id = ?'
            );
            $stmt->execute([$departmentId, $positionId, $managerId, $name, $email, $hireDate, $positionStartDate, $employeeId]);
            flash('success', 'Data karyawan berhasil diperbarui.');
        }

        // Kelola akun login (opsional) — hanya diproses jika username diisi
        if ($username !== '') {
            $existingUserStmt = $pdo->prepare('SELECT user_id FROM employees WHERE id = ?');
            $existingUserStmt->execute([$employeeId]);
            $existingUserId = $existingUserStmt->fetchColumn();

            $dupeStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $dupeStmt->execute([$username, $existingUserId ?: 0]);

            if ($dupeStmt->fetch()) {
                flash('error', 'Karyawan disimpan, tetapi username "' . $username . '" sudah digunakan. Akun login tidak diperbarui.');
            } elseif ($existingUserId) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?');
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $accountRole, $existingUserId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ? WHERE id = ?');
                    $stmt->execute([$username, $accountRole, $existingUserId]);
                }
            } elseif ($password !== '') {
                $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $accountRole]);
                $newUserId = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE employees SET user_id = ? WHERE id = ?')->execute([$newUserId, $employeeId]);
            } else {
                flash('error', 'Karyawan disimpan, tetapi akun login belum dibuat karena password kosong.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $check = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM employees WHERE manager_id = ?) AS total_anak_buah,
                (SELECT COUNT(*) FROM track_records WHERE employee_id = ?) AS total_track,
                (SELECT COUNT(*) FROM competency_scores WHERE employee_id = ?) AS total_score,
                (SELECT COUNT(*) FROM promotions WHERE employee_id = ?) AS total_promo,
                (SELECT user_id FROM employees WHERE id = ?) AS user_id'
        );
        $check->execute([$id, $id, $id, $id, $id]);
        $row = $check->fetch();

        if ((int) $row['total_anak_buah'] > 0) {
            flash('error', 'Karyawan tidak bisa dihapus karena masih menjadi atasan dari karyawan lain.');
        } elseif ((int) $row['total_track'] > 0 || (int) $row['total_score'] > 0 || (int) $row['total_promo'] > 0) {
            flash('error', 'Karyawan tidak bisa dihapus karena masih memiliki riwayat rekam jejak, penilaian, atau pengajuan promosi.');
        } else {
            $pdo->prepare('DELETE FROM employees WHERE id = ?')->execute([$id]);
            if (!empty($row['user_id'])) {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$row['user_id']]);
            }
            flash('success', 'Karyawan berhasil dihapus.');
        }
    }

    redirect('modules/master/employees.php');
}

$employees = $pdo->query(
    'SELECT e.*, p.name AS position_name, d.name AS department_name, u.username, u.role AS account_role,
        m.name AS manager_name
     FROM employees e
     JOIN positions p ON p.id = e.position_id
     JOIN departments d ON d.id = e.department_id
     LEFT JOIN users u ON u.id = e.user_id
     LEFT JOIN employees m ON m.id = e.manager_id
     ORDER BY d.name, e.name'
)->fetchAll();

$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$positions   = $pdo->query('SELECT id, name, department_id FROM positions ORDER BY name')->fetchAll();
$managers    = $pdo->query('SELECT id, name FROM employees ORDER BY name')->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div x-data="employeeForm(<?= htmlspecialchars(json_encode($positions), ENT_QUOTES, 'UTF-8') ?>)">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Data Karyawan</h2>
      <p class="text-sm text-slate-500">Kelola data karyawan beserta akun login (opsional).</p>
    </div>
    <button @click="openCreate()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
      + Tambah Karyawan
    </button>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-left">
        <tr>
          <th class="px-4 py-3 font-medium">Nama</th>
          <th class="px-4 py-3 font-medium">Jabatan</th>
          <th class="px-4 py-3 font-medium">Departemen</th>
          <th class="px-4 py-3 font-medium">Atasan</th>
          <th class="px-4 py-3 font-medium">Akun</th>
          <th class="px-4 py-3 font-medium text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($employees as $emp): ?>
        <tr>
          <td class="px-4 py-3 text-slate-700 font-medium"><?= e($emp['name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($emp['position_name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($emp['department_name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($emp['manager_name'] ?? '-') ?></td>
          <td class="px-4 py-3 text-slate-500">
            <?php if ($emp['username']): ?>
              <span class="font-mono text-xs"><?= e($emp['username']) ?></span>
              <span class="text-xs text-slate-400 uppercase">(<?= e($emp['account_role']) ?>)</span>
            <?php else: ?>
              <span class="text-xs text-slate-400">Tanpa akun</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right space-x-3">
            <button @click="openEdit(<?= htmlspecialchars(json_encode([
                'id' => $emp['id'],
                'name' => $emp['name'],
                'email' => $emp['email'],
                'department_id' => $emp['department_id'],
                'position_id' => $emp['position_id'],
                'manager_id' => $emp['manager_id'],
                'hire_date' => $emp['hire_date'],
                'position_start_date' => $emp['position_start_date'],
                'username' => $emp['username'],
                'account_role' => $emp['account_role'] ?? 'staff',
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
            <form method="POST" class="inline" onsubmit="return confirm('Hapus karyawan ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
              <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($employees)): ?>
        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada data karyawan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div x-show="open" x-cloak class="fixed inset-0 bg-slate-900/40 flex items-center justify-center px-4 py-8 z-50 overflow-y-auto">
    <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 my-auto">
      <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="form.id ? 'Edit Karyawan' : 'Tambah Karyawan'"></h3>
      <form method="POST" class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
        <?= csrf_field() ?>
        <input type="hidden" name="action" :value="form.id ? 'update' : 'create'">
        <input type="hidden" name="id" :value="form.id">

        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
            <input type="text" name="name" x-model="form.name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" name="email" x-model="form.email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Departemen</label>
            <select name="department_id" x-model="form.department_id" @change="form.position_id = ''" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option value="">-- Pilih --</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Jabatan</label>
            <select name="position_id" x-model="form.position_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option value="">-- Pilih Departemen Dahulu --</option>
              <template x-for="pos in filteredPositions()" :key="pos.id">
                <option :value="pos.id" x-text="pos.name" :selected="String(pos.id) === String(form.position_id)"></option>
              </template>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Atasan Langsung (opsional)</label>
            <select name="manager_id" x-model="form.manager_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option value="">-- Tidak Ada --</option>
              <?php foreach ($managers as $m): ?>
                <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Masuk Kerja</label>
            <input type="date" name="hire_date" x-model="form.hire_date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Mulai Menjabat Posisi Ini</label>
            <input type="date" name="position_start_date" x-model="form.position_start_date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
        </div>

        <div class="border-t border-slate-100 pt-4">
          <p class="text-sm font-medium text-slate-700 mb-2">Akun Login (opsional)</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-slate-500 mb-1">Username</label>
              <input type="text" name="username" x-model="form.username" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs text-slate-500 mb-1">Role Akun</label>
              <select name="account_role" x-model="form.account_role" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="staff">Staff</option>
                <option value="manager">Manajer</option>
                <option value="hr">HR / Super Admin</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-xs text-slate-500 mb-1">
                Password <span x-show="form.id" class="text-slate-400">(kosongkan jika tidak ingin mengubah)</span>
              </label>
              <input type="password" name="password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="open = false" class="px-4 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100">Batal</button>
          <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function employeeForm(positions) {
  return {
    open: false,
    positions: positions,
    form: {
      id: null, name: '', email: '', department_id: '', position_id: '',
      manager_id: '', hire_date: '', position_start_date: '', username: '', account_role: 'staff'
    },
    openCreate() {
      this.form = { id: null, name: '', email: '', department_id: '', position_id: '', manager_id: '', hire_date: '', position_start_date: '', username: '', account_role: 'staff' };
      this.open = true;
    },
    openEdit(data) {
      this.form = {
        id: data.id, name: data.name, email: data.email,
        department_id: String(data.department_id), position_id: String(data.position_id),
        manager_id: data.manager_id ? String(data.manager_id) : '',
        hire_date: data.hire_date, position_start_date: data.position_start_date,
        username: data.username || '', account_role: data.account_role || 'staff'
      };
      this.open = true;
    },
    filteredPositions() {
      if (!this.form.department_id) return [];
      return this.positions.filter(p => String(p.department_id) === String(this.form.department_id));
    }
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
