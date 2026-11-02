<?php

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

/**
 * Set pesan flash (jika $message diisi) atau ambil & hapus pesan flash (jika kosong).
 */
function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    return null;
}

function formatTanggal(?string $date, string $fmt = 'd M Y'): string
{
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '-';
}

/**
 * Menghitung selisih bulan antara sebuah tanggal dengan hari ini (atau tanggal akhir tertentu).
 */
function monthsBetween(string $start, ?string $end = null): int
{
    $startDate = new DateTime($start);
    $endDate = $end ? new DateTime($end) : new DateTime();
    if ($startDate > $endDate) {
        return 0;
    }
    $diff = $startDate->diff($endDate);
    return ($diff->y * 12) + $diff->m;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Token CSRF tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}
