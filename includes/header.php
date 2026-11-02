<?php
/** @var string $pageTitle diset oleh masing-masing halaman modul sebelum include file ini */
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Dewala HRIS') ?> · Dewala HRIS</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style>[x-cloak] { display: none !important; }</style>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' }
        }
      }
    }
  }
</script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="flex-1 flex flex-col min-h-screen">
  <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
    <h1 class="text-lg font-semibold text-slate-800"><?= e($pageTitle ?? '') ?></h1>
    <div class="flex items-center gap-3">
      <div class="text-right">
        <div class="text-sm font-medium text-slate-700"><?= e($user['employee_name'] ?? $user['username']) ?></div>
        <div class="text-xs text-slate-400 uppercase"><?= e($user['role']) ?></div>
      </div>
      <a href="<?= base_url('modules/auth/logout.php') ?>" class="text-sm text-red-600 hover:text-red-700 font-medium">Keluar</a>
    </div>
  </header>
  <main class="flex-1 p-6">
    <?php $successMsg = flash('success'); $errorMsg = flash('error'); ?>
    <?php if ($successMsg): ?>
      <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm"><?= e($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
      <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><?= e($errorMsg) ?></div>
    <?php endif; ?>
