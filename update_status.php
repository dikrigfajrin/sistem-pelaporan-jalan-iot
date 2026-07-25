<?php
session_start();

// Pengaman: Hanya admin yang boleh mengubah status
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Akses Ditolak: Anda tidak memiliki izin.");
}

require 'koneksi.php';

try {
    
    if (isset($_GET['id']) && isset($_GET['status'])) {
        $id = intval($_GET['id']);
        $status_baru = $_GET['status']; // 'Menunggu' atau 'Selesai'
        
        $stmt = $pdo->prepare("UPDATE titik_kerusakan SET status_perbaikan = :status WHERE id = :id");
        $stmt->execute(['status' => $status_baru, 'id' => $id]);
    }
    
    header("Location: index.php");
    exit();
    
} catch (PDOException $e) {
    die("Gagal mengubah status: " . $e->getMessage());
}
?>