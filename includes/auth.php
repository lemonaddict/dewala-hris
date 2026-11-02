<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . base_url('modules/auth/login.php'));
        exit;
    }
}

/**
 * Membatasi akses halaman hanya untuk role tertentu.
 * Dipanggil di awal setiap file modul sesuai wewenang di tabel RBAC.
 *
 * @param string[] $roles
 */
function requireRole(array $roles): void
{
    requireLogin();

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
              <title>Akses Ditolak</title>
              <script src="https://cdn.tailwindcss.com"></script></head>
              <body class="min-h-screen flex items-center justify-center bg-slate-50">
                <div class="text-center max-w-sm">
                  <div class="text-4xl mb-3">🚫</div>
                  <h2 class="text-lg font-semibold text-slate-800 mb-1">Akses Ditolak</h2>
                  <p class="text-sm text-slate-500 mb-4">Anda tidak memiliki wewenang untuk mengakses halaman ini.</p>
                  <a href="' . e(base_url('modules/promotion/dashboard.php')) . '" class="text-indigo-600 text-sm font-medium">Kembali ke Dasbor</a>
                </div>
              </body></html>';
        exit;
    }
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'            => $_SESSION['user_id'],
        'username'      => $_SESSION['username'],
        'role'          => $_SESSION['role'],
        'employee_id'   => $_SESSION['employee_id'] ?? null,
        'employee_name' => $_SESSION['employee_name'] ?? null,
    ];
}
