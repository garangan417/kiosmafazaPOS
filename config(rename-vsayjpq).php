<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. BASE PATH (Folder Fisik Server)
define('BASE_PATH', __DIR__ . '/');

// 2. DETEKSI PROTOKOL & HOST
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'];

// 3. DETEKSI BASE URL OTOMATIS
$docRoot   = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$basePath  = str_replace('\\', '/', realpath(__DIR__));
$relativeDir = trim(str_replace($docRoot, '', $basePath), '/');

if ($relativeDir === '') {
    $baseUrl = $protocol . $host . '/';
} else {
    $baseUrl = $protocol . $host . '/' . $relativeDir . '/';
}

define('BASE_URL', $baseUrl);

// ===================================================
// 4. KONFIGURASI DATABASE MARIADB / MYSQL
// ===================================================
// 1. Pengaturan Database MariaDB
define('DB_HOST', '127.0.0.1');     // atau 'localhost'
define('DB_PORT', '3306');          // Port default MariaDB/MySQL
define('DB_NAME', 'mafaza');   // Sesuaikan dengan nama database kamu
define('DB_USER', 'mafaza');          // User database
define('DB_PASS', '1234');              // Password database kamu

// 2. Inisialisasi Koneksi PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Variabel $pdo dibuat di scope global
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Tampilkan pesan error jika koneksi gagal
    die("Koneksi Database MariaDB Gagal: " . $e->getMessage());
}
/**
 * Helper Fungsi Pemisah Ribuan / Format Rupiah
 */
function formatRupiah($angka, $denganRp = true) {
    $prefix = $denganRp ? 'Rp ' : '';
    return $prefix . number_format((float)$angka, 0, ',', '.');
}

// Set zona waktu PHP agar pas dengan waktu lokal Indonesia
date_default_timezone_set('Asia/Makassar');