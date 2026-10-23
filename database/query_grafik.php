<?php
// database/query_grafik.php
// Helper build data grafik laporan penjualan (net setelah retur).

if (!function_exists('buildGrafikDataLaporan')) {
    function buildGrafikDataLaporan(PDO $pdo, string $tglMulai, string $tglSelesai): array
    {
        $result = [
            'harian'       => ['labels' => [], 'omzet' => [], 'laba' => []],
            'metode_bayar' => ['labels' => [], 'values' => []],
            'top_barang'   => ['labels' => [], 'qty' => []],
            'per_kategori' => ['labels' => [], 'omzet' => []],
        ];

        try {
            // 1. TREN HARIAN — omzet & laba net
            $sqlHarian = "
                SELECT
                    DATE(p.tanggal) AS tgl,
                    COALESCE(SUM(p.total_bersih), 0) - COALESCE(SUM(r.retur_nominal), 0) AS omzet_net,
                    (
                        COALESCE(SUM(p.total_bersih), 0) - COALESCE(SUM(r.retur_nominal), 0)
                    ) - (
                        COALESCE(SUM(det.total_modal), 0) - COALESCE(SUM(r.retur_modal), 0)
                    ) AS laba_net
                FROM penjualan p
                LEFT JOIN (
                    SELECT penjualan_id, SUM(COALESCE(harga_beli,0)*COALESCE(qty,0)) AS total_modal
                    FROM penjualan_detail
                    GROUP BY penjualan_id
                ) det ON p.id = det.penjualan_id
                LEFT JOIN (
                    SELECT
                        rp.penjualan_id,
                        SUM(rd.subtotal) AS retur_nominal,
                        SUM(rd.harga_beli * rd.qty_retur) AS retur_modal
                    FROM retur_penjualan_detail rd
                    JOIN retur_penjualan rp ON rd.retur_penjualan_id = rp.id
                    GROUP BY rp.penjualan_id
                ) r ON p.id = r.penjualan_id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?
                GROUP BY DATE(p.tanggal)
                ORDER BY tgl ASC
            ";
            $stmt = $pdo->prepare($sqlHarian);
            $stmt->execute([$tglMulai, $tglSelesai]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = [];
            foreach ($rows as $row) {
                $map[$row['tgl']] = [
                    'omzet' => (float)$row['omzet_net'],
                    'laba'  => (float)$row['laba_net'],
                ];
            }
            $start = new DateTime($tglMulai);
            $end   = new DateTime($tglSelesai);
            $end->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);
            foreach ($period as $dt) {
                $key = $dt->format('Y-m-d');
                $result['harian']['labels'][] = $dt->format('d/m');
                $result['harian']['omzet'][]  = $map[$key]['omzet'] ?? 0;
                $result['harian']['laba'][]   = $map[$key]['laba']  ?? 0;
            }

            // 2. KOMPOSISI METODE BAYAR
            $sqlMetode = "
                SELECT
                    UPPER(p.metode_bayar) AS metode,
                    COALESCE(SUM(p.total_bersih), 0) - COALESCE(SUM(r.retur_nominal), 0) AS total_net
                FROM penjualan p
                LEFT JOIN (
                    SELECT rp.penjualan_id, SUM(rd.subtotal) AS retur_nominal
                    FROM retur_penjualan_detail rd
                    JOIN retur_penjualan rp ON rd.retur_penjualan_id = rp.id
                    GROUP BY rp.penjualan_id
                ) r ON p.id = r.penjualan_id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?
                GROUP BY UPPER(p.metode_bayar)
                HAVING total_net > 0
                ORDER BY total_net DESC
            ";
            $stmt = $pdo->prepare($sqlMetode);
            $stmt->execute([$tglMulai, $tglSelesai]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result['metode_bayar']['labels'][] = $row['metode'];
                $result['metode_bayar']['values'][] = (float)$row['total_net'];
            }

            // 3. TOP 5 BARANG TERLARIS
            $sqlTop = "
                SELECT
                    pd.nama_barang,
                    SUM(pd.qty - COALESCE(r.qty_retur, 0)) AS qty_net
                FROM penjualan_detail pd
                JOIN penjualan p ON pd.penjualan_id = p.id
                LEFT JOIN (
                    SELECT rpd.penjualan_detail_id, SUM(rpd.qty_retur) AS qty_retur
                    FROM retur_penjualan_detail rpd
                    JOIN retur_penjualan rp ON rpd.retur_penjualan_id = rp.id
                    GROUP BY rpd.penjualan_detail_id
                ) r ON r.penjualan_detail_id = pd.id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?
                GROUP BY pd.nama_barang
                HAVING qty_net > 0
                ORDER BY qty_net DESC
                LIMIT 5
            ";
            $stmt = $pdo->prepare($sqlTop);
            $stmt->execute([$tglMulai, $tglSelesai]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result['top_barang']['labels'][] = $row['nama_barang'];
                $result['top_barang']['qty'][]    = (int)$row['qty_net'];
            }

            // 4. OMZET PER KATEGORI
            $sqlKategori = "
                SELECT
                    k.nama_kategori,
                    COALESCE(SUM(pd.subtotal - COALESCE(r.subtotal_retur, 0)), 0) AS omzet_net
                FROM penjualan_detail pd
                JOIN penjualan p ON pd.penjualan_id = p.id
                JOIN barang_kemasan bk ON pd.barang_kemasan_id = bk.id
                JOIN barang b ON bk.barang_id = b.id
                JOIN kategori k ON b.kategori_id = k.id
                LEFT JOIN (
                    SELECT rpd.penjualan_detail_id, SUM(COALESCE(rpd.subtotal,0)) AS subtotal_retur
                    FROM retur_penjualan_detail rpd
                    JOIN retur_penjualan rp ON rpd.retur_penjualan_id = rp.id
                    GROUP BY rpd.penjualan_detail_id
                ) r ON r.penjualan_detail_id = pd.id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?
                GROUP BY k.id, k.nama_kategori
                HAVING omzet_net > 0
                ORDER BY omzet_net DESC
                LIMIT 8
            ";
            $stmt = $pdo->prepare($sqlKategori);
            $stmt->execute([$tglMulai, $tglSelesai]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result['per_kategori']['labels'][] = $row['nama_kategori'];
                $result['per_kategori']['omzet'][]  = (float)$row['omzet_net'];
            }

        } catch (PDOException $e) {
            error_log('[buildGrafikDataLaporan] ' . $e->getMessage());
        }

        return $result;
    }
}