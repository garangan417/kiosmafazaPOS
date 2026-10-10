/**
 * @project   Kios Mafaza POS - Helper Utilities
 * @author    Garangan417
 * @license   Non-Commercial Public License (NC-PL)
 * @copyright (c) 2026 Garangan417. All Rights Reserved.
 */

// ==========================================
// GLOBAL STATE
// ==========================================
let cart = [];
let scannedBarcode = '';
let isFavoritChanged = false;
let html5QrCode = null;
let currentSearchResults = [];

// State untuk debounce & abort live search
let searchTimer = null;
let searchAbort = null;

// State untuk modal utang
let currentSisaUtangKasir = 0;
let debounceUtangTimer = null;

// State untuk daftar pelanggan (dropdown pelanggan)
let daftarPelangganGlobal = [];


// ==========================================
// INIT
// ==========================================
document.addEventListener("DOMContentLoaded", function() {
    const inputScan = document.getElementById('inputScan');

    // ⚠️ PENTING: Cek dulu apakah elemen ada (halaman kasir saja)
    if (!inputScan) {
        // Bukan halaman kasir, skip semua logic kasir
        return;
    }

    // ==========================================
    // EVENT HANDLER: SCAN / SEARCH (keydown)
    // ==========================================
    inputScan.addEventListener('keydown', function(e) {
        let q = this.value.trim();

        // Enter → proses (barcode atau nama)
        if (e.key === 'Enter' && q.length > 0) {
            e.preventDefault();

            clearTimeout(searchTimer);
            if (searchAbort) {
                searchAbort.abort();
                searchAbort = null;
            }

            const isBarcodeFormat = /^\d{6,}$/.test(q);
            if (isBarcodeFormat) {
                processSearch(q);
            } else {
                liveSearchNama(q);
            }
            return;
        }

        if (e.key === 'Escape') {
            document.getElementById('searchResult').style.display = 'none';
            return;
        }

        // Skip tombol navigasi & modifier
        if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab', 'Shift', 'Control', 'Alt', 'Meta'].includes(e.key)) {
            return;
        }

        if (searchAbort) {
            searchAbort.abort();
            searchAbort = null;
        }
        clearTimeout(searchTimer);

        if (q.length <= 2) {
            document.getElementById('searchResult').style.display = 'none';
            return;
        }

        searchTimer = setTimeout(() => {
            liveSearchNama(q);
        }, 300);
    });

    // ==========================================
    // KLIK DI LUAR → TUTUP DROPDOWN BARANG
    // ==========================================
    document.addEventListener('click', function(e) {
        const sr = document.getElementById('searchResult');
        const input = document.getElementById('inputScan');
        if (!sr || !input) return;

        if (!e.target.closest('#searchResult') && e.target !== input) {
            sr.style.display = 'none';
        }
    });

    // ==========================================
    // LOAD AWAL PELANGGAN
    // ==========================================
    loadPelangganList();

    // ==========================================
    // EVENT LISTENER: SEARCH BOX PELANGGAN
    // ==========================================
    const inputCariPelanggan = document.getElementById('inputCariPelanggan');
    if (inputCariPelanggan) {
        inputCariPelanggan.addEventListener('input', function() {
            filterPelangganDropdown(this.value);
        });
    }

    // Reset search & fokus otomatis saat dropdown dibuka
    const btnPilihPelanggan = document.getElementById('btnPilihPelanggan');
    if (btnPilihPelanggan) {
        btnPilihPelanggan.addEventListener('shown.bs.dropdown', function() {
            if (inputCariPelanggan) {
                inputCariPelanggan.value = '';
                inputCariPelanggan.focus();
            }
            renderPelangganDropdown(daftarPelangganGlobal);
        });
    }
});


// ==========================================
// HELPER / UTILITY
// ==========================================

function hitungHargaTieringJS(item, qty) {
    let hargaEcer   = parseFloat(item.harga_ecer || item.harga_jual || 0);
    let hargaGrosir = parseFloat(item.harga_grosir || 0);
    let minGrosir   = parseInt(item.min_qty_grosir || 0);

    if (hargaGrosir > 0 && minGrosir > 0 && qty >= minGrosir) {
        return { harga_jual: hargaGrosir, jenis_harga: 'GROSIR' };
    }

    return { harga_jual: hargaEcer, jenis_harga: 'ECER' };
}


function formatInputRupiahJS(input) {
    let value = input.value.replace(/\D/g, '');

    if (value) {
        input.value = parseInt(value, 10).toLocaleString('id-ID');
    } else {
        input.value = '';
    }
}


function escapeHtml(text) {
    if (!text) return '';

    return text.toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}


// ==========================================
// PELANGGAN & METODE PEMBAYARAN
// ==========================================

function toggleFormUtang() {
    let metode = document.getElementById('metodeBayar').value;
    let boxPelanggan = document.getElementById('boxPelangganUtang');

    if (metode === 'UTANG') {
        boxPelanggan.style.display = 'block';
    } else {
        boxPelanggan.style.display = 'none';
    }

    if (metode === 'TRANSFER') {
        setNominalBayar('PAS');
    }
}


// ==========================================
// PELANGGAN — BOOTSTRAP DROPDOWN CUSTOM
// ==========================================

function loadPelangganList() {
    fetch('api_search_pelanggan.php')
    .then(res => res.json())
    .then(res => {
        daftarPelangganGlobal = (res.status === 'success' && Array.isArray(res.data))
        ? res.data
        : [];
        renderPelangganDropdown(daftarPelangganGlobal);
    })
    .catch(err => {
        console.error('loadPelangganList error:', err);
        renderPelangganDropdown([]);
    });
}


function renderPelangganDropdown(list) {
    const container = document.getElementById('pelangganListContainer');
    if (!container) return;

    if (!list || list.length === 0) {
        container.innerHTML = `
        <span class="dropdown-item-text text-muted small text-center d-block py-3">
        Tidak ada pelanggan.
        </span>
        `;
        return;
    }

    let html = '';
    list.forEach(p => {
        const namaEsc = escapeHtml(p.nama);
        const hpEsc   = p.no_hp ? escapeHtml(p.no_hp) : '';

        html += `
        <a class="dropdown-item d-flex justify-content-between align-items-center py-2"
        href="#"
        onclick="pilihPelanggan(${p.id}, '${namaEsc.replace(/'/g, "\\'")}'); return false;">
        <div style="min-width:0;">
        <div class="fw-semibold text-truncate">${namaEsc}</div>
        ${hpEsc ? `<small class="text-muted">${hpEsc}</small>` : ''}
        </div>
        <i class="bi bi-check2 text-success d-none check-icon-${p.id}"></i>
        </a>
        `;
    });

    container.innerHTML = html;
}


function pilihPelanggan(id, nama) {
    // Simpan ke hidden input
    const hiddenInput = document.getElementById('selectPelanggan');
    if (hiddenInput) hiddenInput.value = id;

    // Update label button
    const label = document.getElementById('labelPelangganTerpilih');
    if (label) label.textContent = nama;

    // Tandai item yang dipilih
    document.querySelectorAll('[class*="check-icon-"]').forEach(el => {
        el.classList.add('d-none');
    });
    const checkIcon = document.querySelector('.check-icon-' + id);
    if (checkIcon) checkIcon.classList.remove('d-none');

    // Tutup dropdown
    const btn = document.getElementById('btnPilihPelanggan');
    if (btn) {
        const dd = bootstrap.Dropdown.getInstance(btn);
        if (dd) dd.hide();
    }
}


function filterPelangganDropdown(keyword) {
    const q = (keyword || '').toLowerCase().trim();

    if (!q) {
        renderPelangganDropdown(daftarPelangganGlobal);
        return;
    }

    const filtered = daftarPelangganGlobal.filter(p => {
        const nama = (p.nama || '').toLowerCase();
        const hp   = (p.no_hp || '').toLowerCase();
        return nama.includes(q) || hp.includes(q);
    });

    renderPelangganDropdown(filtered);
}


// ==========================================
// TRANSAKSI JASA / PPOB
// ==========================================

function openModalJasa() {
    document.getElementById('formJasa').reset();

    let modalJasa = new bootstrap.Modal(
        document.getElementById('modalTransaksiJasa')
    );

    modalJasa.show();
}


function tambahJasaKeKeranjang() {
    let kategori = document.getElementById('jasaKategori').value;
    let keterangan = document.getElementById('jasaKeterangan').value.trim();
    let rawNominal = document.getElementById('jasaNominal').value.replace(/\./g, '');
    let nominal = parseFloat(rawNominal) || 0;

    if (nominal <= 0) {
        alert('Nominal transaksi harus lebih dari 0!');
        return;
    }

    let itemJasa = {
        tipe: 'JASA',
        kemasan_id: null,
        nama_barang: `[${kategori}] ${keterangan}`,
        nama_kemasan: kategori,
        satuan: 'trx',
        harga_beli: 0,
        harga_jual: nominal,
        harga_ecer: nominal,
        harga_grosir: 0,
        min_qty_grosir: 0,
        jenis_harga: 'ECER',
        qty: 1,
        subtotal: nominal,
        is_jasa: true,
        kategori: kategori,
        keterangan: keterangan
    };

    addToCart(itemJasa);

    let modalJasa = bootstrap.Modal.getInstance(
        document.getElementById('modalTransaksiJasa')
    );

    if (modalJasa) modalJasa.hide();

    resetFocusScan();
}


// ==========================================
// INTEGRASI KAMERA BARCODE SCANNER
// ==========================================

function openCameraScanner() {
    let modalCam = new bootstrap.Modal(
        document.getElementById('modalCameraScanner')
    );

    modalCam.show();

    setTimeout(() => {
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        const config = { fps: 10, qrbox: { width: 250, height: 150 } };

        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess
        ).catch(err => {
            alert("Gagal membuka kamera: " + err);
            stopCameraScanner();
        });

    }, 300);
}


function onScanSuccess(decodedText, decodedResult) {
    stopCameraScanner();

    let modalCam = bootstrap.Modal.getInstance(
        document.getElementById('modalCameraScanner')
    );

    if (modalCam) modalCam.hide();

    document.getElementById('inputScan').value = decodedText;
    processSearch(decodedText);
}


function stopCameraScanner() {
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop()
        .then(() => {
            html5QrCode.clear();
            resetFocusScan();
        })
        .catch(err => console.error(err));
    }
}


// ==========================================
// PENCARIAN BARANG
// ==========================================

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
            document.getElementById('inputScan').value = '';

            let modalNF = new bootstrap.Modal(
                document.getElementById('modalNotFound')
            );
            modalNF.show();
        }
    })
    .catch(err => console.error('processSearch error:', err));
}


function liveSearchNama(q) {
    if (searchAbort) {
        searchAbort.abort();
    }
    searchAbort = new AbortController();

    fetch('api_search.php?q=' + encodeURIComponent(q), {
        signal: searchAbort.signal
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success' && res.data.length > 0) {
            showSearchDropdown(res.data);
        } else if (res.status === 'too_short' || res.status === 'empty') {
            document.getElementById('searchResult').style.display = 'none';
        } else {
            showEmptyDropdown(q);
        }
    })
    .catch(err => {
        if (err.name !== 'AbortError') {
            console.error('liveSearchNama error:', err);
        }
    });
}


function showSearchDropdown(data) {
    currentSearchResults = data;

    let html = '';

    html += `
    <div class="dropdown-header-custom">
    <span><i class="bi bi-search me-1"></i> ${data.length} hasil ditemukan</span>
    <button type="button"
    class="btn-close btn-close-sm"
    onclick="hideSearchDropdown()"
    aria-label="Tutup"></button>
    </div>
    `;

    data.forEach((item, index) => {
        let teksSatuan = item.satuan ? item.satuan : item.nama_kemasan;
        let harga = Math.round(item.harga_ecer || item.harga_jual || 0);

        html += `
        <a href="#"
        class="dropdown-item-custom"
        onclick="selectFromDropdown(${index}); return false;">
        <div class="flex-grow-1" style="min-width: 0;">
        <div class="item-nama">${escapeHtml(item.nama_barang)}</div>
        <div class="item-subinfo">
        ${escapeHtml(teksSatuan || '')}${item.isi ? ' • isi ' + escapeHtml(item.isi) : ''}
        </div>
        </div>
        <div class="text-end">
        <div class="item-harga">Rp ${harga.toLocaleString('id-ID')}</div>
        </div>
        </a>
        `;
    });

    let sr = document.getElementById('searchResult');
    sr.innerHTML = html;
    sr.style.display = 'block';
}


function showEmptyDropdown(q) {
    let sr = document.getElementById('searchResult');

    sr.innerHTML = `
    <div class="dropdown-header-custom">
    <span><i class="bi bi-search me-1"></i> Hasil Pencarian</span>
    <button type="button"
    class="btn-close btn-close-sm"
    onclick="hideSearchDropdown()"
    aria-label="Tutup"></button>
    </div>
    <div class="p-3 text-center text-muted small">
    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
    Tidak ada barang yang cocok dengan
    "<strong>${escapeHtml(q)}</strong>"
    </div>
    `;
    sr.style.display = 'block';
}


function hideSearchDropdown() {
    let sr = document.getElementById('searchResult');
    if (sr) sr.style.display = 'none';
}


function selectFromDropdown(index) {
    if (currentSearchResults[index]) {
        addToCart(currentSearchResults[index]);
        clearScan();
    }
}


// ==========================================
// KERANJANG BELANJA
// ==========================================

function addToCart(item) {
    let isJasa = item.is_jasa || false;

    let existingIndex = cart.findIndex(c =>
    isJasa
    ? (c.is_jasa && c.keterangan === item.keterangan)
    : (c.kemasan_id === item.kemasan_id)
    );

    if (existingIndex > -1) {
        let newQty = cart[existingIndex].qty + 1;
        cart[existingIndex].qty = newQty;

        if (!isJasa) {
            let tiering = hitungHargaTieringJS(cart[existingIndex], newQty);
            cart[existingIndex].harga_jual = tiering.harga_jual;
            cart[existingIndex].jenis_harga = tiering.jenis_harga;
        }

        cart[existingIndex].subtotal = newQty * cart[existingIndex].harga_jual;

    } else {
        let initialQty = item.qty || 1;
        let initialHarga = item.harga_jual || item.harga_ecer || 0;
        let initialJenis = item.jenis_harga || 'ECER';

        if (!isJasa) {
            let tiering = hitungHargaTieringJS(item, initialQty);
            initialHarga = tiering.harga_jual;
            initialJenis = tiering.jenis_harga;
        }

        let namaLengkapBarang = item.nama_lengkap
        ? item.nama_lengkap
        : (item.satuan && !item.nama_barang.includes(`(${item.satuan})`)
        ? `${item.nama_barang} (${item.satuan})`
        : item.nama_barang);

        cart.push({
            tipe: isJasa ? 'JASA' : 'BARANG',
            kemasan_id: item.kemasan_id || null,
            nama_barang: isJasa ? item.nama_barang : namaLengkapBarang,
            nama_kemasan: item.nama_kemasan || '',
            satuan: item.satuan || 'pcs',
            harga_beli: item.harga_beli || 0,
            harga_ecer: item.harga_ecer || initialHarga,
            harga_grosir: item.harga_grosir || 0,
            min_qty_grosir: item.min_qty_grosir || 0,
            harga_jual: initialHarga,
            jenis_harga: initialJenis,
            qty: initialQty,
            subtotal: item.subtotal || (initialQty * initialHarga),
                  is_jasa: isJasa,
                  kategori: item.kategori || (isJasa ? 'PPOB / Jasa' : 'Barang Toko'),
                  keterangan: item.keterangan || item.nama_barang
        });
    }

    renderCart();
}


function renderCart() {
    let tbody = document.getElementById('cartBody');
    let total = 0;

    tbody.innerHTML = '';

    if (cart.length === 0) {
        tbody.innerHTML = `
        <tr>
        <td colspan="5" class="text-center text-muted py-5">
        Keranjang masih kosong.
        </td>
        </tr>
        `;

        document.getElementById('btnCheckout').disabled = true;
    } else {
        cart.forEach((item, index) => {
            total += item.subtotal;

            tbody.innerHTML += `
            <tr>
            <td>
            <strong class="d-block text-dark">${escapeHtml(item.nama_barang)}</strong>
            </td>
            <td class="font-monospace">
            Rp ${Math.round(item.harga_jual).toLocaleString('id-ID')}
            </td>
            <td>
            <input type="number"
            class="form-control form-control-sm text-center font-monospace fw-bold"
            min="1"
            value="${item.qty}"
            onchange="updateQty(${index}, this.value)">
            </td>
            <td class="text-end font-monospace fw-bold">
            Rp ${Math.round(item.subtotal).toLocaleString('id-ID')}
            </td>
            <td class="text-center">
            <button class="btn btn-sm btn-outline-danger px-2 py-1"
            onclick="removeItem(${index})"
            title="Hapus item ini">
            <i class="bi bi-trash"></i>
            </button>
            </td>
            </tr>
            `;
        });

        document.getElementById('btnCheckout').disabled = false;
    }

    document.getElementById('displayTotal').innerText =
    'Rp ' + Math.round(total).toLocaleString('id-ID');

    hitungKembalian();
}


function updateQty(index, val) {
    let qty = parseInt(val) || 1;
    let item = cart[index];

    item.qty = qty;

    if (!item.is_jasa) {
        let tiering = hitungHargaTieringJS(item, qty);
        item.harga_jual = tiering.harga_jual;
        item.jenis_harga = tiering.jenis_harga;
    }

    item.subtotal = qty * item.harga_jual;
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


// ==========================================
// INPUT SCAN & FOCUS
// ==========================================

function clearScan() {
    const inputScan = document.getElementById('inputScan');
    if (inputScan) inputScan.value = '';

    const searchResult = document.getElementById('searchResult');
    if (searchResult) searchResult.style.display = 'none';

    resetFocusScan();
}

function resetFocusScan() {
    setTimeout(() => {
        const inputScan = document.getElementById('inputScan');
        if (inputScan) inputScan.focus();
    }, 100);
}


// ==========================================
// PEMBAYARAN
// ==========================================

function setNominalBayar(val) {
    let inputBayar = document.getElementById('inputBayar');
    let total = cart.reduce((sum, item) => sum + item.subtotal, 0);

    if (val === 'PAS') {
        inputBayar.value = total;
    } else {
        inputBayar.value = val;
    }

    formatInputRupiahJS(inputBayar);
    hitungKembalian();
}


function hitungKembalian() {
    let total = cart.reduce((sum, item) => sum + item.subtotal, 0);
    let rawBayar = document.getElementById('inputBayar').value.replace(/\./g, '');
    let bayar = parseFloat(rawBayar) || 0;
    let kembalian = bayar - total;

    let elem = document.getElementById('displayKembalian');

    elem.innerText = 'Rp ' + Math.round(kembalian).toLocaleString('id-ID');

    if (kembalian < 0) {
        elem.className = 'fw-bold fs-5 text-danger';
    } else {
        elem.className = 'fw-bold fs-5 text-dark';
    }
}


// ==========================================
// PENGELOLA BARANG FAVORIT
// ==========================================

function openKelolaFavorit() {
    document.getElementById('inputSearchFavorit').value = '';
    loadFavoritList('');

    let modalFav = new bootstrap.Modal(
        document.getElementById('modalKelolaFavorit')
    );
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
                <td>
                <strong class="text-dark">${escapeHtml(item.nama_barang)}</strong>
                </td>
                <td>
                <span class="badge bg-light text-dark border">
                ${escapeHtml(item.satuan && item.satuan.trim() !== '' ? item.satuan : item.nama_kemasan)}
                </span>
                </td>
                <td class="text-center">
                <button type="button"
                class="btn btn-sm ${btnClass} fw-bold"
                onclick="toggleFavorit(${item.kemasan_id}, ${newStatus})">
                <i class="bi ${iconClass}"></i>
                ${isFav ? 'Favorit' : 'Biasa'}
                </button>
                </td>
                </tr>
                `;
            });
        } else {
            tbody.innerHTML = `
            <tr>
            <td colspan="3" class="text-center text-muted py-4">
            Barang tidak ditemukan.
            </td>
            </tr>
            `;
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
    let modalNF = bootstrap.Modal.getInstance(
        document.getElementById('modalNotFound')
    );
    if (modalNF) modalNF.hide();

    document.getElementById('attachBarcodeVal').value = scannedBarcode;
    searchBarangTarget('');

    let modalAttach = new bootstrap.Modal(
        document.getElementById('modalAttachBarcode')
    );
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
                let namaTarget = item.nama_lengkap
                ? item.nama_lengkap
                : (item.nama_kemasan && !item.nama_barang.includes(`(${item.nama_kemasan})`)
                ? `${item.nama_barang} (${item.nama_kemasan})`
                : item.nama_barang);

                select.innerHTML += `
                <option value="${item.kemasan_id}">
                ${escapeHtml(namaTarget)} (Rp ${Math.round(item.harga_ecer).toLocaleString('id-ID')})
                </option>
                `;
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
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ barang_kemasan_id: kemasanId, barcode: barcode })
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            alert('Barcode berhasil dihubungkan!');

            let modalAttach = bootstrap.Modal.getInstance(
                document.getElementById('modalAttachBarcode')
            );
            if (modalAttach) modalAttach.hide();

            processSearch(barcode);
        } else {
            alert('Gagal: ' + res.message);
        }
    });
}


// ==========================================
// CHECKOUT
// ==========================================

function prosesCheckout() {
    if (cart.length === 0) {
        alert('Keranjang belanja masih kosong!');
        return;
    }

    let total = cart.reduce((sum, item) => sum + item.subtotal, 0);
    let rawBayar = document.getElementById('inputBayar').value.replace(/\./g, '');
    let bayar = parseFloat(rawBayar) || 0;
    let metode = document.getElementById('metodeBayar').value;
    let pelangganId = document.getElementById('selectPelanggan').value;

    if (metode === 'TUNAI' && bayar < total) {
        alert('Uang pembayaran masih kurang!');
        return;
    }

    if (metode === 'UTANG' && !pelangganId) {
        alert('Silakan pilih Pelanggan terlebih dahulu untuk transaksi UTANG / BON!');
        return;
    }

    let btnCheckout = document.getElementById('btnCheckout');
    btnCheckout.disabled = true;
    btnCheckout.innerHTML = `
    <span class="spinner-border spinner-border-sm me-2"></span>
    Menyimpan Transaksi...
    `;

    let kembalian = bayar - total;

    // TANGGAL & WAKTU
    let today = new Date();
    let yyyy = today.getFullYear();
    let mm = String(today.getMonth() + 1).padStart(2, '0');
    let dd = String(today.getDate()).padStart(2, '0');
    let hh = String(today.getHours()).padStart(2, '0');
    let ii = String(today.getMinutes()).padStart(2, '0');
    let ss = String(today.getSeconds()).padStart(2, '0');

    let tglForm = `${yyyy}-${mm}-${dd}`;
    let datetimeFull = `${yyyy}-${mm}-${dd} ${hh}:${ii}:${ss}`;

    // PISAH BARANG FISIK & JASA/PPOB
    let itemsBarang = cart.filter(item => !item.is_jasa);
    let itemsJasa = cart.filter(item => item.is_jasa);

    // SNAPSHOT CART (untuk render struk nanti)
    let cartSnapshot = cart.map(item => ({ ...item }));

    let payload = {
        tanggal: tglForm,
        created_at: datetimeFull,
        total_kotor: total,
        diskon: 0,
        total_bersih: total,
        bayar: bayar,
        kembalian: kembalian > 0 ? kembalian : 0,
        metode_bayar: metode,
        pelanggan_id: pelangganId || 0,
        catatan: '',
        items: cart,
        items_barang: itemsBarang,
        items_jasa: itemsJasa
    };

    fetch('api_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        btnCheckout.disabled = false;
        btnCheckout.innerHTML = `
        <i class="bi bi-printer me-2"></i>
        SIMPAN & PROSES
        `;

        if (res.status === true) {
            let now = new Date();
            let tglStr = now.toLocaleDateString('id-ID') + ' ' +
            now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

            // DATA STRUK
            document.getElementById('receiptNota').innerText =
            res.no_faktur || res.no_nota || 'TRX-' + Date.now();
            document.getElementById('receiptTanggal').innerText = tglStr;
            document.getElementById('receiptMetode').innerText = metode;

            // PELANGGAN UTANG
            if (metode === 'UTANG' && res.nama_pelanggan) {
                document.getElementById('receiptPelangganBox').style.display = 'block';
                document.getElementById('receiptPelanggan').innerText = res.nama_pelanggan;
            } else {
                document.getElementById('receiptPelangganBox').style.display = 'none';
            }

            // ITEM STRUK
            let itemsHtml = '';
            cartSnapshot.forEach(item => {
                itemsHtml += `
                <tr>
                <td colspan="2">
                <strong>${escapeHtml(item.nama_barang)}</strong>
                </td>
                </tr>
                <tr>
                <td>
                ${item.qty} x ${Math.round(item.harga_jual).toLocaleString('id-ID')}
                </td>
                <td class="text-end">
                Rp ${Math.round(item.subtotal).toLocaleString('id-ID')}
                </td>
                </tr>
                `;
            });
            document.getElementById('receiptItems').innerHTML = itemsHtml;

            // TOTAL STRUK
            document.getElementById('receiptTotal').innerText =
            'Rp ' + Math.round(total).toLocaleString('id-ID');
            document.getElementById('receiptBayar').innerText =
            'Rp ' + Math.round(bayar).toLocaleString('id-ID');
            document.getElementById('receiptKembalian').innerText =
            'Rp ' + Math.round(kembalian > 0 ? kembalian : 0).toLocaleString('id-ID');

            // TAMPILKAN MODAL STRUK
            let modalStruk = new bootstrap.Modal(
                document.getElementById('modalStruk')
            );
            modalStruk.show();

            // RESET TRANSAKSI
            clearCart();
            document.getElementById('inputBayar').value = '';
            document.getElementById('displayKembalian').innerText = 'Rp 0';
            document.getElementById('metodeBayar').value = 'TUNAI';

            // Reset pelanggan
            document.getElementById('selectPelanggan').value = '';
            const labelPel = document.getElementById('labelPelangganTerpilih');
            if (labelPel) labelPel.textContent = '-- Pilih Pelanggan --';

            toggleFormUtang();

        } else {
            alert('Gagal menyimpan transaksi: ' +
            (res.message || 'Terjadi kesalahan pada server.'));
        }
    })
    .catch(err => {
        btnCheckout.disabled = false;
        btnCheckout.innerHTML = `
        <i class="bi bi-printer me-2"></i>
        SIMPAN & PROSES
        `;
        alert('Terjadi kesalahan koneksi atau server!');
        console.error('Checkout error:', err);
    });
}


// ==========================================
// TAMBAH PELANGGAN BARU (DARI KASIR)
// ==========================================

function openModalTambahPelanggan() {
    document.getElementById('formTambahPelangganKasir').reset();
    document.getElementById('alertTambahPelanggan').innerHTML = '';

    let modal = new bootstrap.Modal(document.getElementById('modalTambahPelanggan'));
    modal.show();

    setTimeout(() => {
        document.getElementById('newPelangganNama').focus();
    }, 300);
}


function simpanPelangganBaru() {
    let nama   = document.getElementById('newPelangganNama').value.trim();
    let no_hp  = document.getElementById('newPelangganHp').value.trim();
    let alamat = document.getElementById('newPelangganAlamat').value.trim();

    if (!nama) {
        document.getElementById('alertTambahPelanggan').innerHTML =
        '<div class="alert alert-danger py-2 small">Nama pelanggan wajib diisi!</div>';
        return;
    }

    let btn = document.getElementById('btnSimpanPelanggan');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    let formData = new FormData();
    formData.append('nama', nama);
    formData.append('no_hp', no_hp);
    formData.append('alamat', alamat);

    fetch('api_tambah_pelanggan.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i> Simpan & Pilih Pelanggan';

        if (res.status === 'success') {
            let modal = bootstrap.Modal.getInstance(
                document.getElementById('modalTambahPelanggan')
            );
            if (modal) modal.hide();

            loadPelangganListDanPilih(res.data.id);
        } else {
            document.getElementById('alertTambahPelanggan').innerHTML =
            '<div class="alert alert-danger py-2 small">' +
            (res.message || 'Gagal menyimpan') + '</div>';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i> Simpan & Pilih Pelanggan';
        document.getElementById('alertTambahPelanggan').innerHTML =
        '<div class="alert alert-danger py-2 small">Terjadi kesalahan koneksi.</div>';
        console.error('Error simpan pelanggan:', err);
    });
}


function loadPelangganListDanPilih(selectedId) {
    fetch('api_search_pelanggan.php')
    .then(res => res.json())
    .then(res => {
        daftarPelangganGlobal = (res.status === 'success' && Array.isArray(res.data))
        ? res.data
        : [];
        renderPelangganDropdown(daftarPelangganGlobal);

        if (selectedId) {
            const found = daftarPelangganGlobal.find(p => String(p.id) === String(selectedId));
            if (found) {
                pilihPelanggan(found.id, found.nama);
            }
        }

        resetFocusScan();
    })
    .catch(err => {
        console.error('loadPelangganListDanPilih error:', err);
    });
}


// ==========================================
// MODAL DAFTAR PELANGGAN UTANG AKTIF
// ==========================================

function openModalDaftarUtang() {
    document.getElementById('inputSearchUtang').value = '';

    let modal = new bootstrap.Modal(document.getElementById('modalDaftarUtang'));
    modal.show();

    loadDaftarUtang('');
}


function debounceCariUtang(val) {
    clearTimeout(debounceUtangTimer);
    debounceUtangTimer = setTimeout(() => {
        loadDaftarUtang(val);
    }, 400);
}


function loadDaftarUtang(q) {
    let loading = document.getElementById('utangLoading');
    let tbody = document.getElementById('utangListBody');

    loading.style.display = 'block';
    tbody.innerHTML = '';

    fetch('api_utang_aktif.php?q=' + encodeURIComponent(q))
    .then(res => res.json())
    .then(res => {
        loading.style.display = 'none';

        if (res.status !== 'success') {
            tbody.innerHTML = `
            <tr>
            <td colspan="4" class="text-center text-danger py-3">
            Gagal memuat data: ${res.message || 'Error'}
            </td>
            </tr>
            `;
            return;
        }

        document.getElementById('utangTotalCount').innerText = res.total_count;
        document.getElementById('utangGrandTotal').innerText = res.grand_format;

        if (res.data.length === 0) {
            tbody.innerHTML = `
            <tr>
            <td colspan="4" class="text-center text-muted py-4">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            Tidak ada pelanggan dengan utang aktif.
            </td>
            </tr>
            `;
            return;
        }

        let html = '';
        res.data.forEach(p => {
            html += `
            <tr>
            <td>
            <strong class="text-dark">${escapeHtml(p.nama)}</strong>
            </td>
            <td>
            ${p.no_hp ? '<a href="https://wa.me/' + p.no_hp.replace(/[^0-9]/g, '') + '" target="_blank" class="text-decoration-none text-success"><i class="bi bi-whatsapp me-1"></i>' + escapeHtml(p.no_hp) + '</a>' : '<span class="text-muted">-</span>'}
            </td>
            <td class="text-end">
            <span class="badge bg-danger fs-6 font-monospace">${p.sisa_format}</span>
            </td>
            <td class="text-center">
            <button type="button"
            class="btn btn-sm btn-success fw-bold"
            onclick="openModalBayarUtang(${p.id}, '${escapeHtml(p.nama).replace(/'/g, "\\'")}', ${p.sisa_utang})">
            <i class="bi bi-cash-coin"></i> Bayar
            </button>
            </td>
            </tr>
            `;
        });

        tbody.innerHTML = html;
    })
    .catch(err => {
        loading.style.display = 'none';
        tbody.innerHTML = `
        <tr>
        <td colspan="4" class="text-center text-danger py-3">
        Terjadi kesalahan koneksi.
        </td>
        </tr>
        `;
        console.error('Error load daftar utang:', err);
    });
}


function openModalBayarUtang(id, nama, sisaUtang) {
    document.getElementById('bayarUtangPelangganId').value = id;
    document.getElementById('bayarUtangNama').innerText = nama;
    document.getElementById('bayarUtangSisa').innerText = 'Rp ' + Math.round(sisaUtang).toLocaleString('id-ID');
    document.getElementById('bayarUtangNominal').value = '';
    document.getElementById('bayarUtangKeterangan').value = '';
    document.getElementById('alertBayarUtang').innerHTML = '';

    currentSisaUtangKasir = sisaUtang;

    let modalDaftar = bootstrap.Modal.getInstance(
        document.getElementById('modalDaftarUtang')
    );
    if (modalDaftar) modalDaftar.hide();

    let modalBayar = new bootstrap.Modal(document.getElementById('modalBayarUtang'));
    modalBayar.show();

    setTimeout(() => {
        document.getElementById('bayarUtangNominal').focus();
    }, 400);
}


function setBayarUtangNominal(val) {
    let input = document.getElementById('bayarUtangNominal');
    input.value = new Intl.NumberFormat('id-ID').format(val);
    input.focus();
}


function setBayarUtangPas() {
    let input = document.getElementById('bayarUtangNominal');
    input.value = new Intl.NumberFormat('id-ID').format(Math.round(currentSisaUtangKasir));
    input.focus();
}


function submitBayarUtang() {
    let pelangganId = document.getElementById('bayarUtangPelangganId').value;
    let nominalRaw  = document.getElementById('bayarUtangNominal').value.replace(/\./g, '');
    let nominal     = parseFloat(nominalRaw) || 0;
    let keterangan  = document.getElementById('bayarUtangKeterangan').value.trim();

    if (nominal <= 0) {
        document.getElementById('alertBayarUtang').innerHTML =
        '<div class="alert alert-danger py-2 small mb-0">Nominal harus lebih dari 0!</div>';
        return;
    }

    if (nominal > currentSisaUtangKasir) {
        document.getElementById('alertBayarUtang').innerHTML =
        '<div class="alert alert-danger py-2 small mb-0">Nominal melebihi sisa utang!</div>';
        return;
    }

    let btnSubmit = document.querySelector('#modalBayarUtang .btn-success');
    let originalHtml = btnSubmit.innerHTML;
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    let now = new Date();
    let yyyy = now.getFullYear();
    let mm = String(now.getMonth() + 1).padStart(2, '0');
    let dd = String(now.getDate()).padStart(2, '0');
    let hh = String(now.getHours()).padStart(2, '0');
    let ii = String(now.getMinutes()).padStart(2, '0');
    let ss = String(now.getSeconds()).padStart(2, '0');
    let tanggal = `${yyyy}-${mm}-${dd} ${hh}:${ii}:${ss}`;

    let formData = new FormData();
    formData.append('pelanggan_id', pelangganId);
    formData.append('nominal', nominal);
    formData.append('keterangan', keterangan);
    formData.append('tanggal', tanggal);

    fetch('api_bayar_utang.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalHtml;

        if (res.status === 'success') {
            let modalBayar = bootstrap.Modal.getInstance(
                document.getElementById('modalBayarUtang')
            );
            if (modalBayar) modalBayar.hide();

            alert(res.message);

            let modalDaftar = new bootstrap.Modal(
                document.getElementById('modalDaftarUtang')
            );
            modalDaftar.show();

            setTimeout(() => {
                loadDaftarUtang(document.getElementById('inputSearchUtang').value);
            }, 300);

        } else {
            document.getElementById('alertBayarUtang').innerHTML =
            '<div class="alert alert-danger py-2 small mb-0">' +
            (res.message || 'Gagal menyimpan') + '</div>';
        }
    })
    .catch(err => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalHtml;
        document.getElementById('alertBayarUtang').innerHTML =
        '<div class="alert alert-danger py-2 small mb-0">Terjadi kesalahan koneksi.</div>';
        console.error('Error bayar utang:', err);
    });
}
