<?php
// Konfigurasi koneksi database MySQL
// Sesuaikan kredensial berikut dengan environment Anda (XAMPP/Laragon/dll)

$DB_HOST = 'localhost';
$DB_NAME = 'dewala_hris';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal. Pastikan MySQL berjalan dan database "dewala_hris" sudah dibuat. Detail: ' . $e->getMessage());
}
