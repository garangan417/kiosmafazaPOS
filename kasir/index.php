<?php
// kasir/index.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_pos.php';

// Ambil barang favorit untuk Quick Buttons
$barangFavorit = getBarangFavorit($pdoBarang);

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid my-3 px-4">
  <div class="row g-3">
    
    <!-- KOLOM KIRI: INPUT & KERANJANG -->
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label fw-bold mb-0">
              <i class="bi bi-upc-scan text-primary me-1"></i> Cari / Scan Barcode Barang
            </label>
            <!-- TOMBOL UNTUK MEMBUKA MODAL TRANSAKSI JASA / PPOB -->
            <button type="button" class="btn btn-sm btn-outline-success fw-bold" onclick="openModalJasa()">
              <i class="bi bi-plus-circle me-1"></i> Transaksi Jasa / PPOB
            </button>
          </div>
          <div class="input-group input-group-lg mt-2">
            <input type="text" id="inputScan" class="form-control font-monospace" placeholder="Scan Barcode atau ketik nama barang..." autofocus autocomplete="off">
            <!-- TOMBOL KAMERA KHUSUS MOBILE / TABLET -->
            <button class="btn btn-primary px-3" type="button" id="btnStartCamera" onclick="openCameraScanner()" title="Buka Kamera HP">
              <i class="bi bi-camera-fill fs-5"></i>
            </button>
            <button class="btn btn-outline-secondary" type="button" onclick="clearScan()"><i class="bi bi-x-lg"></i></button>
          </div>
          <!-- Dropdown Autocomplete Hasil Pencarian Nama -->
          <div id="searchResult" class="list-group position-absolute shadow w-100 mt-1" style="z-index: 1050; display:none;"></div>
        </div>
      </div>

      <!-- BARANG FAVORIT / QUICK BUTTONS -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
          <small class="fw-bold text-muted"><i class="bi bi-star-fill text-warning me-1"></i> BARANG CEPAT / FAVORIT</small>
          <!-- TOMBOL UNTUK MEMBUKA PENGELOLA FAVORIT -->
         <!--    <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold px-2 py-1" onclick="openKelolaFavorit()" style="font-size: 0.78rem;">
            <i class="bi bi-gear-fill me-1"></i> Kelola Favorit
          </button>  -->
        </div>
        <div class="card-body p-2">
          <?php if (!empty($barangFavorit)): ?>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($barangFavorit as $fav): ?>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick='addToCart(<?= json_encode($fav); ?>)'>
                  + <?= htmlspecialchars($fav['nama_barang']); ?> (<?= htmlspecialchars($fav['nama_kemasan']); ?>)
                </button>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <small class="text-muted italic d-block text-center py-1">Belum ada barang favorit. Klik <strong>Kelola Favorit</strong> untuk menambahkan.</small>
          <?php endif; ?>
        </div>
      </div>

      <!-- TABEL KERANJANG BELANJA -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h6 class="fw-bold mb-0"><i class="bi bi-cart3 text-primary me-2"></i>Keranjang Belanja</h6>
          <button class="btn btn-sm btn-outline-danger" onclick="clearCart()"><i class="bi bi-trash me-1"></i> Kosongkan</button>
        </div>
        <div class="table-responsive" style="min-height: 300px; max-height: 450px; overflow-y: auto;">
          <table class="table table-hover align-middle mb-0" id="cartTable">
            <thead class="table-light sticky-top">
              <tr>
                <th>Nama Barang / Layanan</th>
                <th style="width: 130px;">Harga Jual</th>
                <th style="width: 110px;" class="text-center">Qty</th>
                <th class="text-end">Subtotal</th>
                <th style="width: 50px;" class="text-center">#</th>
              </tr>
            </thead>
            <tbody id="cartBody">
              <!-- Item keranjang dimasukkan via JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- KOLOM KANAN: RINGKASAN & PEMBAYARAN -->
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm bg-dark text-white p-3 mb-3 text-end">
        <small class="text-uppercase fw-semibold opacity-75">Total Pembayaran</small>
        <h1 class="display-5 fw-bold font-monospace text-warning mb-0" id="displayTotal">Rp 0</h1>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="mb-3">
            <label class="form-label fw-bold small">Metode Pembayaran</label>
            <select id="metodeBayar" class="form-select" onchange="toggleFormUtang()">
              <option value="TUNAI" selected>TUNAI</option>
              <option value="QRIS">QRIS</option>
              <option value="UTANG">UTANG / BON</option>
            </select>
          </div>

          <!-- INPUT KHUSUS PELANGGAN (Hanya tampil jika Metode = UTANG) -->
          <div class="mb-3 p-2 border border-warning rounded bg-warning-subtle" id="boxPelangganUtang" style="display: none;">
            <label class="form-label fw-bold small text-dark"><i class="bi bi-person-fill me-1"></i> Pilih Pelanggan (Utang/Bon)</label>
            <select id="selectPelanggan" class="form-select form-select-sm">
              <option value="">-- Pilih Pelanggan --</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small">Uang Diterima (Rp)</label>
            <!-- Diubah menjadi type="text" dan dipasang pemisah ribuan live -->
            <input type="text" id="inputBayar" class="form-control form-control-lg font-monospace fw-bold" placeholder="0" oninput="formatInputRupiahJS(this); hitungKembalian()">
            <div class="d-flex gap-2 mt-2">
              <button class="btn btn-sm btn-outline-secondary flex-fill" onclick="setNominalBayar('PAS')">Uang Pas</button>
              <button class="btn btn-sm btn-outline-secondary flex-fill" onclick="setNominalBayar(50000)">50rb</button>
              <button class="btn btn-sm btn-outline-secondary flex-fill" onclick="setNominalBayar(100000)">100rb</button>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded font-monospace">
            <span class="fw-bold">Kembalian:</span>
            <span class="fw-bold fs-5 text-success" id="displayKembalian">Rp 0</span>
          </div>

           <!--   <button id="btnCheckout" class="btn btn-success btn-lg w-100 fw-bold" onclick="prosesCheckout()" disabled>
            <i class="bi bi-printer me-2"></i> SIMPAN & PROSES
          </button> -->
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ========================================== -->
<!-- MODAL TRANSAKSI JASA / PPOB               -->
<!-- ========================================== -->
<div class="modal fade" id="modalTransaksiJasa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Input Transaksi Jasa / PPOB</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetFocusScan()"></button>
      </div>
      <div class="modal-body p-3">
        <form id="formJasa" onsubmit="event.preventDefault(); tambahJasaKeKeranjang();">
          <div class="mb-3">
            <label class="form-label small fw-bold">Kategori Layanan</label>
            <select id="jasaKategori" class="form-select" required>
              <option value="Transfer Uang">Transfer Uang</option>
              <option value="Topup E-Wallet">Topup E-Wallet</option>
              <option value="Pulsa & Data">Pulsa & Data</option>
              <option value="Token PLN">Token PLN</option>
              <option value="Lain-lain">Lain-lain</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Keterangan / Nama Layanan</label>
            <input type="text" id="jasaKeterangan" class="form-control" placeholder="Contoh: Transfer BRI a/n Budi / Topup Dana" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Nominal / Total Biaya (Rp)</label>
            <!-- Diubah menjadi type="text" dan dipasang pemisah ribuan live -->
            <input type="text" id="jasaNominal" class="form-control font-monospace fw-bold" placeholder="0" oninput="formatInputRupiahJS(this)" required>
          </div>
          <button type="submit" class="btn btn-success w-100 fw-bold">
            <i class="bi bi-cart-plus me-1"></i> Tambahkan ke Keranjang
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL KAMERA BARCODE SCANNER (MOBILE)      -->
<!-- ========================================== -->
<div class="modal fade" id="modalCameraScanner" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-camera-fill me-2"></i>Scan Barcode Kamera HP</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopCameraScanner()"></button>
      </div>
      <div class="modal-body p-3 text-center">
        <div id="reader" style="width: 100%; min-height: 250px; background: #f8f9fa; rounded: 8px;"></div>
        <small class="text-muted d-block mt-2">Arahkan kamera ke barcode barang</small>
      </div>
      <div class="modal-footer py-2 bg-light">
        <button type="button" class="btn btn-sm btn-secondary w-100 fw-bold" data-bs-dismiss="modal" onclick="stopCameraScanner()">Tutup Kamera</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL PENGELOLA BARANG FAVORIT             -->
<!-- ========================================== -->
<div class="modal fade" id="modalKelolaFavorit" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning text-dark py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-star-fill me-2"></i>Pengelola Barang Favorit</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="closeKelolaFavorit()"></button>
      </div>
      <div class="modal-body p-3">
        <div class="mb-3">
          <label class="form-label small fw-bold">Cari Nama Barang / Kemasan</label>
          <input type="text" id="inputSearchFavorit" class="form-control" placeholder="Ketik nama barang untuk menandai/menghapus favorit..." oninput="loadFavoritList(this.value)">
        </div>

        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th>Nama Barang</th>
                <th>Kemasan / Satuan</th>
                <th class="text-center" style="width: 120px;">Favorit</th>
              </tr>
            </thead>
            <tbody id="favoritListBody">
              <!-- Dinamis via JS -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2 bg-light">
        <button type="button" class="btn btn-sm btn-secondary fw-bold" data-bs-dismiss="modal" onclick="closeKelolaFavorit()">Selesai & Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL BARCODE TIDAK DITEMUKAN             -->
<!-- ========================================== -->
<div class="modal fade" id="modalNotFound" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning text-dark py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Barcode Tidak Ditemukan</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="resetFocusScan()"></button>
      </div>
      <div class="modal-body text-center p-4">
        <p class="mb-1">Barcode <strong id="notFoundBarcode" class="font-monospace text-danger fs-5"></strong> belum terdaftar di sistem.</p>
        <p class="text-muted small">Pilih salah satu tindakan di bawah ini untuk melanjutkan:</p>

        <div class="d-grid gap-2 mt-4">
          <button type="button" class="btn btn-outline-primary text-start p-3" onclick="openAttachBarcodeModal()">
            <div class="fw-bold"><i class="bi bi-link-45deg me-2"></i>Hubungkan ke Barang yang Sudah Ada</div>
            <small class="text-muted d-block ms-4">Tambahkan barcode ini sebagai varian barcode lain untuk produk yang sudah ada.</small>
          </button>

          <button type="button" class="btn btn-outline-success text-start p-3" onclick="redirectToCreateBarang()">
            <div class="fw-bold"><i class="bi bi-plus-circle me-2"></i>Buat Master Barang Baru</div>
            <small class="text-muted d-block ms-4">Daftarkan sebagai barang baru dari awal menggunakan barcode ini.</small>
          </button>
        </div>
      </div>
      <div class="modal-footer py-2 bg-light">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" onclick="resetFocusScan()">Batal</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL HUBUNGKAN BARCODE KE BARANG EXISTING -->
<!-- ========================================== -->
<div class="modal fade" id="modalAttachBarcode" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-link-45deg me-2"></i>Hubungkan Barcode ke Barang</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetFocusScan()"></button>
      </div>
      <div class="modal-body p-3">
        <form id="formAttachBarcode">
          <div class="mb-3">
            <label class="form-label small fw-bold">Barcode yang Discan</label>
            <input type="text" id="attachBarcodeVal" class="form-control font-monospace bg-light" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Pilih Barang & Kemasan Target</label>
            <input type="text" id="searchTargetBarang" class="form-control mb-2" placeholder="Cari nama barang target..." oninput="searchBarangTarget(this.value)">
            <select id="selectTargetKemasan" class="form-select" size="5" required>
              <!-- Dinamis via JS -->
            </select>
          </div>
          <button type="button" class="btn btn-primary w-100 fw-bold" onclick="simpanAttachBarcode()">
            <i class="bi bi-save me-1"></i> Simpan & Hubungkan Barcode
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL STRUK (CETAK THERMAL 58MM)           -->
<!-- ========================================== -->
<div class="modal fade" id="modalStruk" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white py-2 d-print-none">
        <h6 class="modal-title fw-bold"><i class="bi bi-printer me-2"></i>Struk Transaksi</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetFocusScan()"></button>
      </div>
      
      <div class="modal-body p-2">
        <div id="receiptArea" class="receipt-58mm">
          <div class="text-center mb-2">
            <h5 class="fw-bold mb-0 text-uppercase">KIOS MAFAZA</h5>
            <small class="d-block text-muted">Jl. Raya Utama No. 123</small>
            <small class="d-block text-muted">Telp/WA: 0812-3456-7890</small>
            <div class="border-top-dashed my-2"></div>
          </div>

          <div class="small mb-2">
            <div><strong>No:</strong> <span id="receiptNota">TRX-000</span></div>
            <div><strong>Tgl:</strong> <span id="receiptTanggal">00/00/0000 00:00</span></div>
            <div><strong>Bayar:</strong> <span id="receiptMetode">TUNAI</span></div>
            <div id="receiptPelangganBox" style="display:none;"><strong>Pelanggan:</strong> <span id="receiptPelanggan">-</span></div>
          </div>

          <div class="border-top-dashed my-2"></div>

          <table class="w-100 small receipt-table">
            <tbody id="receiptItems">
              <!-- Item dimasukkan via JS -->
            </tbody>
          </table>

          <div class="border-top-dashed my-2"></div>

          <div class="small fw-bold">
            <div class="d-flex justify-content-between">
              <span>Total:</span>
              <span id="receiptTotal">Rp 0</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Bayar:</span>
              <span id="receiptBayar">Rp 0</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Kembali:</span>
              <span id="receiptKembalian">Rp 0</span>
            </div>
          </div>

          <div class="border-top-dashed my-2"></div>

          <div class="text-center small mt-2">
            <p class="mb-0">-- Terima Kasih --</p>
            <small class="text-muted">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</small>
          </div>
        </div>
      </div>

      <div class="modal-footer py-2 bg-light d-print-none">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" onclick="resetFocusScan()">Tutup</button>
        <button type="button" class="btn btn-sm btn-success fw-bold" onclick="window.print()">
          <i class="bi bi-printer me-1"></i> Cetak Struk
        </button>
      </div>
    </div>
  </div>
</div>





<?php require_once BASE_PATH . 'partials/kasirfooter.php'; ?>