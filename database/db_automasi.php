<?php
// database/db_automasi.php
// Koneksi khusus untuk Database Automasi (STB Python)

$host_auto = 'localhost';
$db_auto   = 'automation_db'; // Nama database yang kita buat di STB
$user_auto = 'automation';   // User yang kita buat di STB
$pass_auto = '1234'; // GANTI dengan password asli MariaDB kamu!

try {
    $pdo_automasi = new PDO("mysql:host=$host_auto;dbname=$db_auto;charset=utf8mb4", $user_auto, $pass_auto);
    // Set error mode ke exception
    $pdo_automasi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode ke array associative
    $pdo_automasi->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Kalau gagal konek, tampilkan pesan (hati-hati jangan tampilkan di production asli)
    die("Koneksi ke Database Automasi Gagal: " . $e->getMessage());
}
?>