<?php
// File konfigurasi umum aplikasi Dewala HRIS

define('APP_ROOT_PATH', dirname(__DIR__));

/**
 * Menghasilkan URL relatif terhadap root aplikasi.
 * Otomatis mendeteksi apakah aplikasi berjalan di root domain
 * atau di dalam subfolder (misal: http://localhost/dewala-hris/).
 */
function base_url(string $path = ''): string
{
    static $baseUrl = null;

    if ($baseUrl === null) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $appRoot = str_replace('\\', '/', APP_ROOT_PATH);

        if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
            $baseUrl = substr($appRoot, strlen($docRoot));
        } else {
            $baseUrl = '';
        }
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}
