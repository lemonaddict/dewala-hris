<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (isLoggedIn()) {
    redirect('modules/promotion/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];

        $empStmt = $pdo->prepare('SELECT id, name FROM employees WHERE user_id = ? LIMIT 1');
        $empStmt->execute([$user['id']]);
        $emp = $empStmt->fetch();

        $_SESSION['employee_id']   = $emp['id'] ?? null;
        $_SESSION['employee_name'] = $emp['name'] ?? null;

        redirect('modules/promotion/dashboard.php');
    }

    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk &middot; Dewala HRIS</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-sm bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
    <div class="text-center mb-6">
      <div class="text-2xl font-bold text-slate-900">Dewala</div>
      <div class="text-sm text-slate-500">Mini HRIS &middot; Sistem Promosi Merit</div>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
        <input type="text" name="username" required autofocus
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
        <input type="password" name="password" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
      </div>
      <button type="submit"
              class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg text-sm transition">
        Masuk
      </button>
    </form>

    <div class="mt-6 text-xs text-slate-400 border-t border-slate-100 pt-4">
      <p class="font-medium text-slate-500 mb-1">Akun demo (password: password123):</p>
      <p>HR &nbsp;&nbsp;&nbsp;&nbsp;: <span class="font-mono">hr_admin</span></p>
      <p>Manajer : <span class="font-mono">budi_manager</span></p>
      <p>Staff &nbsp;&nbsp;: <span class="font-mono">andi_staff</span> / <span class="font-mono">citra_staff</span> / <span class="font-mono">dedi_staff</span> / <span class="font-mono">eka_staff</span></p>
    </div>
  </div>
</body>
</html>
