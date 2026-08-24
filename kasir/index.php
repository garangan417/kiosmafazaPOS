<?php
// kasir/index.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_pos.php';

// Ambil barang favorit untuk Quick Buttons
$barangFavorit = getBarangFavorit($pdoBarang);

require_once BASE_PATH . 'partials/header.php';
?>

<!-- HTML5-QRCode JS Library via CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<div class="container-fluid my-3 px-4">
  <div class="row g-3">
    
    <!-- KOLOM KIRI: INPUT & KERANJANG -->
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
          <label class="form-label fw-bold mb-1">
            <i class="bi bi-upc-scan text-primary me-1"></i> Cari / Scan Barcode Barang
          </label>
          <div class="input-group input-group-lg">
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
          <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold px-2 py-1" onclick="openKelolaFavorit()" style="font-size: 0.78rem;">
            <i class="bi bi-gear-fill me-1"></i> Kelola Favorit
          </button>
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
                <th>Nama Barang</th>
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
            <select id="metodeBayar" class="form-select">
              <option value="TUNAI" selected>TUNAI</option>
              <option value="TRANSFER">TRANSFER / QRIS</option>
              <option value="UTANG">UTANG / BON</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold small">Uang Diterima (Rp)</label>
            <input type="number" id="inputBayar" class="form-control form-control-lg font-monospace fw-bold" placeholder="0" oninput="hitungKembalian()">
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

          <button id="btnCheckout" class="btn btn-success btn-lg w-100 fw-bold" onclick="prosesCheckout()" disabled>
            <i class="bi bi-printer me-2"></i> SIMPAN & PROSES
          </button>
        </div>
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

<script>
let cart = [];
let scannedBarcode = '';
let isFavoritChanged = false;
let html5QrCode = null;

document.addEventListener("DOMContentLoaded", function() {
  const inputScan = document.getElementById('inputScan');

  // Event handler Scan / Search
  inputScan.addEventListener('keyup', function(e) {
    let q = this.value.trim();
    if (e.key === 'Enter' && q.length > 0) {
      processSearch(q);
    } else if (q.length > 2) {
      liveSearchNama(q);
    } else {
      document.getElementById('searchResult').style.display = 'none';
    }
  });
});

// ==========================================
// INTEGRASI KAMERA BARCODE SCANNER
// ==========================================
function openCameraScanner() {
  let modalCam = new bootstrap.Modal(document.getElementById('modalCameraScanner'));
  modalCam.show();

  setTimeout(() => {
    html5QrCode = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: { width: 250, height: 150 } };

    html5QrCode.start(
      { facingMode: "environment" }, // Gunakan Kamera Belakang HP
      config,
      onScanSuccess
    ).catch(err => {
      alert("Gagal membuka kamera: " + err);
      stopCameraScanner();
    });
  }, 300);
}

function onScanSuccess(decodedText, decodedResult) {
  // Tutup Kamera & Modal
  stopCameraScanner();
  let modalCamElem = document.getElementById('modalCameraScanner');
  let modalCam = bootstrap.Modal.getInstance(modalCamElem);
  if (modalCam) modalCam.hide();

  // Masukkan hasil scan ke input lalu proses pencarian
  document.getElementById('inputScan').value = decodedText;
  processSearch(decodedText);
}

function stopCameraScanner() {
  if (html5QrCode && html5QrCode.isScanning) {
    html5QrCode.stop().then(() => {
      html5QrCode.clear();
      resetFocusScan();
    }).catch(err => console.error(err));
  }
}

// Proses pencarian utama (Scan Exact atau Enter)
function processSearch(q) {
  fetch('api_search.php?q=' + encodeURIComponent(q))
    .then(res => res.json())
    .then(res => {
      if (res.status === 'success' && res.data.length > 0) {
        if (res.is_barcode || res.data.length === 1) {
          addToCart(res.data[0]);
          clearScan();
        } else {
          showSearchDropdown(res.data);
        }
      } else {
        scannedBarcode = q;
        document.getElementById('notFoundBarcode').innerText = scannedBarcode;
        
        let modalNF = new bootstrap.Modal(document.getElementById('modalNotFound'));
        modalNF.show();
      }
    });
}

// Live Search Dropdown Nama Barang
function liveSearchNama(q) {
  fetch('api_search.php?q=' + encodeURIComponent(q))
    .then(res => res.json())
    .then(res => {
      if (res.status === 'success' && res.data.length > 0) {
        showSearchDropdown(res.data);
      } else {
        document.getElementById('searchResult').style.display = 'none';
      }
    });
}

function showSearchDropdown(data) {
  let html = '';
  data.forEach(item => {
    html += `
      <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2" 
         onclick='selectFromDropdown(${JSON.stringify(item)})'>
        <div>
          <strong class="d-block text-dark">${item.nama_barang}</strong>
          <small class="text-muted">${item.nama_kemasan} (${item.satuan})</small>
        </div>
        <span class="badge bg-success font-monospace fs-6">Rp ${Math.round(item.harga_ecer).toLocaleString('id-ID')}</span>
      </a>`;
  });
  let sr = document.getElementById('searchResult');
  sr.innerHTML = html;
  sr.style.display = 'block';
}

function selectFromDropdown(item) {
  addToCart(item);
  clearScan();
}

function addToCart(item) {
  let existingIndex = cart.findIndex(c => c.kemasan_id === item.kemasan_id);
  if (existingIndex > -1) {
    cart[existingIndex].qty += 1;
    cart[existingIndex].subtotal = cart[existingIndex].qty * cart[existingIndex].harga_jual;
  } else {
    cart.push({
      kemasan_id: item.kemasan_id,
      nama_barang: item.nama_barang,
      nama_kemasan: item.nama_kemasan,
      satuan: item.satuan,
      harga_beli: item.harga_beli,
      harga_jual: item.harga_ecer,
      qty: 1,
      subtotal: item.harga_ecer
    });
  }
  renderCart();
}

function renderCart() {
  let tbody = document.getElementById('cartBody');
  let total = 0;
  tbody.innerHTML = '';

  if (cart.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-5">Keranjang masih kosong.</td></tr>`;
    document.getElementById('btnCheckout').disabled = true;
  } else {
    cart.forEach((item, index) => {
      total += item.subtotal;
      tbody.innerHTML += `
        <tr>
          <td>
            <strong class="d-block text-dark">${item.nama_barang}</strong>
            <small class="text-muted">${item.nama_kemasan}</small>
          </td>
          <td class="font-monospace">Rp ${Math.round(item.harga_jual).toLocaleString('id-ID')}</td>
          <td>
            <input type="number" class="form-control form-control-sm text-center font-monospace" min="1" value="${item.qty}" onchange="updateQty(${index}, this.value)">
          </td>
          <td class="text-end font-monospace fw-bold">Rp ${Math.round(item.subtotal).toLocaleString('id-ID')}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-danger px-2 py-1" onclick="removeItem(${index})" title="Hapus item ini">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>`;
    });
    document.getElementById('btnCheckout').disabled = false;
  }

  document.getElementById('displayTotal').innerText = 'Rp ' + Math.round(total).toLocaleString('id-ID');
  hitungKembalian();
}

function updateQty(index, val) {
  let qty = parseInt(val) || 1;
  cart[index].qty = qty;
  cart[index].subtotal = qty * cart[index].harga_jual;
  renderCart();
}

function removeItem(index) {
  cart.splice(index, 1);
  renderCart();
}

function clearCart() {
  cart = [];
  renderCart();
}

function clearScan() {
  document.getElementById('inputScan').value = '';
  document.getElementById('searchResult').style.display = 'none';
  resetFocusScan();
}

function resetFocusScan() {
  setTimeout(() => document.getElementById('inputScan').focus(), 300);
}

function setNominalBayar(val) {
  let total = cart.reduce((sum, item) => sum + item.subtotal, 0);
  if (val === 'PAS') {
    document.getElementById('inputBayar').value = total;
  } else {
    document.getElementById('inputBayar').value = val;
  }
  hitungKembalian();
}

function hitungKembalian() {
  let total = cart.reduce((sum, item) => sum + item.subtotal, 0);
  let bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
  let kembalian = bayar - total;
  
  let elem = document.getElementById('displayKembalian');
  elem.innerText = 'Rp ' + Math.round(kembalian).toLocaleString('id-ID');
  
  if (kembalian < 0) {
    elem.className = 'fw-bold fs-5 text-danger';
  } else {
    elem.className = 'fw-bold fs-5 text-success';
  }
}

// ==========================================
// KODE UNTUK PENGELOLA BARANG FAVORIT
// ==========================================
function openKelolaFavorit() {
  document.getElementById('inputSearchFavorit').value = '';
  loadFavoritList('');
  let modalFav = new bootstrap.Modal(document.getElementById('modalKelolaFavorit'));
  modalFav.show();
}

function loadFavoritList(q) {
  fetch('api_favorit.php?action=search&q=' + encodeURIComponent(q))
    .then(res => res.json())
    .then(res => {
      let tbody = document.getElementById('favoritListBody');
      tbody.innerHTML = '';
      if (res.status === 'success' && res.data.length > 0) {
        res.data.forEach(item => {
          let isFav = parseInt(item.is_favorite) === 1;
          let btnClass = isFav ? 'btn-warning text-dark' : 'btn-outline-secondary';
          let iconClass = isFav ? 'bi-star-fill' : 'bi-star';
          let newStatus = isFav ? 0 : 1;

          tbody.innerHTML += `
            <tr>
              <td><strong class="text-dark">${item.nama_barang}</strong></td>
              <td><span class="badge bg-light text-dark border">${item.nama_kemasan} (${item.satuan})</span></td>
              <td class="text-center">
                <button type="button" class="btn btn-sm ${btnClass} fw-bold" onclick="toggleFavorit(${item.kemasan_id}, ${newStatus})">
                  <i class="bi ${iconClass}"></i> ${isFav ? 'Favorit' : 'Biasa'}
                </button>
              </td>
            </tr>`;
        });
      } else {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Barang tidak ditemukan.</td></tr>';
      }
    });
}

function toggleFavorit(kemasanId, status) {
  let formData = new FormData();
  formData.append('kemasan_id', kemasanId);
  formData.append('status', status);

  fetch('api_favorit.php?action=toggle', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.status === 'success') {
      isFavoritChanged = true;
      let q = document.getElementById('inputSearchFavorit').value;
      loadFavoritList(q);
    } else {
      alert('Gagal mengubah favorit: ' + res.message);
    }
  });
}

function closeKelolaFavorit() {
  if (isFavoritChanged) {
    location.reload();
  } else {
    resetFocusScan();
  }
}

// ==========================================
// INTEGRASI BARCODE BARU
// ==========================================
function redirectToCreateBarang() {
  window.location.href = '../barang/?barcode=' + encodeURIComponent(scannedBarcode);
}

function openAttachBarcodeModal() {
  let modalNF = bootstrap.Modal.getInstance(document.getElementById('modalNotFound'));
  if (modalNF) modalNF.hide();

  document.getElementById('attachBarcodeVal').value = scannedBarcode;
  searchBarangTarget('');

  let modalAttach = new bootstrap.Modal(document.getElementById('modalAttachBarcode'));
  modalAttach.show();
}

function searchBarangTarget(q) {
  fetch('api_search.php?q=' + encodeURIComponent(q))
    .then(res => res.json())
    .then(res => {
      let select = document.getElementById('selectTargetKemasan');
      select.innerHTML = '';
      if (res.status === 'success' && res.data.length > 0) {
        res.data.forEach(item => {
          select.innerHTML += `<option value="${item.kemasan_id}">${item.nama_barang} - ${item.nama_kemasan} (Rp ${Math.round(item.harga_ecer).toLocaleString('id-ID')})</option>`;
        });
      } else {
        select.innerHTML = '<option disabled>Tidak ada barang ditemukan...</option>';
      }
    });
}

function simpanAttachBarcode() {
  let kemasanId = document.getElementById('selectTargetKemasan').value;
  let barcode = document.getElementById('attachBarcodeVal').value;

  if (!kemasanId) {
    alert('Pilih barang target terlebih dahulu!');
    return;
  }

  fetch('api_attach_barcode.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ barang_kemasan_id: kemasanId, barcode: barcode })
  })
  .then(res => res.json())
  .then(res => {
    if (res.status === 'success') {
      alert('Barcode berhasil dihubungkan!');
      let modalAttach = bootstrap.Modal.getInstance(document.getElementById('modalAttachBarcode'));
      if (modalAttach) modalAttach.hide();
      
      processSearch(barcode);
    } else {
      alert('Gagal: ' + res.message);
    }
  });
}

function prosesCheckout() {
  if (cart.length === 0) {
    alert('Keranjang belanja masih kosong!');
    return;
  }

  let total = cart.reduce((sum, item) => sum + item.subtotal, 0);
  let bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
  let metode = document.getElementById('metodeBayar').value;

  if (metode === 'TUNAI' && bayar < total) {
    alert('Uang pembayaran masih kurang!');
    return;
  }

  let btnCheckout = document.getElementById('btnCheckout');
  btnCheckout.disabled = true;
  btnCheckout.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan Transaksi...';

  let kembalian = bayar - total;

  let payload = {
    total_kotor: total,
    diskon: 0,
    total_bersih: total,
    bayar: bayar,
    kembalian: kembalian > 0 ? kembalian : 0,
    metode_bayar: metode,
    catatan: '',
    items: cart
  };

  fetch('api_checkout.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(res => {
    btnCheckout.disabled = false;
    btnCheckout.innerHTML = '<i class="bi bi-printer me-2"></i> SIMPAN & PROSES';

    if (res.status === true) {
      let now = new Date();
      let tglStr = now.toLocaleDateString('id-ID') + ' ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

      document.getElementById('receiptNota').innerText = res.no_faktur || res.no_nota || 'TRX-' + Date.now();
      document.getElementById('receiptTanggal').innerText = tglStr;
      document.getElementById('receiptMetode').innerText = metode;

      let itemsHtml = '';
      cart.forEach(item => {
        itemsHtml += `
          <tr>
            <td colspan="2"><strong>${item.nama_barang}</strong> (${item.nama_kemasan})</td>
          </tr>
          <tr>
            <td>${item.qty} x ${Math.round(item.harga_jual).toLocaleString('id-ID')}</td>
            <td class="text-end">Rp ${Math.round(item.subtotal).toLocaleString('id-ID')}</td>
          </tr>`;
      });
      document.getElementById('receiptItems').innerHTML = itemsHtml;

      document.getElementById('receiptTotal').innerText = 'Rp ' + Math.round(total).toLocaleString('id-ID');
      document.getElementById('receiptBayar').innerText = 'Rp ' + Math.round(bayar).toLocaleString('id-ID');
      document.getElementById('receiptKembalian').innerText = 'Rp ' + Math.round(kembalian > 0 ? kembalian : 0).toLocaleString('id-ID');

      let modalStruk = new bootstrap.Modal(document.getElementById('modalStruk'));
      modalStruk.show();

      clearCart();
      document.getElementById('inputBayar').value = '';
      document.getElementById('displayKembalian').innerText = 'Rp 0';
    } else {
      alert('Gagal menyimpan transaksi: ' + (res.message || 'Terjadi kesalahan pada server.'));
    }
  })
  .catch(err => {
    btnCheckout.disabled = false;
    btnCheckout.innerHTML = '<i class="bi bi-printer me-2"></i> SIMPAN & PROSES';
    alert('Terjadi kesalahan koneksi atau server!');
    console.error('Checkout error:', err);
  });
}
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>