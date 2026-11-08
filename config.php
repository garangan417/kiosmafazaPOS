<?php
// config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===================================================
// 1. FUNGSI PEMBACA FILE .ENV
// ===================================================
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$name] = $value;
        }
    }
}

// Muat file .env dari folder yang sama dengan config.php
loadEnv(__DIR__ . '/.env');

// ===================================================
// 2. BASE PATH
// ===================================================
define('BASE_PATH', __DIR__ . '/');

// ===================================================
// 3. DETEKSI BASE URL OTOMATIS
// ===================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

$docRoot   = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$basePath  = str_replace('\\', '/', realpath(__DIR__));
$relativeDir = trim(str_replace($docRoot, '', $basePath), '/');

if ($relativeDir === '') {
    $baseUrl = $protocol . $host . '/';
} else {
    $baseUrl = $protocol . $host . '/' . $relativeDir . '/';
}
define('BASE_URL', $baseUrl);

// ===================================================
// 4. KONFIGURASI DATABASE (BACA DARI .ENV)
// ===================================================
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'kios');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Inisialisasi Koneksi PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    die("Koneksi Database MariaDB Gagal: " . $e->getMessage());
}

// ===================================================
// 5. HELPER FUNGSI
// ===================================================
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka, $denganRp = true) {
        $prefix = $denganRp ? 'Rp ' : '';
        return $prefix . number_format((float)$angka, 0, ',', '.');
    }
}

// Set zona waktu PHP agar pas dengan waktu lokal
date_default_timezone_set('Asia/Makassar');
