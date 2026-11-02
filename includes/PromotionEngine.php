<?php

/**
 * PromotionEngine
 *
 * Mesin validasi kelayakan promosi berbasis merit. Mengevaluasi 4 matriks
 * utama untuk seorang karyawan terhadap career path yang terkonfigurasi dari
 * posisinya saat ini:
 *   1. Career Path   — harus ada jalur karir terdaftar dari posisi saat ini.
 *   2. Tenur         — masa kerja di posisi saat ini >= minimum jalur.
 *   3. Kompetensi    — rata-rata skor teknikal & soft-skill terbaru >= minimum jalur.
 *   4. Rekam Jejak   — keterlambatan 6 bulan terakhir <= kuota, dan tidak ada
 *                      SP (SP1/SP2/SP3) aktif dalam periode lockout.
 *
 * Hasil dari checkEligibility() dipakai baik oleh api/promotion_check.php
 * (dikonsumsi Alpine.js di Dasbor Merit) maupun divalidasi ulang di server
 * oleh modules/promotion/request.php sebelum pengajuan promosi disimpan.
 */
class PromotionEngine
{
    public static function checkEligibility(PDO $pdo, int $employeeId): array
    {
        $stmt = $pdo->prepare(
            'SELECT e.*, p.id AS current_position_id, p.name AS current_position_name
             FROM employees e
             JOIN positions p ON p.id = e.position_id
             WHERE e.id = ?'
        );
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            return [
                'eligible'        => false,
                'employee'        => null,
                'target_position' => null,
                'criteria'        => [],
                'message'         => 'Data karyawan tidak ditemukan.',
            ];
        }

        // 1. Cari career path yang terdaftar dari posisi saat ini
        $stmt = $pdo->prepare(
            'SELECT cp.*, tp.name AS to_position_name
             FROM career_paths cp
             JOIN positions tp ON tp.id = cp.to_position_id
             WHERE cp.from_position_id = ?
             LIMIT 1'
        );
        $stmt->execute([$employee['current_position_id']]);
        $path = $stmt->fetch();

        if (!$path) {
            return [
                'eligible'        => false,
                'employee'        => $employee,
                'target_position' => null,
                'criteria'        => [
                    [
                        'key'    => 'career_path',
                        'label'  => 'Jalur Karir',
                        'pass'   => false,
                        'detail' => 'Tidak ada jalur karir yang dikonfigurasi dari posisi "' . $employee['current_position_name'] . '".',
                    ],
                ],
                'message' => 'Tidak ada jalur karir dari posisi saat ini.',
            ];
        }

        $criteria = [];

        // 1. Career path ditemukan
        $criteria[] = [
            'key'    => 'career_path',
            'label'  => 'Jalur Karir',
            'pass'   => true,
            'detail' => $employee['current_position_name'] . ' -> ' . $path['to_position_name'],
        ];

        // 2. Tenur (masa kerja di posisi saat ini)
        $tenureMonths = monthsBetween($employee['position_start_date']);
        $tenurePass = $tenureMonths >= (int) $path['min_tenure_months'];
        $criteria[] = [
            'key'      => 'tenure',
            'label'    => 'Masa Kerja di Posisi Saat Ini',
            'pass'     => $tenurePass,
            'current'  => $tenureMonths . ' bulan',
            'required' => '>= ' . $path['min_tenure_months'] . ' bulan',
            'detail'   => $tenurePass
                ? "Memenuhi (sudah {$tenureMonths} bulan)."
                : "Gagal: baru {$tenureMonths} bulan, butuh minimal {$path['min_tenure_months']} bulan.",
        ];

        // 3. Kompetensi (rata-rata skor teknikal & soft-skill terbaru)
        $stmt = $pdo->prepare(
            'SELECT technical_score, softskill_score FROM competency_scores
             WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$employeeId]);
        $score = $stmt->fetch();
        $avgScore = $score ? round(((float) $score['technical_score'] + (float) $score['softskill_score']) / 2, 1) : 0;
        $scorePass = $avgScore >= (float) $path['min_score'];
        $criteria[] = [
            'key'      => 'competency',
            'label'    => 'Skor Kompetensi',
            'pass'     => $scorePass,
            'current'  => $avgScore,
            'required' => '>= ' . $path['min_score'],
            'detail'   => $scorePass
                ? "Memenuhi (skor saat ini {$avgScore})."
                : "Gagal: Skor Kompetensi saat ini {$avgScore}, Butuh {$path['min_score']}.",
        ];

        // 4a. Keterlambatan dalam 6 bulan terakhir
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS jumlah FROM track_records
             WHERE employee_id = ? AND type = 'keterlambatan'
             AND record_date >= (CURDATE() - INTERVAL 6 MONTH)"
        );
        $stmt->execute([$employeeId]);
        $latenessCount = (int) $stmt->fetch()['jumlah'];
        $latenessPass = $latenessCount <= (int) $path['max_lateness_6m'];
        $criteria[] = [
            'key'      => 'lateness',
            'label'    => 'Keterlambatan (6 Bulan Terakhir)',
            'pass'     => $latenessPass,
            'current'  => $latenessCount . ' kali',
            'required' => '<= ' . $path['max_lateness_6m'] . ' kali',
            'detail'   => $latenessPass
                ? "Memenuhi ({$latenessCount} kali dalam 6 bulan terakhir)."
                : "Gagal: {$latenessCount} kali keterlambatan, maksimal {$path['max_lateness_6m']} kali.",
        ];

        // 4b. Kasus indisipliner (SP) aktif dalam periode lockout
        $lockoutMonths = (int) ($path['sp_lockout_months'] ?: 12);
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS jumlah FROM track_records
             WHERE employee_id = ? AND type IN ('sp1','sp2','sp3') AND is_active = 1
             AND record_date >= (CURDATE() - INTERVAL {$lockoutMonths} MONTH)"
        );
        $stmt->execute([$employeeId]);
        $spCount = (int) $stmt->fetch()['jumlah'];
        $spPass = $spCount === 0;
        $criteria[] = [
            'key'      => 'disciplinary',
            'label'    => 'Kasus Indisipliner (SP)',
            'pass'     => $spPass,
            'current'  => $spCount . ' SP aktif',
            'required' => '0 SP aktif dalam ' . $lockoutMonths . ' bulan terakhir',
            'detail'   => $spPass
                ? 'Memenuhi (tidak ada SP aktif).'
                : "Gagal: terdapat {$spCount} Surat Peringatan aktif dalam {$lockoutMonths} bulan terakhir.",
        ];

        $eligible = true;
        foreach ($criteria as $c) {
            if (!$c['pass']) {
                $eligible = false;
                break;
            }
        }

        return [
            'eligible'        => $eligible,
            'employee'        => $employee,
            'target_position' => ['id' => $path['to_position_id'], 'name' => $path['to_position_name']],
            'criteria'        => $criteria,
            'message'         => $eligible
                ? 'Karyawan memenuhi seluruh syarat promosi.'
                : 'Karyawan belum memenuhi seluruh syarat promosi.',
        ];
    }
}
