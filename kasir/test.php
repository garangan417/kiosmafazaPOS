<?php
// kasir/laporantes.php

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// Filter Tanggal
$tglMulai   = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

try {
    // 1. QUERY RINGKASAN UTAMA (Sudah termasuk penyesuaian saldo manual QRIS)
    $sqlSum = "SELECT 
                    COUNT(DISTINCT p.id) AS total_transaksi,
                    COALESCE(SUM(CASE WHEN UPPER(p.metode_bayar) = 'TUNAI' THEN p.total_bersih ELSE 0 END), 0) - COALESCE(r.retur_tunai, 0) AS total_tunai,
                    
                    /* Total QRIS = Transaksi QRIS Sistem - Retur QRIS + Penyesuaian Manual */
                    (COALESCE(SUM(CASE WHEN UPPER(p.metode_bayar) = 'QRIS' THEN p.total_bersih ELSE 0 END), 0) 
                     - COALESCE(r.retur_qris, 0) 
                     + COALESCE(q_adj.total_penyesuaian, 0)) AS total_qris,
                     
                    COALESCE(q_adj.total_penyesuaian, 0) AS penyesuaian_qris,

                    (COALESCE(SUM(p.total_bersih), 0) - COALESCE(r.total_retur_nominal, 0)) - 
                    (COALESCE(SUM(det.total_modal_transaksi), 0) - COALESCE(r.total_retur_modal, 0)) AS total_laba
               FROM penjualan p
               LEFT JOIN (
                   SELECT 
                       penjualan_id,
                       SUM(COALESCE(harga_beli, 0) * COALESCE(qty, 0)) AS total_modal_transaksi
                   FROM penjualan_detail
                   GROUP BY penjualan_id
               ) det ON p.id = det.penjualan_id
               LEFT JOIN (
                   SELECT 
                       SUM(rd.subtotal) AS total_retur_nominal,
                       SUM(rd.harga_beli * rd.qty_retur) AS total_retur_modal,
                       SUM(CASE WHEN UPPER(p2.metode_bayar) = 'TUNAI' THEN rd.subtotal ELSE 0 END) AS retur_tunai,
                       SUM(CASE WHEN UPPER(p2.metode_bayar) = 'QRIS' THEN rd.subtotal ELSE 0 END) AS retur_qris
                   FROM retur_penjualan_detail rd
                   JOIN retur_penjualan rp ON rd.retur_penjualan_id = rp.id
                   JOIN penjualan p2 ON rp.penjualan_id = p2.id
                   WHERE DATE(p2.tanggal) BETWEEN ? AND ?
               ) r ON 1=1
               LEFT JOIN (
                   SELECT SUM(nominal) AS total_penyesuaian 
                   FROM qris_penyesuaian 
                   WHERE tanggal BETWEEN ? AND ?
               ) q_adj ON 1=1
               WHERE DATE(p.tanggal) BETWEEN ? AND ?";

    $stmtSum = $pdoBarang->prepare($sqlSum);
    $stmtSum->execute([$tglMulai, $tglSelesai, $tglMulai, $tglSelesai, $tglMulai, $tglSelesai]);
    $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

    // 2. QUERY DAFTAR TRANSAKSI
    $sqlList = "SELECT 
                    p.id,
                    p.no_faktur,
                    p.tanggal,
                    p.metode_bayar,
                    p.total_bersih,
                    COALESCE(r.total_retur, 0) AS total_retur
                FROM penjualan p
                LEFT JOIN (
                    SELECT 
                        penjualan_id, 
                        SUM(subtotal) AS total_retur 
                    FROM retur_penjualan_detail rd
                    JOIN retur_penjualan rp ON rd.retur_penjualan_id = rp.id
                    GROUP BY penjualan_id
                ) r ON p.id = r.penjualan_id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?
                ORDER BY p.tanggal DESC";

    $stmtList = $pdoBarang->prepare($sqlList);
    $stmtList->execute([$tglMulai, $tglSelesai]);
    $daftarPenjualan = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error mengambil data laporan: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Penjualan</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container-fluid py-4 px-4">
  
  <!-- FILTER TANGGAL -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-center">
        <div class="col-auto">
          <label class="col-form-label fw-bold">Dari:</label>
        </div>
        <div class="col-auto">
          <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglMulai); ?>">
        </div>
        <div class="col-auto">
          <label class="col-form-label fw-bold">Sampai:</label>
        </div>
        <div class="col-auto">
          <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglSelesai); ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">
            <i class="bi bi-filter me-1"></i> Filter
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- RINGKASAN CARD -->
  <div class="row g-3 mb-4">
    <!-- TOTAL TRANSAKSI -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-primary text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-bold opacity-75">Total Transaksi</small>
          <h3 class="fw-bold mb-0 font-monospace mt-1"><?= number_format($summary['total_transaksi'] ?? 0, 0, ',', '.'); ?></h3>
        </div>
      </div>
    </div>

    <!-- TOTAL TUNAI -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-success text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-bold opacity-75">Total Tunai</small>
          <h3 class="fw-bold mb-0 font-monospace mt-1">
            Rp <?= number_format($summary['total_tunai'] ?? 0, 0, ',', '.'); ?>
          </h3>
        </div>
      </div>
    </div>

    <!-- TOTAL QRIS DENGAN TOMBOL ADJUSTMENT -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-info text-dark">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-uppercase fw-bold opacity-75">
              <i class="bi bi-qr-code-scan me-1"></i>Total QRIS
            </small>
            <button type="button" class="btn btn-xs btn-dark py-0 px-2 fw-bold" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalInputQris" title="Tambah / Sesuaikan Saldo QRIS Manual">
              <i class="bi bi-plus-lg me-1"></i>Adjust
            </button>
          </div>
          <h3 class="fw-bold mb-0 font-monospace mt-1">
            Rp <?= number_format($summary['total_qris'] ?? 0, 0, ',', '.'); ?>
          </h3>
          <?php if (($summary['penyesuaian_qris'] ?? 0) > 0): ?>
            <small class="d-block text-dark opacity-75 mt-1" style="font-size: 0.75rem;">
              *Termasuk penyesuaian: Rp <?= number_format($summary['penyesuaian_qris'], 0, ',', '.'); ?>
            </small>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ESTIMASI LABA BERSIH -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-warning text-dark">
        <div class="card-body p-3">
          <small class="text-uppercase fw-bold opacity-75">Estimasi Laba</small>
          <h3 class="fw-bold mb-0 font-monospace mt-1">
            Rp <?= number_format($summary['total_laba'] ?? 0, 0, ',', '.'); ?>
          </h3>
        </div>
      </div>
    </div>
  </div>

  <!-- TABEL DAFTAR TRANSAKSI -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th class="text-center" style="width: 50px;">#</th>
              <th>No. Faktur</th>
              <th>Tanggal</th>
              <th>Metode Bayar</th>
              <th class="text-end">Total Bersih</th>
              <th class="text-end">Retur</th>
              <th class="text-end">Nett</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($daftarPenjualan)): ?>
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">Tidak ada data transaksi untuk periode ini.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($daftarPenjualan as $index => $row): 
                $nett = $row['total_bersih'] - $row['total_retur'];
              ?>
                <tr>
                  <td class="text-center"><?= $index + 1; ?></td>
                  <td class="fw-bold"><?= htmlspecialchars($row['no_faktur']); ?></td>
                  <td><?= date('d-m-Y H:i', strtotime($row['tanggal'])); ?></td>
                  <td>
                    <span class="badge <?= strtoupper($row['metode_bayar']) === 'QRIS' ? 'bg-info text-dark' : 'bg-success'; ?>">
                      <?= htmlspecialchars($row['metode_bayar']); ?>
                    </span>
                  </td>
                  <td class="text-end font-monospace">Rp <?= number_format($row['total_bersih'], 0, ',', '.'); ?></td>
                  <td class="text-end font-monospace text-danger">
                    <?= $row['total_retur'] > 0 ? '- Rp ' . number_format($row['total_retur'], 0, ',', '.') : '-'; ?>
                  </td>
                  <td class="text-end font-monospace fw-bold">Rp <?= number_format($nett, 0, ',', '.'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- MODAL INPUT / PENYESUAIAN SALDO QRIS -->
<div class="modal fade" id="modalInputQris" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-info text-dark py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-1"></i>Tambah Saldo QRIS</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <form id="formKoreksiQris" onsubmit="simpanKoreksiQris(event)">
          <div class="mb-2">
            <label class="form-label small fw-bold">Tanggal</label>
            <input type="date" id="adj_tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tglMulai); ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-bold">Nominal Masuk (Rp)</label>
            <input type="number" id="adj_nominal" class="form-control form-control-sm font-monospace fw-bold" placeholder="Contoh: 50000" required step="any" min="1">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Keterangan / Catatan</label>
            <input type="text" id="adj_keterangan" class="form-control form-control-sm" placeholder="Contoh: Transaksi manual / Fisik QRIS">
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold" id="btnSimpanAdj">
            <i class="bi bi-save me-1"></i> Tambahkan ke Card QRIS
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function simpanKoreksiQris(e) {
  e.preventDefault();
  
  const tanggal = document.getElementById('adj_tanggal').value;
  const nominal = parseFloat(document.getElementById('adj_nominal').value);
  const keterangan = document.getElementById('adj_keterangan').value;
  const btn = document.getElementById('btnSimpanAdj');

  if (isNaN(nominal) || nominal <= 0) {
    alert("Nominal penyesuaian tidak valid!");
    return;
  }

  btn.disabled = true;
  btn.innerHTML = 'Menyimpan...';

  fetch('api_koreksi_qris.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      tanggal: tanggal,
      nominal: nominal,
      keterangan: keterangan
    })
  })
  .then(res => res.json())
  .then(res => {
    alert(res.message);
    if (res.status === 'success') {
      window.location.reload();
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-save me-1"></i> Tambahkan ke Card QRIS';
    }
  })
  .catch(err => {
    alert('Terjadi kesalahan jaringan/server!');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-save me-1"></i> Tambahkan ke Card QRIS';
  });
}
</script>
</body>
</html>