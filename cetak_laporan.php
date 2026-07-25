<?php
session_start();

// Pastikan hanya admin yang bisa mencetak
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Akses ditolak. Anda harus login sebagai admin.");
}

require 'koneksi.php';

try {
    // Ambil data untuk menghitung total keseluruhan
    $stmt = $pdo->query("SELECT kategori FROM titik_kerusakan");
    $daftar_jalan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_lubang = 0;
    $total_ptidur = 0;
    foreach($daftar_jalan as $jalan) {
        if ($jalan['kategori'] === 'Lubang') $total_lubang++;
        elseif ($jalan['kategori'] === 'P. Tidur') $total_ptidur++;
    }

    // Ambil data rekapitulasi per jalan
    $stmtRekap = $pdo->query("SELECT alamat, 
                                     SUM(CASE WHEN kategori = 'Lubang' THEN 1 ELSE 0 END) as total_lubang,
                                     SUM(CASE WHEN kategori = 'P. Tidur' THEN 1 ELSE 0 END) as total_ptidur
                              FROM titik_kerusakan 
                              WHERE alamat IS NOT NULL 
                              AND alamat NOT IN ('Tidak diketahui', 'Gagal mengambil alamat', 'Sedang dicari...')
                              GROUP BY alamat 
                              ORDER BY total_lubang DESC, total_ptidur DESC");
    $rekapJalan = $stmtRekap->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Gagal memuat database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Kerusakan Jalan</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; margin: 30px; color: black; background: white; }
        .text-center { text-align: center; }
        h4 { margin-bottom: 5px; text-decoration: underline; font-size: 16pt; }
        h5 { margin-bottom: 10px; font-size: 12pt; }
        p { margin-top: 0; margin-bottom: 10px; font-size: 12pt; }
        ul { margin-top: 5px; margin-bottom: 15px; font-size: 12pt; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11pt; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; vertical-align: middle; }
        th { background-color: #e2e3e5; text-align: center; font-weight: bold; }
        
        .text-danger { color: red; }
        .fw-bold { font-weight: bold; }
        
        /* Tanda Tangan */
        .ttd-container { width: 100%; text-align: right; margin-top: 50px; }
        .ttd-box { display: inline-block; text-align: center; width: 300px; }
        
        /* Kunci Anti Terpotong di Browser */
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
    </style>
</head>
<body onload="window.print()">

    <div class="text-center" style="margin-bottom: 20px;">
        <h4>LAPORAN REKAPITULASI KONDISI JALAN</h4>
        <p>Tanggal Cetak: <?php echo date('d-m-Y H:i:s'); ?></p>
    </div>
    
    <div style="margin-bottom: 20px;">
        <p><strong>Keterangan Ringkas:</strong></p>
        <ul>
            <li>Total Keseluruhan Jalan Berlubang: <?php echo $total_lubang; ?> Titik</li>
        </ul>
    </div>
    
    <div>
        <h5>Ringkasan Kerusakan Berdasarkan Nama Jalan</h5>
        <table>
            <thead>
                <tr>
                    <th style="width: 70%;">Nama Jalan / Wilayah</th>
                    <th class="text-center">Jumlah Lubang</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rekapJalan as $rekap): ?>
                    <?php 
                        if($rekap['total_lubang'] == 0) continue; 
                        $nama_jalan_rekap = htmlspecialchars(explode(',', $rekap['alamat'])[0]);
                    ?>
                    <tr>
                        <td><?php echo $nama_jalan_rekap; ?></td>
                        <td class="text-center">
                            <span class="text-danger fw-bold"><?php echo $rekap['total_lubang']; ?> Titik</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>



</body>
</html>
