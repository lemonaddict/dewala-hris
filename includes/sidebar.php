<?php
$role = $_SESSION['role'] ?? null;
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

function navActive(string $needle, string $scriptName): string
{
    return str_contains($scriptName, $needle) ? 'bg-slate-800 text-white' : 'text-slate-300';
}
?>
<aside class="w-64 bg-slate-900 text-slate-200 flex flex-col shrink-0">
  <div class="px-6 py-5 border-b border-slate-800">
    <div class="text-xl font-bold text-white">Dewala</div>
    <div class="text-xs text-slate-400">Mini HRIS &middot; Sistem Promosi Merit</div>
  </div>
  <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
    <a href="<?= base_url('modules/promotion/dashboard.php') ?>"
       class="block px-3 py-2 rounded-lg hover:bg-slate-800 <?= navActive('promotion/dashboard', $scriptName) ?>">
      &#128202; Dasbor Merit &amp; Promosi
    </a>

    <?php if (in_array($role, ['hr', 'manager'], true)): ?>
      <a href="<?= base_url('modules/competency/index.php') ?>"
         class="block px-3 py-2 rounded-lg hover:bg-slate-800 <?= navActive('competency', $scriptName) ?>">
        &#129517; Penilaian Kompetensi
      </a>
      <a href="<?= base_url('modules/track-record/index.php') ?>"
         class="block px-3 py-2 rounded-lg hover:bg-slate-800 <?= navActive('track-record', $scriptName) ?>">
        &#128203; Rekam Jejak
      </a>
    <?php endif; ?>

    <?php if ($role === 'hr'): ?>
      <div class="pt-4 pb-1 px-3 text-xs uppercase tracking-wide text-slate-500">Data Master</div>
      <a href="<?= base_url('modules/master/departments.php') ?>"
         class="block px-3 py-2 rounded-lg hover:bg-slate-800 <?= navActive('departments', $scriptName) ?>">
        &#127970; Departemen
      </a>
      <a href="<?= base_url('modules/master/positions.php') ?>"
         class="block px-3 py-2 rounded-lg hover:bg-slate-800 <?= navActive('positions', $scriptName) ?>">
        &#128188; Jabatan
      </a>
      <a href="<?= base_url('modules/master/employees.php') ?>"
         class="block px-3 py-2 rounded-lg hover:bg-slate-800 <?= navActive('employees', $scriptName) ?>">
        &#128101; Karyawan
      </a>
      <a href="<?= base_url('modules/career-path/index.php') ?>"
         class="block px-3 py-2 rounded-lg hover:bg-slate-800 <?= navActive('career-path', $scriptName) ?>">
        &#128640; Career Path Configurator
      </a>
    <?php endif; ?>
  </nav>
  <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500">Dewala HRIS &copy; <?= date('Y') ?></div>
</aside>
