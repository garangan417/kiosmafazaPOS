<?php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_automasi.php';

echo "<h3>1. Tes Koneksi Database</h3>";
echo "✅ Koneksi berhasil tersambung ke database: <b>" . $db_auto . "</b><br><br>";

echo "<h3>2. Tes Ambil Semua Data (Tanpa Filter)</h3>";
try {
    $stmt = $pdo_automasi->query("SELECT * FROM transaksi_ppob");
    $data = $stmt->fetchAll();
    
    if (empty($data)) {
        echo "❌ Tabel kosong (tidak ada data sama sekali di tabel transaksi_ppob).<br>";
    } else {
        echo "✅ Ketemu " . count($data) . " baris data di tabel!<br>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "❌ Error SQL: " . $e->getMessage();
}

echo "<h3>3. Tes Query Pakai Filter Tanggal</h3>";
$tgl = date('Y-m-d'); // Hari ini
$start = date('Y-m-d 00:00:00');
$end = date('Y-m-d 23:59:59');

echo "Waktu server PHP sekarang: <b>" . $tgl . "</b><br>";
echo "Mencari data antara <b>$start</b> sampai <b>$end</b><br><br>";

try {
    $stmt2 = $pdo_automasi->prepare("SELECT * FROM transaksi_ppob WHERE created_at >= ? AND created_at <= ?");
    $stmt2->execute([$start, $end]);
    $data2 = $stmt2->fetchAll();
    
    if (empty($data2)) {
        echo "❌ Kosong. Berarti masalahnya di Filter Tanggal (Timezone PHP beda dengan MariaDB).<br>";
    } else {
        echo "✅ Ketemu " . count($data2) . " baris data dengan filter hari ini!<br>";
    }
} catch (Exception $e) {
    echo "❌ Error SQL: " . $e->getMessage();
}
?>