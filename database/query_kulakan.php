<?php
// database/query_kulakan.php
require_once __DIR__ . '/db_barang.php';
require_once __DIR__ . '/query_stok_v2.php';
require_once __DIR__ . '/query_harga.php';

// ==========================================
// 1. KERANJANG
// ==========================================
function initKeranjangKulakan(): void {
    if (!isset($_SESSION['kulakan_cart'])) $_SESSION['kulakan_cart'] = [];
}

function tambahItemKulakan(int $kemasanId, int $qty = 1, string $satuanPilihan = 'KEMASAN'): void {
    initKeranjangKulakan();
    $key = $kemasanId . '_' . $satuanPilihan;
    if (isset($_SESSION['kulakan_cart'][$key])) {
        $_SESSION['kulakan_cart'][$key]['qty'] += $qty;
    } else {
        $_SESSION['kulakan_cart'][$key] = ['kemasan_id' => $kemasanId, 'qty' => $qty, 'satuan' => $satuanPilihan];
    }
}

function updateQtyKulakan(string $key, int $qty): void {
    initKeranjangKulakan();
    if ($qty <= 0) unset($_SESSION['kulakan_cart'][$key]);
    else $_SESSION['kulakan_cart'][$key]['qty'] = $qty;
}

function hapusItemKulakan(string $key): void {
    initKeranjangKulakan();
    unset($_SESSION['kulakan_cart'][$key]);
}

function clearKeranjangKulakan(): void {
    $_SESSION['kulakan_cart'] = [];
}

function getKeranjangKulakanDetail(PDO $pdo): array {
    initKeranjangKulakan();
    if (empty($_SESSION['kulakan_cart'])) return ['items' => [], 'total_modal' => 0];

    $items = []; $totalModal = 0;
    
    foreach ($_SESSION['kulakan_cart'] as $key => $cartItem) {
        $kemasanId = $cartItem['kemasan_id'];
        $qty = $cartItem['qty'];
        $satuanPilihan = $cartItem['satuan'];

        $stmt = $pdo->prepare("
            SELECT bk.id AS kemasan_id, b.id AS barang_id, b.nama_barang,
                   k.nama_kategori, bk.nama_kemasan, bk.satuan,
                   COALESCE(bk.isi,1) AS isi,
                   COALESCE(h.harga_beli,0) AS harga_beli
            FROM barang_kemasan bk
            JOIN barang b ON bk.barang_id = b.id
            JOIN kategori k ON b.kategori_id = k.id
            LEFT JOIN harga_barang h ON bk.id = h.barang_kemasan_id
            WHERE bk.id = ?
        ");
        $stmt->execute([$kemasanId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $isi = max(1, (int) $row['isi']);
            $hargaKemasan = (float) $row['harga_beli'];
            
            if ($satuanPilihan === 'BASE') {
                $hargaTampil = $hargaKemasan / $isi;
                $row['harga_beli_tampil'] = $hargaTampil;
                $row['satuan_tampil'] = $row['satuan'];
            } else {
                $hargaTampil = $hargaKemasan;
                $row['harga_beli_tampil'] = $hargaTampil;
                $row['satuan_tampil'] = $row['nama_kemasan'];
            }

            $subtotal = $qty * $hargaTampil;
            $row['key'] = $key;
            $row['qty'] = $qty;
            $row['satuan_pilihan'] = $satuanPilihan;
            $row['subtotal_modal'] = $subtotal;
            $items[] = $row;
            $totalModal += $subtotal;
        }
    }
    return ['items' => $items, 'total_modal' => $totalModal];
}

// ==========================================
// 2. SIMPAN NOTA
// ==========================================
function simpanNotaKulakan(PDO $pdo, string $nama, string $supplier, string $catatan): array {
    $cart = getKeranjangKulakanDetail($pdo);
    if (empty($cart['items'])) return ['ok' => false, 'error' => 'Keranjang kosong'];

    try {
        $pdo->beginTransaction();
        $noKulakan = 'NOTA-' . date('Ymd-His') . '-' . rand(10, 99);
        
        $stmt = $pdo->prepare("INSERT INTO kulakan (no_kulakan, nama_kulakan, supplier, total_modal, catatan, status) VALUES (?, ?, ?, ?, ?, 'BELUM_DATANG')");
        $stmt->execute([$noKulakan, $nama, $supplier, $cart['total_modal'], $catatan]);
        $kulakanId = (int) $pdo->lastInsertId();

        $stmtD = $pdo->prepare("INSERT INTO kulakan_detail (kulakan_id, barang_kemasan_id, nama_barang, nama_kemasan, satuan, isi, qty, harga_beli, subtotal_modal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($cart['items'] as $it) {
            // TENTUKAN TEKS SATUAN YANG AKAN DISIMPAN
            if ($it['satuan_pilihan'] === 'BASE') {
                $satuanSimpan = $it['satuan']; // "BUNGKUS"
                $qtySimpan = $it['qty'];
                $hargaSimpan = $it['harga_beli_tampil']; // Rp 25.900
            } else {
                $satuanSimpan = $it['nama_kemasan']; // "PAK"
                $qtySimpan = $it['qty'];
                $hargaSimpan = $it['harga_beli_tampil']; // Rp 259.000
            }

            $stmtD->execute([
                $kulakanId, 
                $it['kemasan_id'], 
                $it['nama_barang'], 
                $it['nama_kemasan'], 
                $satuanSimpan, // Simpan teks murni di sini!
                $it['isi'], 
                $qtySimpan, 
                $hargaSimpan, 
                $it['subtotal_modal']
            ]);
        }
        $pdo->commit();
        clearKeranjangKulakan();
        return ['ok' => true, 'id' => $kulakanId, 'no' => $noKulakan];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ==========================================
// 3. INPUT HASIL (Konversi hanya saat masuk stok)
// ==========================================
function inputHasilBelanjaKeStok(PDO $pdo, int $kulakanId, array $qtyAktual, array $hargaAktual, array $itemDihapus = [], array $satuanAktual = []): array {
    $nota = getKulakanById($pdo, $kulakanId);
    if (!$nota || $nota['status'] !== 'BELUM_DATANG') return ['ok' => false, 'error' => 'Nota tidak valid atau sudah diinput.'];

    $items = getKulakanDetailItems($pdo, $kulakanId);
    
    try {
        $pdo->beginTransaction();
        $totalModalAktual = 0;
        
        foreach ($items as $it) {
            $idDetail = $it['id'];
            
            if (in_array($idDetail, $itemDihapus)) {
                $stmtDel = $pdo->prepare("DELETE FROM kulakan_detail WHERE id = ?");
                $stmtDel->execute([$idDetail]);
                continue;
            }

            $qty = (int) ($qtyAktual[$idDetail] ?? 0); // Angka Bulat
            $hargaBaru = (float) ($hargaAktual[$idDetail] ?? 0);
            $satuanPilihan = $satuanAktual[$idDetail] ?? 'KEMASAN'; 
            
            if ($qty <= 0) {
                $stmtDel = $pdo->prepare("DELETE FROM kulakan_detail WHERE id = ?");
                $stmtDel->execute([$idDetail]);
                continue; 
            }

            $isiKemasan = max(1, (int) $it['isi']);
            
            if ($satuanPilihan === 'BASE') {
                // Input BUNGKUS
                $qtyMasuk = $qty; 
                $hargaBeliKemasanBaru = $hargaBaru * $isiKemasan; 
            } else {
                // Input PAK
                $qtyMasuk = $qty * $isiKemasan; 
                $hargaBeliKemasanBaru = $hargaBaru; 
            }

            $hargaPerPcs = $hargaBeliKemasanBaru / $isiKemasan;
            $subtotal = $qtyMasuk * $hargaPerPcs;
            $totalModalAktual += $subtotal;

            // 1. Update harga beli master
            if ($hargaBeliKemasanBaru > 0) {
                $stmtHj = $pdo->prepare("SELECT harga_jual_ecer FROM harga_barang WHERE barang_kemasan_id = ?");
                $stmtHj->execute([$it['barang_kemasan_id']]);
                $hargaJualLama = (float) $stmtHj->fetchColumn();
                saveOrUpdateHarga($pdo, $it['barang_kemasan_id'], $hargaBeliKemasanBaru, $hargaJualLama, 0, 1);
            }
            
            // 2. Masukkan ke Stok
            $stmtS = $pdo->prepare("SELECT stok_base FROM barang_stok WHERE barang_id = ? FOR UPDATE");
            $stmtS->execute([$it['barang_id']]);
            $stokSebelum = (int) $stmtS->fetchColumn();
            $stokSesudah = $stokSebelum + $qtyMasuk;

            $pdo->prepare("UPDATE barang_stok SET stok_base = ? WHERE barang_id = ?")->execute([$stokSesudah, $it['barang_id']]);
            
            // PERBAIKAN FINAL: Masukkan barang_id ke dalam query INSERT
            $pdo->prepare("
                INSERT INTO stok_mutasi 
                (barang_id, barang_kemasan_id, jenis_mutasi, qty_base, qty_kemasan, isi_saat_itu, satuan_saat_itu, stok_sebelum, stok_sesudah, ref_type, ref_id, keterangan) 
                VALUES (?, ?, 'MASUK_KULAKAN', ?, ?, ?, ?, ?, ?, 'KULAKAN', ?, ?)
            ")->execute([
                $it['barang_id'],           // <-- INI DITAMBAHKAN
                $it['barang_kemasan_id'], 
                $qtyMasuk, 
                $qty, 
                $isiKemasan, 
                $it['satuan'], 
                $stokSebelum, 
                $stokSesudah, 
                $kulakanId, 
                "Nota {$nota['no_kulakan']}"
            ]);

            // 3. Update detail nota
            $stmtU = $pdo->prepare("UPDATE kulakan_detail SET qty_realisasi = ?, harga_beli_aktual = ?, subtotal_modal = ? WHERE id = ?");
            $stmtU->execute([$qty, $hargaBeliKemasanBaru, $subtotal, $idDetail]);
        }
        
        $stmtH = $pdo->prepare("UPDATE kulakan SET status = 'SELESAI', total_modal = ?, realisasi_at = NOW() WHERE id = ?");
        $stmtH->execute([$totalModalAktual, $kulakanId]);

        $pdo->commit();
        return ['ok' => true];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Database Error: ' . $e->getMessage()];
    }
}

function getKulakanById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM kulakan WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getKulakanDetailItems(PDO $pdo, int $kulakanId): array {
    $stmt = $pdo->prepare("
        SELECT 
            kd.*, 
            bk.barang_id,
            bk.nama_kemasan AS nama_kemasan_master, 
            bk.satuan AS satuan_master,
            bk.isi AS isi_master
        FROM kulakan_detail kd 
        JOIN barang_kemasan bk ON kd.barang_kemasan_id = bk.id 
        WHERE kd.kulakan_id = ? 
        ORDER BY kd.id ASC
    ");
    $stmt->execute([$kulakanId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
