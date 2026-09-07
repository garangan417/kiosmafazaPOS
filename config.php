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
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');               // Isi password MySQL kamu di sini
define('DB_NAME', 'kiosmafaza_db');

/**
 * Helper Fungsi Pemisah Ribuan / Format Rupiah
 */
function formatRupiah($angka, $denganRp = true) {
    $prefix = $denganRp ? 'Rp ' : '';
    return $prefix . number_format((float)$angka, 0, ',', '.');
}

// Set zona waktu PHP agar pas dengan waktu lokal Indonesia
date_default_timezone_set('Asia/Makassar');