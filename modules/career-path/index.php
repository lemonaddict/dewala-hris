<?php
$pageTitle = 'Career Path Configurator';
require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['hr']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $fromId   = (int) ($_POST['from_position_id'] ?? 0);
        $toId     = (int) ($_POST['to_position_id'] ?? 0);
        $tenure   = (int) ($_POST['min_tenure_months'] ?? 0);
        $score    = (float) ($_POST['min_score'] ?? 0);
        $lateness = (int) ($_POST['max_lateness_6m'] ?? 0);
        $lockout  = (int) ($_POST['sp_lockout_months'] ?? 12);

        if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
            flash('error', 'Jabatan asal dan tujuan wajib dipilih dan tidak boleh sama.');
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO career_paths (from_position_id, to_position_id, min_tenure_months, min_score, max_lateness_6m, sp_lockout_months)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$fromId, $toId, $tenure, $score, $lateness, $lockout]);
                    flash('success', 'Career path berhasil ditambahkan.');
                } else {
                    $id = (int) ($_POST['id'] ?? 0);
                    $stmt = $pdo->prepare(
                        'UPDATE career_paths SET from_position_id = ?, to_position_id = ?, min_tenure_months = ?, min_score = ?, max_lateness_6m = ?, sp_lockout_months = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([$fromId, $toId, $tenure, $score, $lateness, $lockout, $id]);
                    flash('success', 'Career path berhasil diperbarui.');
                }
            } catch (PDOException $e) {
                flash('error', 'Gagal menyimpan: jalur karir dari jabatan ini mungkin sudah terdaftar.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM career_paths WHERE id = ?')->execute([$id]);
        flash('success', 'Career path berhasil dihapus.');
    }

    redirect('modules/career-path/index.php');
}

$paths = $pdo->query(
    'SELECT cp.*, fp.name AS from_name, tp.name AS to_name, fd.name AS from_dept
     FROM career_paths cp
     JOIN positions fp ON fp.id = cp.from_position_id
     JOIN positions tp ON tp.id = cp.to_position_id
     JOIN departments fd ON fd.id = fp.department_id
     ORDER BY fd.name, fp.level'
)->fetchAll();

$positions = $pdo->query('SELECT id, name, department_id, level FROM positions ORDER BY department_id, level')->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div x-data="careerPathForm(<?= htmlspecialchars(json_encode($positions), ENT_QUOTES, 'UTF-8') ?>)">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Career Path Configurator</h2>
      <p class="text-sm text-slate-500">Petakan alur jenjang jabatan beserta kriteria minimum tiap tingkat.</p>
    </div>
    <button @click="openCreate()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
      + Tambah Jalur
    </button>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-left">
        <tr>
          <th class="px-4 py-3 font-medium">Jalur</th>
          <th class="px-4 py-3 font-medium">Min. Masa Kerja</th>
          <th class="px-4 py-3 font-medium">Min. Skor Kompetensi</th>
          <th class="px-4 py-3 font-medium">Maks. Keterlambatan (6bln)</th>
          <th class="px-4 py-3 font-medium">Lockout SP</th>
          <th class="px-4 py-3 font-medium text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($paths as $p): ?>
        <tr>
          <td class="px-4 py-3 text-slate-700 font-medium"><?= e($p['from_name']) ?> &rarr; <?= e($p['to_name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= (int) $p['min_tenure_months'] ?> bulan</td>
          <td class="px-4 py-3 text-slate-500">&ge; <?= e($p['min_score']) ?></td>
          <td class="px-4 py-3 text-slate-500">&le; <?= (int) $p['max_lateness_6m'] ?> kali</td>
          <td class="px-4 py-3 text-slate-500"><?= (int) $p['sp_lockout_months'] ?> bulan</td>
          <td class="px-4 py-3 text-right space-x-3">
            <button @click="openEdit(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
            <form method="POST" class="inline" onsubmit="return confirm('Hapus career path ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($paths)): ?>
        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada career path yang dikonfigurasi.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div x-show="open" x-cloak class="fixed inset-0 bg-slate-900/40 flex items-center justify-center px-4 z-50">
    <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4" x-text="form.id ? 'Edit Career Path' : 'Tambah Career Path'"></h3>
      <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" :value="form.id ? 'update' : 'create'">
        <input type="hidden" name="id" :value="form.id">

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Dari Jabatan</label>
          <select name="from_position_id" x-model="form.from_position_id" @change="form.to_position_id = ''" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">-- Pilih --</option>
            <template x-for="pos in positions" :key="pos.id">
              <option :value="pos.id" x-text="pos.name" :selected="String(pos.id) === String(form.from_position_id)"></option>
            </template>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Ke Jabatan</label>
          <select name="to_position_id" x-model="form.to_position_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">-- Pilih Jabatan Asal Dahulu --</option>
            <template x-for="pos in toOptions()" :key="pos.id">
              <option :value="pos.id" x-text="pos.name" :selected="String(pos.id) === String(form.to_position_id)"></option>
            </template>
          </select>
          <p class="text-xs text-slate-400 mt-1">Jabatan satu departemen dengan level lebih tinggi ditampilkan lebih dulu.</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Min. Masa Kerja (bulan)</label>
            <input type="number" name="min_tenure_months" x-model="form.min_tenure_months" min="0" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Min. Skor Kompetensi</label>
            <input type="number" step="0.1" name="min_score" x-model="form.min_score" min="0" max="100" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Maks. Keterlambatan (6bln)</label>
            <input type="number" name="max_lateness_6m" x-model="form.max_lateness_6m" min="0" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Lockout SP (bulan)</label>
            <input type="number" name="sp_lockout_months" x-model="form.sp_lockout_months" min="0" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
function careerPathForm(positions) {
  return {
    open: false,
    positions: positions,
    form: { id: null, from_position_id: '', to_position_id: '', min_tenure_months: 12, min_score: 80, max_lateness_6m: 5, sp_lockout_months: 12 },
    openCreate() {
      this.form = { id: null, from_position_id: '', to_position_id: '', min_tenure_months: 12, min_score: 80, max_lateness_6m: 5, sp_lockout_months: 12 };
      this.open = true;
    },
    openEdit(data) {
      this.form = {
        id: data.id,
        from_position_id: String(data.from_position_id),
        to_position_id: String(data.to_position_id),
        min_tenure_months: data.min_tenure_months,
        min_score: data.min_score,
        max_lateness_6m: data.max_lateness_6m,
        sp_lockout_months: data.sp_lockout_months
      };
      this.open = true;
    },
    // Select chaining: begitu jabatan asal dipilih, opsi jabatan tujuan
    // diprioritaskan pada jabatan satu departemen dengan level lebih tinggi.
    toOptions() {
      if (!this.form.from_position_id) return [];
      const from = this.positions.find(p => String(p.id) === String(this.form.from_position_id));
      if (!from) return this.positions.filter(p => String(p.id) !== String(this.form.from_position_id));

      const sameDept = this.positions.filter(p => p.department_id === from.department_id && p.id !== from.id);
      const otherDept = this.positions.filter(p => p.department_id !== from.department_id);
      return [...sameDept, ...otherDept];
    }
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
