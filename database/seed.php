<?php
/**
 * Seeder data contoh untuk Dewala HRIS.
 * Jalankan sekali setelah schema.sql diimport:
 *   php database/seed.php
 * atau akses lewat browser: http://localhost/dewala-hris/database/seed.php
 *
 * Tanggal masa kerja dibuat relatif terhadap CURDATE() (bukan hardcode),
 * supaya skenario kelayakan tetap masuk akal kapan pun seeder dijalankan.
 */

require_once __DIR__ . '/../config/database.php';

echo "Menjalankan seeder Dewala HRIS...\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['promotions', 'track_records', 'competency_scores', 'career_paths', 'employees', 'positions', 'users', 'departments'] as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// 1. Departemen
$pdo->exec("INSERT INTO departments (id, name) VALUES
    (1, 'Engineering'),
    (2, 'Product'),
    (3, 'Human Resources')");

// 2. Jabatan (career title berjenjang per departemen)
$pdo->exec("INSERT INTO positions (id, department_id, name, level) VALUES
    (1, 1, 'Junior Developer', 1),
    (2, 1, 'Mid Developer', 2),
    (3, 1, 'Senior Developer', 3),
    (4, 1, 'Team Lead', 4),
    (5, 2, 'Product Analyst', 1),
    (6, 2, 'Product Manager', 2),
    (7, 3, 'HR Staff', 1),
    (8, 3, 'HR Manager', 2)");

// 3. Career Path: kriteria minimum tiap jenjang
$pdo->exec("INSERT INTO career_paths (from_position_id, to_position_id, min_tenure_months, min_score, max_lateness_6m, sp_lockout_months) VALUES
    (1, 2, 12, 80, 5, 12),
    (2, 3, 18, 82, 5, 12),
    (3, 4, 24, 85, 3, 12),
    (5, 6, 15, 80, 5, 12),
    (7, 8, 15, 80, 5, 12)");

// 4. Users & Employees
$passwordHash = password_hash('password123', PASSWORD_DEFAULT);
$hashQuoted = $pdo->quote($passwordHash);

$pdo->exec("INSERT INTO users (id, username, password, role) VALUES
    (1, 'hr_admin', {$hashQuoted}, 'hr'),
    (2, 'budi_manager', {$hashQuoted}, 'manager'),
    (3, 'andi_staff', {$hashQuoted}, 'staff'),
    (4, 'citra_staff', {$hashQuoted}, 'staff'),
    (5, 'dedi_staff', {$hashQuoted}, 'staff'),
    (6, 'eka_staff', {$hashQuoted}, 'staff')");

// HR admin & manajer (tanpa atasan)
$pdo->exec("INSERT INTO employees (id, user_id, department_id, position_id, manager_id, name, email, hire_date, position_start_date) VALUES
    (1, 1, 3, 8, NULL, 'Siti Aminah', 'siti.aminah@dewala.test', DATE_SUB(CURDATE(), INTERVAL 80 MONTH), DATE_SUB(CURDATE(), INTERVAL 60 MONTH)),
    (2, 2, 1, 4, NULL, 'Budi Santoso', 'budi.santoso@dewala.test', DATE_SUB(CURDATE(), INTERVAL 90 MONTH), DATE_SUB(CURDATE(), INTERVAL 48 MONTH))");

// Anak buah Budi (Team Lead), dengan 4 skenario kelayakan yang berbeda
$pdo->exec("INSERT INTO employees (id, user_id, department_id, position_id, manager_id, name, email, hire_date, position_start_date) VALUES
    (3, 3, 1, 1, 2, 'Andi Wijaya', 'andi.wijaya@dewala.test', DATE_SUB(CURDATE(), INTERVAL 30 MONTH), DATE_SUB(CURDATE(), INTERVAL 15 MONTH)),
    (4, 4, 1, 1, 2, 'Citra Lestari', 'citra.lestari@dewala.test', DATE_SUB(CURDATE(), INTERVAL 40 MONTH), DATE_SUB(CURDATE(), INTERVAL 20 MONTH)),
    (5, 5, 1, 2, 2, 'Dedi Kurniawan', 'dedi.kurniawan@dewala.test', DATE_SUB(CURDATE(), INTERVAL 50 MONTH), DATE_SUB(CURDATE(), INTERVAL 30 MONTH)),
    (6, 6, 1, 1, 2, 'Eka Putri', 'eka.putri@dewala.test', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_SUB(CURDATE(), INTERVAL 4 MONTH))");

// 5. Penilaian kompetensi terbaru per karyawan
$pdo->exec("INSERT INTO competency_scores (employee_id, technical_score, softskill_score, period, scored_by) VALUES
    (3, 85, 84, 'Berjalan', 2),
    (4, 78, 72, 'Berjalan', 2),
    (5, 88, 86, 'Berjalan', 2),
    (6, 70, 68, 'Berjalan', 2)");

// 6. Rekam jejak — dirancang agar tiap staff mendemonstrasikan skenario berbeda:

// Andi: rekam jejak bersih, tenur & skor cukup -> ELIGIBLE untuk promosi ke Mid Developer
$pdo->exec("INSERT INTO track_records (employee_id, type, record_date, description, is_active, created_by) VALUES
    (3, 'keterlambatan', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'Terlambat karena banjir', 1, 2)");

// Citra: skor kompetensi di bawah standar (lihat competency_scores) -> INELIGIBLE
$pdo->exec("INSERT INTO track_records (employee_id, type, record_date, description, is_active, created_by) VALUES
    (4, 'keterlambatan', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'Terlambat masuk kantor', 1, 2)");

// Dedi: skor & tenur bagus, tapi punya SP2 aktif dalam 1 tahun terakhir -> INELIGIBLE
$pdo->exec("INSERT INTO track_records (employee_id, type, record_date, description, is_active, created_by) VALUES
    (5, 'sp2', DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'Pelanggaran kode etik perusahaan', 1, 2)");

// Eka: baru mulai menjabat & skor belum memenuhi -> INELIGIBLE (tenur & kompetensi)
// (tidak perlu rekam jejak tambahan untuk mendemonstrasikan skenario ini)

echo "Seeder selesai. Data contoh berhasil dimasukkan.\n\n";
echo "Login demo (password untuk semua akun: password123):\n";
echo "- HR      : hr_admin\n";
echo "- Manajer : budi_manager\n";
echo "- Staff   : andi_staff  (eligible)\n";
echo "            citra_staff (skor kompetensi kurang)\n";
echo "            dedi_staff  (SP aktif)\n";
echo "            eka_staff   (tenur & skor belum cukup)\n";
