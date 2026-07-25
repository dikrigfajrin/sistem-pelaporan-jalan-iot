<?php
session_start();

// PENGAMAN: Tolak akses jika bukan admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Akses Ditolak: Anda tidak memiliki izin untuk menghapus data.");
}

// Koneksi ke Database
require 'koneksi.php';

try {
    
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']); 
        $stmt = $pdo->prepare("DELETE FROM titik_kerusakan WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
    
    header("Location: index.php");
    exit();
    
} catch (PDOException $e) {
    die("Gagal menghapus data: " . $e->getMessage());
}
?>