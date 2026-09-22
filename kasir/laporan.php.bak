<?php
// kasir/laporantes.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// Filter Tanggal (Default: Hari Ini)
$tglMulai   =$_GET['tgl_mulai'] ?? date('Y-m-d');
$tglSelesai =$_GET['tgl_selesai'] ?? date('Y-m-d');

// Parameter Paginasi Tunggal
$limit = 20; // Jumlah transaksi per halaman
$page  = (isset($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) *$limit;

try {
    // 1. QUERY RINGKASAN UTAMA (TUNAI, QRIS, LABA, TOTAL TX)
    $sqlSum = "SELECT 
                    COUNT(DISTINCT p.id) AS total_transaksi,
                    COALESCE(SUM(CASE WHEN UPPER(p.metode_bayar) = 'TUNAI' THEN p.total_bersih ELSE 0 END), 0) - COALESCE(r.retur_tunai, 0) AS total_tunai,
                    COALESCE(SUM(CASE WHEN UPPER(p.metode_bayar) = 'QRIS' THEN p.total_bersih ELSE 0 END), 0) - COALESCE(r.retur_qris, 0) AS total_qris,
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
               WHERE DATE(p.tanggal) BETWEEN ? AND ?";

    $stmtSum =$pdoBarang->prepare($sqlSum);$stmtSum->execute([$tglMulai,$tglSelesai, $tglMulai,$tglSelesai]);
    $summary =$stmtSum->fetch(PDO::FETCH_ASSOC);

    // 2. HITUNG TOTAL TRANSAKSI UNTUK PAGINASI
    $stmtCount =$pdoBarang->prepare("SELECT COUNT(id) FROM penjualan WHERE DATE(tanggal) BETWEEN ? AND ?");
    $stmtCount->execute([$tglMulai, $tglSelesai]);$totalRows  = (int)$stmtCount->fetchColumn();$totalPages = max(1, ceil($totalRows / $limit));

    // 3. QUERY RIWAYAT PENJUALAN (SINGLE LIST)
    $sqlList = "SELECT 
                    p.*,
                    (SELECT COUNT(id) FROM penjualan_detail WHERE penjualan_id = p.id) AS item_count,
                    (
                        (p.total_bersih - COALESCE(r_tx.retur_nominal, 0)) - 
                        (
                            COALESCE((
                                SELECT SUM(COALESCE(harga_beli, 0) * COALESCE(qty, 0)) 
                                FROM penjualan_detail 
                                WHERE penjualan_id = p.id
                            ), 0) - COALESCE(r_tx.retur_modal, 0)
                        )
                    ) AS untung_per_transaksi,
                    COALESCE(r_tx.retur_nominal, 0) AS total_retur_transaksi
                FROM penjualan p
                LEFT JOIN (
                    SELECT 
                        rp.penjualan_id,
                        SUM(rd.subtotal) AS retur_nominal,
                        SUM(rd.harga_beli * rd.qty_retur) AS retur_modal
                    FROM retur_penjualan_detail rd
                    JOIN retur_penjualan rp ON rd.retur_penjualan_id = rp.id
                    GROUP BY rp.penjualan_id
                ) r_tx ON p.id = r_tx.penjualan_id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?
                ORDER BY p.id DESC
                LIMIT ? OFFSET ?";
    
    $stmtList =$pdoBarang->prepare($sqlList);$stmtList->bindValue(1, $tglMulai);$stmtList->bindValue(2, $tglSelesai);$stmtList->bindValue(3, $limit, PDO::PARAM_INT);$stmtList->bindValue(4, $offset, PDO::PARAM_INT);$stmtList->execute();
    $transaksiList =$stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid my-4 px-4">
  
  <!-- HEADER & FILTER TANGGAL -->
  <div class="row align-items-center mb-4">
    <div class="col-md-6">
      <h4 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i>Laporan Penjualan</h4>
      <p class="text-muted small mb-0">Ringkasan transaksi tunai, QRIS, serta estimasi keuntungan.</p>
    </div>
    <div class="col-md-6">
      <form method="GET" class="row g-2 justify-content-md-end">
        <div class="col-auto">
          <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglMulai); ?>">
        </div>
        <div class="col-auto align-self-center">s/d</div>
        <div class="col-auto">
          <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglSelesai); ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-filter me-1"></i> Filter</button>
          <a href="index.php" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-arrow-left me-1"></i> Ke Kasir</a>
        </div>
      </form>
    </div>
  </div>

  <!-- 4 CARD RINGKASAN UTAMA -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-success text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75"><i class="bi bi-cash me-1"></i>Total Tunai</small>
          <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($summary['total_tunai'], 0, ',', '.'); ?></h3>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-info text-dark">
        <div class="card-body p-3">
          <small class="text-uppercase fw-bold opacity-75"><i class="bi bi-qr-code-scan me-1"></i>Total QRIS</small>
          <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($summary['total_qris'], 0, ',', '.'); ?></h3>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-primary text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75"><i class="bi bi-graph-up-arrow me-1"></i>Total Laba (Bersih)</small>
          <h3 class="fw-bold mb-0 font-monospace text-warning">
            Rp <?= number_format($summary['total_laba'], 0, ',', '.'); ?>
          </h3>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-dark text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75"><i class="bi bi-receipt me-1"></i>Total Transaksi</small>
          <h3 class="fw-bold mb-0 font-monospace"><?= number_format($summary['total_transaksi']); ?> <span class="fs-6 fw-normal">Struk</span></h3>
        </div>
      </div>
    </div>
  </div>

  <!-- TABEL RIWAYAT TRANSAKSI -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Transaksi Penjualan</h6>
      <span class="badge bg-light text-dark border">Total: <?= number_format($totalRows); ?> Transaksi</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Waktu & Faktur</th>
            <th class="text-center">Jumlah Item</th>
            <th class="text-end">Total Belanja</th>
            <th class="text-end text-success fw-bold">Est. Laba</th>
            <th class="text-end">Uang Bayar</th>
            <th class="text-end">Kembalian</th>
            <th class="text-center">Metode</th>
            <th class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transaksiList)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-receipt display-5 d-block mb-2 text-muted"></i>
                Tidak ada transaksi penjualan pada rentang tanggal ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($transaksiList as$row): ?>
              <?php $isQris = strtoupper($row['metode_bayar']) === 'QRIS'; ?>
              <tr>
                <td>
                  <strong class="text-dark d-block"><?= htmlspecialchars($row['no_faktur']); ?></strong>
                  <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= date('d/m/Y H:i', strtotime($row['tanggal'])); ?></small>
                  <?php if ($row['total_retur_transaksi'] > 0): ?>
                    <span class="badge bg-danger text-white d-block mt-1" style="width: fit-content;">Ada Retur</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border"><?= $row['item_count']; ?> Jenis Item</span>
                </td>
                <td class="text-end font-monospace fw-bold text-dark">
                  Rp <?= number_format($row['total_bersih'], 0, ',', '.'); ?>
                </td>
                <td class="text-end font-monospace fw-bold <?= $row['untung_per_transaksi'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                  <?= $row['untung_per_transaksi'] >= 0 ? '+' : ''; ?>Rp <?= number_format($row['untung_per_transaksi'], 0, ',', '.'); ?>
                </td>
                <td class="text-end font-monospace">
                  Rp <?= number_format($row['bayar'], 0, ',', '.'); ?>
                </td>
                <td class="text-end font-monospace text-muted">
                  Rp <?= number_format($row['kembalian'], 0, ',', '.'); ?>
                </td>
                <td class="text-center">
                  <!-- PENANDA METODE PEMBAYARAN VISUAL -->
                  <span class="badge <?= $isQris ? 'bg-info text-dark' : 'bg-secondary'; ?> fw-bold">
                    <i class="bi <?= $isQris ? 'bi-qr-code-scan' : 'bi-cash-stack'; ?> me-1"></i>
                    <?= htmlspecialchars(strtoupper($row['metode_bayar'])); ?>
                  </span>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="lihatDetailStruk(<?= $row['id']; ?>)">
                    <i class="bi bi-eye-fill me-1"></i> Detail
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINASI TUNGGAL -->
    <?php if ($totalPages > 1): ?>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
        <small class="text-muted">
          Menampilkan Halaman <strong><?= $page; ?></strong> dari <strong><?=$totalPages; ?></strong>
        </small>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
              <a class="page-link" href="?tgl_mulai=<?= $tglMulai; ?>&tgl_selesai=<?= $tglSelesai; ?>&page=<?=$page - 1; ?>">&laquo; Prev</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i ===$page ? 'active' : ''; ?>">
                <a class="page-link" href="?tgl_mulai=<?= $tglMulai; ?>&tgl_selesai=<?=$tglSelesai; ?>&page=<?= $i; ?>"><?= $i; ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >=$totalPages ? 'disabled' : ''; ?>">
              <a class="page-link" href="?tgl_mulai=<?= $tglMulai; ?>&tgl_selesai=<?= $tglSelesai; ?>&page=<?=$page + 1; ?>">Next &raquo;</a>
            </li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>

  </div>

</div>

<!-- MODAL DETAIL STRUK TRANSAKSI -->
<div class="modal fade" id="modalDetailStruk" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white py-2">
        <h6 class="modal-title fw-bold" id="detail_faktur_title">Detail Transaksi</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="detail_struk_body">
        <div class="text-center py-4"><span class="spinner-border text-primary"></span> Loading detail...</div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
let currentPenjualanId = 0;

function lihatDetailStruk(id) {
  currentPenjualanId = id;
  const modal = new bootstrap.Modal(document.getElementById('modalDetailStruk'));
  const body = document.getElementById('detail_struk_body');
  
  body.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span> Loading detail...</div>';
  modal.show();

  fetch('api_detail_transaksi.php?id=' + id)
    .then(res => res.json())
    .then(res => {
      if (res.status === 'success') {
        document.getElementById('detail_faktur_title').innerText = 'Faktur: ' + res.header.no_faktur;
        
        let itemsHtml = '';
        let totalModalStruk = 0;
        let totalNilaiRetur = 0;
        let totalModalRetur = 0;

        res.details.forEach(d => {
          let modalItem = (parseFloat(d.harga_beli) || 0) * parseInt(d.qty);
          totalModalStruk += modalItem;

          let qtyRetur = parseInt(d.qty_retur) || 0;
          let sisaQty = parseInt(d.qty) - qtyRetur;
          
          if (qtyRetur > 0) {
            totalNilaiRetur += (qtyRetur * parseFloat(d.harga_jual));
            totalModalRetur += (qtyRetur * (parseFloat(d.harga_beli) || 0));
          }

          itemsHtml += `
            <tr>
              <td>
                <div class="fw-bold text-dark">${d.nama_barang}</div>
                <small class="text-muted">${d.nama_kemasan} @ Rp ${Math.round(d.harga_jual).toLocaleString('id-ID')} (Modal: Rp ${Math.round(d.harga_beli).toLocaleString('id-ID')})</small>
                ${qtyRetur > 0 ? `<div class="badge bg-danger text-white mt-1">Diretur: ${qtyRetur}${d.satuan || ''}</div>` : ''}
              </td>
              <td class="text-center fw-bold">${d.qty} ${d.satuan || ''}</td>
              <td class="text-end font-monospace fw-bold">Rp ${Math.round(d.subtotal).toLocaleString('id-ID')}</td>
              <td class="text-center">
                ${sisaQty > 0 ? `
                  <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size: 0.75rem;" onclick="prosesReturItem(${d.penjualan_id},${d.id}, ${sisaQty}, '${d.nama_barang.replace(/'/g, "\\'")}')">
                    <i class="bi bi-arrow-return-left me-1"></i>Retur
                  </button>
                ` : '<span class="badge bg-secondary">Habis Diretur</span>'}
              </td>
            </tr>`;
        });

        let omzetNet = parseFloat(res.header.total_bersih) - totalNilaiRetur;
        let modalNet = totalModalStruk - totalModalRetur;
        let totalUntungStruk = omzetNet - modalNet;

        body.innerHTML = `
          <div class="text-center mb-3 border-bottom pb-2">
            <h5 class="fw-bold mb-0">KIOS MAFAZA</h5>
            <small class="text-muted d-block">${res.header.tanggal}</small>
            <span class="badge bg-info text-dark mt-1">Metode Bayar: ${res.header.metode_bayar}</span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
              <thead>
                <tr class="table-light">
                  <th>Item</th>
                  <th class="text-center">Qty</th>
                  <th class="text-end">Subtotal</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>${itemsHtml}</tbody>
            </table>
          </div>
          <div class="p-2 bg-light rounded border font-monospace fs-6">
            <div class="d-flex justify-content-between mb-1">
              <span>Total Omzet Struk:</span>
              <strong class="text-dark">Rp ${Math.round(res.header.total_bersih).toLocaleString('id-ID')}</strong>
            </div>
            ${totalNilaiRetur > 0 ? `
              <div class="d-flex justify-content-between mb-1 text-danger">
                <span>Potongan Retur:</span>
                <strong>- Rp ${Math.round(totalNilaiRetur).toLocaleString('id-ID')}</strong>
              </div>
            ` : ''}
            <div class="d-flex justify-content-between mb-1 ${totalUntungStruk >= 0 ? 'text-success' : 'text-danger'} fw-bold">
              <span>Keuntungan Laba Struk:</span>
              <span>${totalUntungStruk >= 0 ? '+' : ''}Rp ${Math.round(totalUntungStruk).toLocaleString('id-ID')}</span>
            </div>
            <hr class="my-1">
            <div class="d-flex justify-content-between mb-1">
              <span>Bayar:</span>
              <span>Rp ${Math.round(res.header.bayar).toLocaleString('id-ID')}</span>
            </div>
            <div class="d-flex justify-content-between text-primary">
              <span>Kembalian:</span>
              <span>Rp ${Math.round(res.header.kembalian).toLocaleString('id-ID')}</span>
            </div>
          </div>`;
      } else {
        body.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
      }
    });
}

function prosesReturItem(penjualanId, penjualanDetailId, maxQty, namaBarang) {
  const qtyInput = prompt(`Retur untuk item "${namaBarang}"\nMasukkan Qty Retur (Maksimal ${maxQty}):`, "1");
  if (qtyInput === null) return;

  const qtyRetur = parseInt(qtyInput);
  if (isNaN(qtyRetur) || qtyRetur <= 0 || qtyRetur > maxQty) {
    alert(`Qty retur tidak valid! Harus angka antara 1 dan ${maxQty}.`);
    return;
  }

  const alasan = prompt("Masukkan alasan retur:", "Barang Cacat/Rusak");
  if (alasan === null) return;

  fetch('api_retur_item.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      penjualan_id: penjualanId,
      penjualan_detail_id: penjualanDetailId,
      qty_retur: qtyRetur,
      alasan: alasan
    })
  })
  .then(res => res.json())
  .then(res => {
    alert(res.message);
    if (res.status === 'success') {
      lihatDetailStruk(penjualanId);
    }
  })
  .catch(err => {
    alert('Terjadi kesalahan jaringan/server!');
  });
}
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>