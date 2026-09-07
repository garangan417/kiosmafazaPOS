let cart = [];
let scannedBarcode = '';
let isFavoritChanged = false;
let html5QrCode = null;
let currentSearchResults = [];

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

    // Load awal daftar pelanggan
    loadPelangganList();
});


// ==========================================
// HELPER / UTILITY
// ==========================================

// Helper JavaScript untuk penentuan harga grosir berdasarkan Qty
function hitungHargaTieringJS(item, qty) {
    let hargaEcer   = parseFloat(item.harga_ecer || item.harga_jual || 0);
    let hargaGrosir = parseFloat(item.harga_grosir || 0);
    let minGrosir   = parseInt(item.min_qty_grosir || 0);

    if (hargaGrosir > 0 && minGrosir > 0 && qty >= minGrosir) {
        return {
            harga_jual: hargaGrosir,
            jenis_harga: 'GROSIR'
        };
    }

    return {
        harga_jual: hargaEcer,
        jenis_harga: 'ECER'
    };
}


// Helper JavaScript untuk format ribuan live saat mengetik di input text
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

// Handler Dropdown UTANG / BON & TRANSFER
function toggleFormUtang() {
    let metode = document.getElementById('metodeBayar').value;
    let boxPelanggan = document.getElementById('boxPelangganUtang');

    if (metode === 'UTANG') {
        boxPelanggan.style.display = 'block';
    } else {
        boxPelanggan.style.display = 'none';
    }

    // Jika TRANSFER/QRIS, otomatis set Uang Pas
    if (metode === 'TRANSFER') {
        setNominalBayar('PAS');
    }
}


function loadPelangganList() {
    fetch('api_search_pelanggan.php')
        .then(res => res.json())
        .then(res => {
            let select = document.getElementById('selectPelanggan');

            select.innerHTML =
                '<option value="">-- Pilih Pelanggan --</option>';

            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(p => {
                    select.innerHTML += `
                        <option value="${p.id}">
                            ${escapeHtml(p.nama)}
                            ${p.no_hp ? '(' + escapeHtml(p.no_hp) + ')' : ''}
                        </option>
                    `;
                });
            }
        });
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
    let kategori =
        document.getElementById('jasaKategori').value;

    let keterangan =
        document.getElementById('jasaKeterangan').value.trim();

    // Clean karakter titik pemisah ribuan sebelum parsing nominal
    let rawNominal =
        document.getElementById('jasaNominal').value.replace(/\./g, '');

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

    let modalElem =
        document.getElementById('modalTransaksiJasa');

    let modalJasa =
        bootstrap.Modal.getInstance(modalElem);

    if (modalJasa) {
        modalJasa.hide();
    }

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

        const config = {
            fps: 10,
            qrbox: {
                width: 250,
                height: 150
            }
        };

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

    let modalCamElem =
        document.getElementById('modalCameraScanner');

    let modalCam =
        bootstrap.Modal.getInstance(modalCamElem);

    if (modalCam) {
        modalCam.hide();
    }

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

                document.getElementById('notFoundBarcode').innerText =
                    scannedBarcode;

                let modalNF = new bootstrap.Modal(
                    document.getElementById('modalNotFound')
                );

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
                document.getElementById('searchResult').style.display =
                    'none';
            }
        });
}


function showSearchDropdown(data) {
    currentSearchResults = data;

    let html = '';

    data.forEach((item, index) => {

        html += `
            <a href="#"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2"
               onclick="selectFromDropdown(${index}); return false;">

                <div>
                    <strong class="d-block text-dark">
                        ${escapeHtml(item.nama_barang)}
                    </strong>

                    <small class="text-muted">
                        ${escapeHtml(item.nama_kemasan)}
                        (${escapeHtml(item.satuan || '')})
                    </small>
                </div>

                <span class="badge bg-success font-monospace fs-6">
                    Rp ${Math.round(
                        item.harga_ecer ||
                        item.harga_jual ||
                        0
                    ).toLocaleString('id-ID')}
                </span>

            </a>
        `;
    });

    let sr = document.getElementById('searchResult');

    sr.innerHTML = html;
    sr.style.display = 'block';
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

        let newQty =
            cart[existingIndex].qty + 1;

        cart[existingIndex].qty = newQty;

        // Hitung ulang harga jika barang fisik
        if (!isJasa) {

            let tiering =
                hitungHargaTieringJS(
                    cart[existingIndex],
                    newQty
                );

            cart[existingIndex].harga_jual =
                tiering.harga_jual;

            cart[existingIndex].jenis_harga =
                tiering.jenis_harga;
        }

        cart[existingIndex].subtotal =
            newQty * cart[existingIndex].harga_jual;

    } else {

        let initialQty = item.qty || 1;

        let initialHarga =
            item.harga_jual ||
            item.harga_ecer ||
            0;

        let initialJenis =
            item.jenis_harga ||
            'ECER';

        if (!isJasa) {

            let tiering =
                hitungHargaTieringJS(
                    item,
                    initialQty
                );

            initialHarga =
                tiering.harga_jual;

            initialJenis =
                tiering.jenis_harga;
        }

        cart.push({

            tipe: isJasa
                ? 'JASA'
                : 'BARANG',

            kemasan_id:
                item.kemasan_id || null,

            nama_barang:
                item.nama_barang,

            nama_kemasan:
                item.nama_kemasan || '',

            satuan:
                item.satuan || 'pcs',

            harga_beli:
                item.harga_beli || 0,

            harga_ecer:
                item.harga_ecer ||
                initialHarga,

            harga_grosir:
                item.harga_grosir || 0,

            min_qty_grosir:
                item.min_qty_grosir || 0,

            harga_jual:
                initialHarga,

            jenis_harga:
                initialJenis,

            qty:
                initialQty,

            subtotal:
                item.subtotal ||
                (initialQty * initialHarga),

            is_jasa:
                isJasa,

            kategori:
                item.kategori ||
                (
                    isJasa
                        ? 'PPOB / Jasa'
                        : 'Barang Toko'
                ),

            keterangan:
                item.keterangan ||
                item.nama_barang
        });
    }

    renderCart();
}


function renderCart() {

    let tbody =
        document.getElementById('cartBody');

    let total = 0;

    tbody.innerHTML = '';

    if (cart.length === 0) {

        tbody.innerHTML = `
            <tr>
                <td colspan="5"
                    class="text-center text-muted py-5">
                    Keranjang masih kosong.
                </td>
            </tr>
        `;

        document.getElementById('btnCheckout').disabled =
            true;

    } else {

        cart.forEach((item, index) => {

            total += item.subtotal;

            tbody.innerHTML += `
                <tr>

                    <td>
                        <strong class="d-block text-dark">
                            ${escapeHtml(item.nama_barang)}
                        </strong>

                        <small class="text-muted">
                            ${escapeHtml(item.nama_kemasan)}
                        </small>
                    </td>

                    <td class="font-monospace">
                        Rp ${Math.round(
                            item.harga_jual
                        ).toLocaleString('id-ID')}
                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control form-control-sm text-center font-monospace fw-bold"
                            min="1"
                            value="${item.qty}"
                            onchange="updateQty(${index}, this.value)"
                        >

                    </td>

                    <td class="text-end font-monospace fw-bold">
                        Rp ${Math.round(
                            item.subtotal
                        ).toLocaleString('id-ID')}
                    </td>

                    <td class="text-center">

                        <button
                            class="btn btn-sm btn-outline-danger px-2 py-1"
                            onclick="removeItem(${index})"
                            title="Hapus item ini">

                            <i class="bi bi-trash"></i>

                        </button>

                    </td>

                </tr>
            `;
        });

        document.getElementById('btnCheckout').disabled =
            false;
    }

    document.getElementById('displayTotal').innerText =
        'Rp ' +
        Math.round(total).toLocaleString('id-ID');

    hitungKembalian();
}


function updateQty(index, val) {

    let qty =
        parseInt(val) || 1;

    let item =
        cart[index];

    item.qty = qty;

    // Jika bukan barang jasa/PPOB,
    // update harganya otomatis berdasarkan Qty baru
    if (!item.is_jasa) {

        let tiering =
            hitungHargaTieringJS(
                item,
                qty
            );

        item.harga_jual =
            tiering.harga_jual;

        item.jenis_harga =
            tiering.jenis_harga;
    }

    item.subtotal =
        qty * item.harga_jual;

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

    document.getElementById('inputScan').value =
        '';

    document.getElementById('searchResult').style.display =
        'none';

    resetFocusScan();
}


function resetFocusScan() {

    setTimeout(() => {
        document.getElementById('inputScan').focus();
    }, 300);
}


// ==========================================
// PEMBAYARAN
// ==========================================

function setNominalBayar(val) {

    let inputBayar =
        document.getElementById('inputBayar');

    let total =
        cart.reduce(
            (sum, item) =>
                sum + item.subtotal,
            0
        );

    if (val === 'PAS') {
        inputBayar.value = total;
    } else {
        inputBayar.value = val;
    }

    // Format ulang angka ke pemisah ribuan
    formatInputRupiahJS(inputBayar);

    hitungKembalian();
}


function hitungKembalian() {

    let total =
        cart.reduce(
            (sum, item) =>
                sum + item.subtotal,
            0
        );

    // Clean karakter titik pemisah ribuan
    // sebelum parsing uang bayar
    let rawBayar =
        document.getElementById('inputBayar')
            .value
            .replace(/\./g, '');

    let bayar =
        parseFloat(rawBayar) || 0;

    let kembalian =
        bayar - total;

    let elem =
        document.getElementById(
            'displayKembalian'
        );

    elem.innerText =
        'Rp ' +
        Math.round(kembalian)
            .toLocaleString('id-ID');

    if (kembalian < 0) {

        elem.className =
            'fw-bold fs-5 text-danger';

    } else {

        elem.className =
            'fw-bold fs-5 text-success';
    }
}


// ==========================================
// PENGELOLA BARANG FAVORIT
// ==========================================

function openKelolaFavorit() {

    document.getElementById(
        'inputSearchFavorit'
    ).value = '';

    loadFavoritList('');

    let modalFav =
        new bootstrap.Modal(
            document.getElementById(
                'modalKelolaFavorit'
            )
        );

    modalFav.show();
}


function loadFavoritList(q) {

    fetch(
        'api_favorit.php?action=search&q=' +
        encodeURIComponent(q)
    )
        .then(res => res.json())
        .then(res => {

            let tbody =
                document.getElementById(
                    'favoritListBody'
                );

            tbody.innerHTML = '';

            if (
                res.status === 'success' &&
                res.data.length > 0
            ) {

                res.data.forEach(item => {

                    let isFav =
                        parseInt(
                            item.is_favorite
                        ) === 1;

                    let btnClass =
                        isFav
                            ? 'btn-warning text-dark'
                            : 'btn-outline-secondary';

                    let iconClass =
                        isFav
                            ? 'bi-star-fill'
                            : 'bi-star';

                    let newStatus =
                        isFav ? 0 : 1;

                    tbody.innerHTML += `
                        <tr>

                            <td>
                                <strong class="text-dark">
                                    ${escapeHtml(
                                        item.nama_barang
                                    )}
                                </strong>
                            </td>

                            <td>
                                <span class="badge bg-light text-dark border">
                                    ${escapeHtml(
                                        item.nama_kemasan
                                    )}
                                    (${escapeHtml(
                                        item.satuan || ''
                                    )})
                                </span>
                            </td>

                            <td class="text-center">

                                <button
                                    type="button"
                                    class="btn btn-sm ${btnClass} fw-bold"
                                    onclick="toggleFavorit(
                                        ${item.kemasan_id},
                                        ${newStatus}
                                    )">

                                    <i class="bi ${iconClass}"></i>

                                    ${isFav
                                        ? 'Favorit'
                                        : 'Biasa'}

                                </button>

                            </td>

                        </tr>
                    `;

                });

            } else {

                tbody.innerHTML = `
                    <tr>
                        <td colspan="3"
                            class="text-center text-muted py-4">
                            Barang tidak ditemukan.
                        </td>
                    </tr>
                `;
            }
        });
}


function toggleFavorit(kemasanId, status) {

    let formData =
        new FormData();

    formData.append(
        'kemasan_id',
        kemasanId
    );

    formData.append(
        'status',
        status
    );

    fetch(
        'api_favorit.php?action=toggle',
        {
            method: 'POST',
            body: formData
        }
    )
        .then(res => res.json())
        .then(res => {

            if (res.status === 'success') {

                isFavoritChanged = true;

                let q =
                    document.getElementById(
                        'inputSearchFavorit'
                    ).value;

                loadFavoritList(q);

            } else {

                alert(
                    'Gagal mengubah favorit: ' +
                    res.message
                );
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

    window.location.href =
        '../barang/?barcode=' +
        encodeURIComponent(
            scannedBarcode
        );
}


function openAttachBarcodeModal() {

    let modalNF =
        bootstrap.Modal.getInstance(
            document.getElementById(
                'modalNotFound'
            )
        );

    if (modalNF) {
        modalNF.hide();
    }

    document.getElementById(
        'attachBarcodeVal'
    ).value = scannedBarcode;

    searchBarangTarget('');

    let modalAttach =
        new bootstrap.Modal(
            document.getElementById(
                'modalAttachBarcode'
            )
        );

    modalAttach.show();
}


function searchBarangTarget(q) {

    fetch(
        'api_search.php?q=' +
        encodeURIComponent(q)
    )
        .then(res => res.json())
        .then(res => {

            let select =
                document.getElementById(
                    'selectTargetKemasan'
                );

            select.innerHTML = '';

            if (
                res.status === 'success' &&
                res.data.length > 0
            ) {

                res.data.forEach(item => {

                    select.innerHTML += `
                        <option value="${item.kemasan_id}">
                            ${escapeHtml(item.nama_barang)}
                            -
                            ${escapeHtml(item.nama_kemasan)}
                            (Rp ${Math.round(
                                item.harga_ecer
                            ).toLocaleString('id-ID')})
                        </option>
                    `;

                });

            } else {

                select.innerHTML =
                    '<option disabled>Tidak ada barang ditemukan...</option>';
            }
        });
}


function simpanAttachBarcode() {

    let kemasanId =
        document.getElementById(
            'selectTargetKemasan'
        ).value;

    let barcode =
        document.getElementById(
            'attachBarcodeVal'
        ).value;

    if (!kemasanId) {

        alert(
            'Pilih barang target terlebih dahulu!'
        );

        return;
    }

    fetch(
        'api_attach_barcode.php',
        {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json'
            },

            body: JSON.stringify({
                barang_kemasan_id:
                    kemasanId,

                barcode:
                    barcode
            })
        }
    )
        .then(res => res.json())
        .then(res => {

            if (res.status === 'success') {

                alert(
                    'Barcode berhasil dihubungkan!'
                );

                let modalAttach =
                    bootstrap.Modal.getInstance(
                        document.getElementById(
                            'modalAttachBarcode'
                        )
                    );

                if (modalAttach) {
                    modalAttach.hide();
                }

                processSearch(barcode);

            } else {

                alert(
                    'Gagal: ' +
                    res.message
                );
            }
        });
}


// ==========================================
// CHECKOUT
// ==========================================

function prosesCheckout() {

    if (cart.length === 0) {

        alert(
            'Keranjang belanja masih kosong!'
        );

        return;
    }

    let total =
        cart.reduce(
            (sum, item) =>
                sum + item.subtotal,
            0
        );

    // Clean karakter titik pemisah ribuan
    // sebelum dikirim ke server/checkout
    let rawBayar =
        document.getElementById(
            'inputBayar'
        ).value.replace(/\./g, '');

    let bayar =
        parseFloat(rawBayar) || 0;

    let metode =
        document.getElementById(
            'metodeBayar'
        ).value;

    let pelangganId =
        document.getElementById(
            'selectPelanggan'
        ).value;


    // Validasi Metode TUNAI
    if (
        metode === 'TUNAI' &&
        bayar < total
    ) {

        alert(
            'Uang pembayaran masih kurang!'
        );

        return;
    }


    // Validasi Metode UTANG
    if (
        metode === 'UTANG' &&
        !pelangganId
    ) {

        alert(
            'Silakan pilih Pelanggan terlebih dahulu untuk transaksi UTANG / BON!'
        );

        return;
    }


    let btnCheckout =
        document.getElementById(
            'btnCheckout'
        );

    btnCheckout.disabled = true;

    btnCheckout.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2"></span>
        Menyimpan Transaksi...
    `;


    let kembalian =
        bayar - total;


    // ==========================================
    // TANGGAL & WAKTU
    // ==========================================

    let today =
        new Date();

    let yyyy =
        today.getFullYear();

    let mm =
        String(
            today.getMonth() + 1
        ).padStart(2, '0');

    let dd =
        String(
            today.getDate()
        ).padStart(2, '0');

    let hh =
        String(
            today.getHours()
        ).padStart(2, '0');

    let ii =
        String(
            today.getMinutes()
        ).padStart(2, '0');

    let ss =
        String(
            today.getSeconds()
        ).padStart(2, '0');


    let tglForm =
        `${yyyy}-${mm}-${dd}`;

    let datetimeFull =
        `${yyyy}-${mm}-${dd} ${hh}:${ii}:${ss}`;


    // ==========================================
    // PISAH BARANG FISIK & JASA/PPOB
    // ==========================================

    let itemsBarang =
        cart.filter(
            item => !item.is_jasa
        );

    let itemsJasa =
        cart.filter(
            item => item.is_jasa
        );


    // ==========================================
    // PAYLOAD CHECKOUT
    // ==========================================

    let payload = {

        tanggal:
            tglForm,

        created_at:
            datetimeFull,

        total_kotor:
            total,

        diskon:
            0,

        total_bersih:
            total,

        bayar:
            bayar,

        kembalian:
            kembalian > 0
                ? kembalian
                : 0,

        metode_bayar:
            metode,

        pelanggan_id:
            pelangganId || 0,

        catatan:
            '',

        // Tetap dikirim untuk kompatibilitas
        items:
            cart,

        // Barang fisik toko
        // untuk pemotongan stok & laporan fisik
        items_barang:
            itemsBarang,

        // Layanan PPOB/Jasa
        // untuk laporan PPOB/Jasa
        items_jasa:
            itemsJasa
    };


    // ==========================================
    // KIRIM CHECKOUT KE SERVER
    // ==========================================

    fetch(
        'api_checkout.php',
        {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json'
            },

            body:
                JSON.stringify(payload)
        }
    )
        .then(res => res.json())

        .then(res => {

            btnCheckout.disabled =
                false;

            btnCheckout.innerHTML = `
                <i class="bi bi-printer me-2"></i>
                SIMPAN & PROSES
            `;


            // ==========================================
            // CHECKOUT BERHASIL
            // ==========================================

            if (res.status === true) {

                let now =
                    new Date();

                let tglStr =
                    now.toLocaleDateString(
                        'id-ID'
                    ) +
                    ' ' +
                    now.toLocaleTimeString(
                        'id-ID',
                        {
                            hour: '2-digit',
                            minute: '2-digit'
                        }
                    );


                // ==========================================
                // DATA STRUK
                // ==========================================

                document.getElementById(
                    'receiptNota'
                ).innerText =
                    res.no_faktur ||
                    res.no_nota ||
                    'TRX-' + Date.now();


                document.getElementById(
                    'receiptTanggal'
                ).innerText =
                    tglStr;


                document.getElementById(
                    'receiptMetode'
                ).innerText =
                    metode;


                // ==========================================
                // PELANGGAN UTANG
                // ==========================================

                if (
                    metode === 'UTANG' &&
                    res.nama_pelanggan
                ) {

                    document.getElementById(
                        'receiptPelangganBox'
                    ).style.display =
                        'block';

                    document.getElementById(
                        'receiptPelanggan'
                    ).innerText =
                        res.nama_pelanggan;

                } else {

                    document.getElementById(
                        'receiptPelangganBox'
                    ).style.display =
                        'none';
                }


                // ==========================================
                // ITEM STRUK
                // ==========================================

                let itemsHtml = '';

                cart.forEach(item => {

                    itemsHtml += `
                        <tr>
                            <td colspan="2">
                                <strong>
                                    ${escapeHtml(
                                        item.nama_barang
                                    )}
                                </strong>

                                (${escapeHtml(
                                    item.nama_kemasan
                                )})
                            </td>
                        </tr>

                        <tr>
                            <td>
                                ${item.qty}
                                x
                                ${Math.round(
                                    item.harga_jual
                                ).toLocaleString('id-ID')}
                            </td>

                            <td class="text-end">
                                Rp ${Math.round(
                                    item.subtotal
                                ).toLocaleString('id-ID')}
                            </td>
                        </tr>
                    `;
                });


                document.getElementById(
                    'receiptItems'
                ).innerHTML =
                    itemsHtml;


                // ==========================================
                // TOTAL STRUK
                // ==========================================

                document.getElementById(
                    'receiptTotal'
                ).innerText =
                    'Rp ' +
                    Math.round(total)
                        .toLocaleString('id-ID');


                document.getElementById(
                    'receiptBayar'
                ).innerText =
                    'Rp ' +
                    Math.round(bayar)
                        .toLocaleString('id-ID');


                document.getElementById(
                    'receiptKembalian'
                ).innerText =
                    'Rp ' +
                    Math.round(
                        kembalian > 0
                            ? kembalian
                            : 0
                    ).toLocaleString('id-ID');


                // ==========================================
                // TAMPILKAN MODAL STRUK
                // ==========================================

                let modalStruk =
                    new bootstrap.Modal(
                        document.getElementById(
                            'modalStruk'
                        )
                    );

                modalStruk.show();


                // ==========================================
                // RESET TRANSAKSI
                // ==========================================

                clearCart();

                document.getElementById(
                    'inputBayar'
                ).value = '';

                document.getElementById(
                    'displayKembalian'
                ).innerText =
                    'Rp 0';

                document.getElementById(
                    'metodeBayar'
                ).value =
                    'TUNAI';

                toggleFormUtang();


            } else {

                alert(
                    'Gagal menyimpan transaksi: ' +
                    (
                        res.message ||
                        'Terjadi kesalahan pada server.'
                    )
                );
            }
        })

        .catch(err => {

            btnCheckout.disabled =
                false;

            btnCheckout.innerHTML = `
                <i class="bi bi-printer me-2"></i>
                SIMPAN & PROSES
            `;

            alert(
                'Terjadi kesalahan koneksi atau server!'
            );

            console.error(
                'Checkout error:',
                err
            );
        });
}