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
// Mengambil direktori tempat config.php berada relatif terhadap Document Root
$docRoot   = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$basePath  = str_replace('\\', '/', realpath(__DIR__));
$relativeDir = trim(str_replace($docRoot, '', $basePath), '/');

if ($relativeDir === '') {
    // Jika project berada di root domain /var/www/html/ -> BASE_URL = http://192.168.10.90/
    $baseUrl = $protocol . $host . '/';
} else {
    // Jika project di subfolder /var/www/html/project/ -> BASE_URL = http://192.168.10.90/project/
    $baseUrl = $protocol . $host . '/' . $relativeDir . '/';
}

define('BASE_URL', $baseUrl);

/**
 * Helper Fungsi Pemisah Ribuan / Format Rupiah
 */
function formatRupiah($angka, $denganRp = true) {
    $prefix = $denganRp ? 'Rp ' : '';
    return $prefix . number_format((float)$angka, 0, ',', '.');
}

// Set zona waktu PHP agar pas dengan waktu lokal Indonesia
date_default_timezone_set('Asia/Makassar'); // Gunakan 'Asia/Makassar' untuk WITA atau 'Asia/Jayapura' untuk WIT