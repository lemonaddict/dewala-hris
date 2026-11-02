<?php
$pageTitle = 'Dasbor Merit & Promosi';
require_once __DIR__ . '/../../includes/bootstrap.php';
requireLogin();

$role = $_SESSION['role'];

if ($role === 'staff') {
    $stmt = $pdo->prepare(
        'SELECT e.*, p.name AS position_name, d.name AS department_name
         FROM employees e
         JOIN positions p ON p.id = e.position_id
         JOIN departments d ON d.id = e.department_id
         WHERE e.id = ?'
    );
    $stmt->execute([$_SESSION['employee_id']]);
    $employees = $stmt->fetchAll();
} elseif ($role === 'manager') {
    $stmt = $pdo->prepare(
        'SELECT e.*, p.name AS position_name, d.name AS department_name
         FROM employees e
         JOIN positions p ON p.id = e.position_id
         JOIN departments d ON d.id = e.department_id
         WHERE e.manager_id = ?
         ORDER BY e.name'
    );
    $stmt->execute([$_SESSION['employee_id']]);
    $employees = $stmt->fetchAll();
} else { // hr
    $employees = $pdo->query(
        'SELECT e.*, p.name AS position_name, d.name AS department_name
         FROM employees e
         JOIN positions p ON p.id = e.position_id
         JOIN departments d ON d.id = e.department_id
         ORDER BY d.name, e.name'
    )->fetchAll();
}

$pendingPromotions = [];
if ($role === 'hr') {
    $pendingPromotions = $pdo->query(
        "SELECT pr.*, e.name AS employee_name, fp.name AS from_name, tp.name AS to_name, req.name AS requested_by_name
         FROM promotions pr
         JOIN employees e ON e.id = pr.employee_id
         JOIN positions fp ON fp.id = pr.from_position_id
         JOIN positions tp ON tp.id = pr.to_position_id
         LEFT JOIN employees req ON req.id = pr.requested_by
         WHERE pr.status = 'pending'
         ORDER BY pr.requested_at ASC"
    )->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($role === 'hr' && $pendingPromotions): ?>
<div class="mb-8">
  <h2 class="text-lg font-semibold text-slate-800 mb-3">&#9203; Pengajuan Promosi Menunggu Finalisasi</h2>
  <div class="bg-white rounded-xl border border-amber-200 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-amber-50 text-amber-800 text-left">
        <tr>
          <th class="px-4 py-3 font-medium">Karyawan</th>
          <th class="px-4 py-3 font-medium">Jalur</th>
          <th class="px-4 py-3 font-medium">Diajukan Oleh</th>
          <th class="px-4 py-3 font-medium">Tanggal</th>
          <th class="px-4 py-3 font-medium text-right">Keputusan</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($pendingPromotions as $p): ?>
        <tr>
          <td class="px-4 py-3 font-medium text-slate-700"><?= e($p['employee_name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($p['from_name']) ?> &rarr; <?= e($p['to_name']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e($p['requested_by_name'] ?? '-') ?></td>
          <td class="px-4 py-3 text-slate-500"><?= formatTanggal($p['requested_at'], 'd M Y H:i') ?></td>
          <td class="px-4 py-3 text-right space-x-2">
            <form method="POST" action="<?= base_url('modules/promotion/decide.php') ?>" class="inline" onsubmit="return confirm('Setujui promosi ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="promotion_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="decision" value="approved">
              <button class="text-emerald-600 hover:text-emerald-800 font-medium">Setujui</button>
            </form>
            <form method="POST" action="<?= base_url('modules/promotion/decide.php') ?>" class="inline" onsubmit="return confirm('Tolak promosi ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="promotion_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="decision" value="rejected">
              <button class="text-red-600 hover:text-red-800 font-medium">Tolak</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<h2 class="text-lg font-semibold text-slate-800 mb-3">
  <?= $role === 'staff' ? 'Progres Karier Saya' : 'Progres Karier Tim' ?>
</h2>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<?php foreach ($employees as $emp): ?>
  <div x-data="promotionCard(<?= (int) $emp['id'] ?>)" x-init="load()"
       class="bg-white rounded-xl border border-slate-200 p-5">
    <div class="flex items-start justify-between mb-3">
      <div>
        <div class="font-semibold text-slate-800"><?= e($emp['name']) ?></div>
        <div class="text-xs text-slate-500"><?= e($emp['position_name']) ?> &middot; <?= e($emp['department_name']) ?></div>
      </div>
      <span x-show="loaded" :class="data.eligible ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
            class="text-xs font-medium px-2.5 py-1 rounded-full border" x-text="data.eligible ? 'Eligible' : 'Belum Eligible'"></span>
    </div>

    <template x-if="!loaded">
      <div class="text-sm text-slate-400 py-4">Memuat data kelayakan...</div>
    </template>

    <template x-if="loaded && !data.target_position">
      <div class="text-sm text-slate-400 py-4">Tidak ada jalur karir yang dikonfigurasi dari posisi ini.</div>
    </template>

    <template x-if="loaded && data.target_position">
      <div>
        <div class="text-xs text-slate-500 mb-1">
          Menuju: <span class="font-medium text-slate-700" x-text="data.target_position.name"></span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2 mb-4">
          <div class="h-2 rounded-full transition-all" :class="data.eligible ? 'bg-emerald-500' : 'bg-amber-500'"
               :style="'width: ' + data.progress + '%'"></div>
        </div>
        <ul class="space-y-2 mb-4">
          <template x-for="c in data.criteria" :key="c.key">
            <li class="flex items-start gap-2 text-sm">
              <span x-text="c.pass ? '\u2705' : '\u274c'"></span>
              <div>
                <div class="font-medium text-slate-700" x-text="c.label"></div>
                <div :class="c.pass ? 'text-slate-500' : 'text-red-600'" x-text="c.detail"></div>
              </div>
            </li>
          </template>
        </ul>

        <?php if (in_array($role, ['manager', 'hr'], true)): ?>
        <form method="POST" action="<?= base_url('modules/promotion/request.php') ?>" @submit="if(!confirm('Ajukan promosi karyawan ini?')) $event.preventDefault();">
          <?= csrf_field() ?>
          <input type="hidden" name="employee_id" value="<?= (int) $emp['id'] ?>">
          <button type="submit" :disabled="!data.eligible"
                  :class="data.eligible ? 'bg-indigo-600 hover:bg-indigo-700 cursor-pointer' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                  class="w-full text-white text-sm font-medium py-2 rounded-lg transition"
                  :title="data.eligible ? 'Ajukan promosi' : 'Belum memenuhi seluruh syarat'">
            Ajukan Promosi
          </button>
        </form>
        <?php else: ?>
        <p class="text-xs text-slate-400 text-center">Pengajuan promosi dilakukan oleh atasan Anda.</p>
        <?php endif; ?>
      </div>
    </template>
  </div>
<?php endforeach; ?>

<?php if (empty($employees)): ?>
  <div class="text-slate-400 text-sm">Belum ada data karyawan pada cakupan Anda.</div>
<?php endif; ?>
</div>

<script>
function promotionCard(employeeId) {
  return {
    loaded: false,
    data: { eligible: false, criteria: [], target_position: null, progress: 0 },
    async load() {
      try {
        const res = await fetch('<?= base_url("api/promotion_check.php") ?>?employee_id=' + employeeId);
        const json = await res.json();
        if (json.criteria) {
          const passCount = json.criteria.filter(c => c.pass).length;
          json.progress = json.criteria.length ? Math.round((passCount / json.criteria.length) * 100) : 0;
        }
        this.data = json;
      } catch (err) {
        this.data = { eligible: false, criteria: [], target_position: null, progress: 0 };
      } finally {
        this.loaded = true;
      }
    }
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
