<?php
// koneksi.php - Konfigurasi Database Terpusat
$host     = "127.0.0.1";
$username = "bintang";
$password = "bintang";
$dbname   = "skripsi_iot";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+07:00';");
} catch (PDOException $e) {
    die("Gagal memuat database: " . $e->getMessage());
}
?>
