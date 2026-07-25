<?php
session_start();

// Matikan Cache agar browser HP selalu mengambil halaman terbaru (Bukan hasil hafalan)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Cek apakah yang membuka halaman ini adalah admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// 1. KONEKSI KE DATABASE MYSQL
require 'koneksi.php';

try {
    $stmt = $pdo->query("SELECT * FROM titik_kerusakan ORDER BY terakhir_dideteksi DESC");
    $daftar_jalan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Memecah jumlah total berdasarkan kategori untuk Ringkasan Data
    $total_lubang = 0;
    $total_ptidur = 0;
    foreach($daftar_jalan as $jalan) {
        if ($jalan['kategori'] === 'Lubang') $total_lubang++;
        elseif ($jalan['kategori'] === 'P. Tidur') $total_ptidur++;
    }

    // --- TAMBAHAN REVISI DOSEN 2: Query Jalan Paling Rawan ---
    // Mengambil jalan dengan jumlah lubang terbanyak yang belum diperbaiki
    $stmtRawan = $pdo->query("SELECT alamat, COUNT(id) as jumlah_lubang 
                              FROM titik_kerusakan 
                              WHERE kategori = 'Lubang' 
                              AND alamat IS NOT NULL 
                              AND alamat NOT IN ('Tidak diketahui', 'Gagal mengambil alamat', 'Sedang dicari...')
                              AND status_perbaikan != 'Selesai' 
                              GROUP BY alamat 
                              ORDER BY jumlah_lubang DESC 
                              LIMIT 1");
    $jalanRawan = $stmtRawan->fetch(PDO::FETCH_ASSOC);

    // --- TAMBAHAN REVISI DOSEN 2: Query Rekapitulasi Per Jalan (Untuk Laporan Cetak) ---
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Pelaporan Kerusakan Jalan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc !important; }
        .navbar-custom {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .modern-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: rgba(255, 255, 255, 0.98);
        }
        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
        }
        .card-header-modern {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 20px 24px;
            color: #1e293b;
        }
        .btn-modern {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        #map { height: 500px; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        
        /* --- PENGATURAN KHUSUS CETAK LAPORAN (PRINT) --- */
        @media print {
            @page { size: landscape; margin: 10mm; }
            body { background: white !important; color: black !important; }
            
            /* Sembunyikan elemen yang tidak perlu saat cetak */
            .no-print, 
            .dataTables_length, 
            .dataTables_filter, 
            .dataTables_info, 
            .dataTables_paginate { 
                display: none !important; 
            }
            
            /* Tampilkan header cetak */
            .print-only { display: block !important; text-align: center; margin-bottom: 20px; }
            
            /* Rapihkan Card Container */
            .card { border: none !important; box-shadow: none !important; }
            .card-header { display: none !important; }
            .card-body { padding: 0 !important; }
            
            /* Rapihkan Tabel Laporan (Anti-Terpotong) */
            .table-responsive { overflow: visible !important; }
            .table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 0 !important; table-layout: auto !important; }
            .table th, .table td { 
                border: 1px solid #000 !important; 
                padding: 6px 8px !important; 
                font-size: 10pt !important;
                vertical-align: middle !important;
                white-space: normal !important;
                word-wrap: break-word !important;
            }
            .table th { 
                background-color: #e2e3e5 !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
                color: #000 !important;
            }
            
            /* Pastikan badge berwarna tercetak dengan baik */
            .badge { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
                border: 1px solid #666 !important; 
                color: #000 !important;
            }
            
            /* Perbaikan Pagination Tabel Utama */
            table { 
                page-break-inside: auto !important; 
                page-break-after: auto !important;
            }
            tr { 
                page-break-inside: avoid !important; 
                page-break-after: auto !important; 
            }
            thead { 
                display: table-header-group !important; 
            }
            tfoot { 
                display: table-footer-group !important; 
            }
            
        }

        /* INI PENTING: Sembunyikan elemen cetak di layar biasa */
        .print-only { display: none; }
        
        /* Tambahan Interaktif UI */
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .clickable-row:hover {
            background-color: #e2e8f0 !important;
        }
        .highlight-row {
            background-color: #dbeafe !important;
            border-left: 4px solid #3b82f6 !important;
        }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark navbar-custom mb-4 no-print py-3">
        <div class="container-fluid px-4 flex-column flex-md-row align-items-start align-items-md-center gap-3">
            <span class="navbar-brand mb-0 h4 fw-bold text-wrap lh-base" style="font-size: 1.15rem;">Sistem Pelaporan Kerusakan Jalan Berbasis IoT</span>
            <div class="d-flex align-items-center">
                <?php if($isAdmin): ?>
                    <span class="navbar-text me-3 text-white fw-medium">Halo, <?php echo htmlspecialchars($_SESSION['nama_admin']); ?> 👋</span>
                    <a href="logout.php" class="btn btn-danger btn-sm btn-modern px-3">Keluar</a>
                <?php else: ?>
                    <button onclick="window.location.href='login.php';" class="btn btn-light btn-sm btn-modern px-3 text-dark fw-bold">Login Admin</button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- TAMBAHAN REVISI DOSEN 2: BANNER PERINGATAN (MARQUEE) -->
    <?php if ($jalanRawan && $jalanRawan['jumlah_lubang'] > 0): ?>
    <div class="container-fluid px-4 mb-3 no-print">
        <div class="alert alert-danger d-flex align-items-center py-2 mb-0" style="border-radius: 8px; box-shadow: 0 4px 10px rgba(220,53,69,0.2);">
            <div class="flex-shrink-0 me-3">
                <span style="font-size: 1.5rem;">⚠️</span>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <marquee behavior="scroll" direction="left" scrollamount="5" class="fw-bold mb-0" style="font-size: 1.05rem;">
                    PERINGATAN PENGGUNA JALAN: Harap berhati-hati dan kurangi kecepatan saat melintasi <?php echo htmlspecialchars(explode(',', $jalanRawan['alamat'])[0]); ?>! Terdapat <?php echo $jalanRawan['jumlah_lubang']; ?> titik jalan berlubang yang belum diperbaiki.
                </marquee>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container-fluid px-4">

        <div class="row no-print mb-4">
            <div class="col-md-3 mb-3">
                <div class="card modern-card card-body mb-4 p-4">
                    <h6 class="card-title text-secondary fw-bold text-uppercase mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Ringkasan Data</h6>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-medium"><span style="display:inline-block; width:10px; height:10px; background:red; border-radius:50%; margin-right:5px;"></span>Jalan Berlubang</span>
                            <h4 class="fw-bold mb-0 text-dark"><?php echo $total_lubang; ?></h4>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-medium"><span style="display:inline-block; width:10px; height:10px; background:orange; border-radius:50%; margin-right:5px;"></span>Polisi Tidur</span>
                            <h4 class="fw-bold mb-0 text-dark"><?php echo $total_ptidur; ?></h4>
                        </div>
                    </div>
                </div>
                
                <div class="card modern-card card-body p-4">
                    <h6 class="card-title text-secondary fw-bold text-uppercase mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Legenda Peta</h6>
                    <div class="d-flex align-items-center mb-2">
                        <div style="width: 15px; height: 15px; background: red; border-radius: 50%; margin-right: 10px;"></div>
                        <span>Jalan Berlubang (Menunggu Perbaikan)</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div style="width: 15px; height: 15px; background: orange; border-radius: 50%; margin-right: 10px;"></div>
                        <span>Polisi Tidur (Normal / Aman)</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <div style="width: 15px; height: 15px; background: #00FF00; border-radius: 50%; margin-right: 10px;"></div>
                        <span>Lubang Sudah Diperbaiki (Aman)</span>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card mb-4 modern-card no-print">
                    <div class="card-body d-flex align-items-center p-3 gap-3">
                        <div class="d-flex align-items-center w-50">
                            <label for="filterWilayah" class="fw-bold me-2 text-secondary text-nowrap">Fokus Peta:</label>
                            <select id="filterWilayah" class="form-select" onchange="pindahLokasi()">
                                <option value="auto" selected>🎯 Otomatis (Semua Titik)</option>
                                <option value="indonesia">🌍 Seluruh Indonesia</option>
                                <option value="jabar">🗺️ Provinsi Jawa Barat</option>
                                <option value="kab_bogor">📍 Kabupaten Bogor</option>
                                <option value="kota_bogor">🏙️ Kota Bogor</option>
                                <option value="dramaga">🏡 Kecamatan Dramaga</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center w-50">
                            <label for="filterStatus" class="fw-bold me-2 text-secondary text-nowrap">Status:</label>
                            <select id="filterStatus" class="form-select" onchange="filterMarker()">
                                <option value="semua" selected>👁️ Tampilkan Semua</option>
                                <option value="menunggu">🔴 Menunggu Perbaikan</option>
                                <option value="selesai">🟢 Sudah Diperbaiki</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="map"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card modern-card mb-5">
                    <div class="card-header-modern d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Riwayat Deteksi Kerusakan Jalan</h5>
                        <?php if($isAdmin): ?>
                            <a href="cetak_laporan.php" target="_blank" class="btn btn-success btn-sm btn-modern no-print">🖨️ Cetak Laporan</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-3 table-responsive">
                        <table id="tabelRiwayat" class="table table-hover table-striped mb-0 w-100">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th><th>Kategori</th><th>Alamat</th><th>Latitude</th><th>Longitude</th>
                                    <th>Waktu Pembaruan</th>
                                    <th>Status</th>
                                    <?php if($isAdmin): ?><th class="no-print">Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($daftar_jalan as $row): 
                                    // Ambil status dari database
                                    $status_jalan = isset($row['status_perbaikan']) ? $row['status_perbaikan'] : 'Menunggu';
                                    
                                    // KOREKSI LOGIKA: Paksa status menjadi 'Normal' jika itu Polisi Tidur
                                    if ($row['kategori'] == 'P. Tidur') {
                                        $status_jalan = 'Normal';
                                    }
                                ?>
                                <tr id="row-<?php echo $row['id']; ?>" class="clickable-row" onclick="fokusKeMap(<?php echo $row['id']; ?>)" title="Klik untuk melihat posisi di peta">
                                    <td><?php echo $no++; ?></td>
                                    <td><span class="badge <?php echo ($row['kategori'] == 'Lubang') ? 'bg-danger' : 'bg-warning text-dark'; ?>"><?php echo htmlspecialchars($row['kategori']); ?></span></td>
                                    <td><?php 
                                        if (isset($row['alamat']) && !empty($row['alamat'])) {
                                            $parts = explode(',', $row['alamat']);
                                            echo htmlspecialchars(trim($parts[0]));
                                        } else {
                                            echo '<i class="text-muted">Tidak ada alamat</i>';
                                        }
                                    ?></td>
                                    <td><?php echo htmlspecialchars($row['latitude']); ?></td>
                                    <td><?php echo htmlspecialchars($row['longitude']); ?></td>
                                    <td><?php echo htmlspecialchars($row['terakhir_dideteksi']); ?></td>
                                    
                                    <td>
                                        <?php 
                                            // Tentukan warna lencana berdasarkan status
                                            $warna_badge = 'bg-secondary';
                                            if ($status_jalan == 'Selesai') $warna_badge = 'bg-success';
                                            if ($status_jalan == 'Menunggu') $warna_badge = 'bg-danger';
                                            if ($status_jalan == 'Normal') $warna_badge = 'bg-info text-dark';
                                        ?>
                                        <span class="badge <?php echo $warna_badge; ?>">
                                            <?php 
                                                // Ubah teks status agar lebih profesional
                                                if ($status_jalan == 'Menunggu') {
                                                    echo 'Menunggu Perbaikan';
                                                } elseif ($status_jalan == 'Selesai') {
                                                    echo 'Sudah Diperbaiki';
                                                } else {
                                                    echo htmlspecialchars($status_jalan); 
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    
                                    <?php if($isAdmin): ?>
                                    <td class="no-print">
                                        <?php if($status_jalan == 'Menunggu' && $row['kategori'] != 'P. Tidur'): ?>
                                            <a href="update_status.php?id=<?php echo $row['id']; ?>&status=Selesai" class="btn btn-sm btn-success mb-1" onclick="event.stopPropagation(); return confirm('Tandai jalan ini sudah diperbaiki?')">✔ Tandai Diperbaiki</a>
                                        <?php elseif($status_jalan == 'Selesai'): ?>
                                            <a href="update_status.php?id=<?php echo $row['id']; ?>&status=Menunggu" class="btn btn-sm btn-outline-secondary mb-1" onclick="event.stopPropagation();">Batal Selesai</a>
                                        <?php endif; ?>
                                        <a href="hapus.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" onclick="event.stopPropagation(); return confirm('Yakin hapus data ini?')">Hapus</a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>



            </div>
        </div>
    </div>

    <script>
        let map;
        let infoWindow;
        let semuaMarker = []; // Array untuk menyimpan semua marker peta (Untuk fitur filter)

        // Fungsi utama yang dipanggil oleh Google Maps setelah script termuat
        function initMap() {
            
            map = new google.maps.Map(document.getElementById("map"), {
                mapTypeId: "roadmap" // Bisa diganti 'satellite' atau 'hybrid'
            });

            infoWindow = new google.maps.InfoWindow();
            
            // Ambil data JSON dari PHP
            const dataJalan = <?php echo json_encode($daftar_jalan); ?>;
            const bounds = new google.maps.LatLngBounds();
            let adaTitik = false;

            // Looping pembuatan marker (menggunakan Piksel agar ukuran tetap)
            dataJalan.forEach(titik => {
                const posisi = { lat: parseFloat(titik.latitude), lng: parseFloat(titik.longitude) };
                
                // LOGIKA WARNA BERDASARKAN STATUS
                let warnaMarker = (titik.kategori === "Lubang") ? "#FF0000" : "#FFA500"; 
                let statusJalanText = titik.status_perbaikan ? titik.status_perbaikan : 'Menunggu';
                
                // KOREKSI LOGIKA POPUP PETA
                if (titik.kategori === "P. Tidur") {
                    statusJalanText = "Normal";
                } else if (statusJalanText === "Selesai") {
                    warnaMarker = "#00FF00"; // Ubah jadi Hijau khusus Lubang yang sudah diperbaiki
                    statusJalanText = "Sudah Diperbaiki";
                } else if (statusJalanText === "Menunggu") {
                    statusJalanText = "Menunggu Perbaikan";
                }

                // Membuat Penanda menggunakan Marker SVG (Ukuran mengunci di layar)
                const titikMarker = new google.maps.Marker({
                    position: posisi,
                    map: map,
                    idData: titik.id, // Menyimpan ID database ke dalam marker
                    statusKondisi: statusJalanText, // Custom property untuk mempermudah filter
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 8, // Ukuran titik dalam piksel
                        fillColor: warnaMarker,
                        fillOpacity: 0.8,
                        strokeColor: "#000000",
                        strokeWeight: 1
                    }
                });

                semuaMarker.push(titikMarker);
                bounds.extend(posisi);
                adaTitik = true;

                google.maps.event.addListener(titikMarker, "click", () => {
                    const konten = `
                        <div style="color: black;">
                            <h5 style="margin-bottom:5px;">${titik.kategori}</h5>
                            <p style="margin:0;"><b>Waktu:</b> ${titik.terakhir_dideteksi}</p>
                            <p style="margin:0;"><b>Status:</b> ${statusJalanText}</p>
                        </div>
                    `;
                    infoWindow.setContent(konten);
                    infoWindow.setPosition(posisi);
                    infoWindow.open(map);
                    
                    // Fitur Interaktif MAP -> TABEL
                    if (tabelRiwayat) {
                        // 1. Ketikkan Latitude ke dalam kotak pencarian DataTables untuk memfilter baris
                        tabelRiwayat.search(titik.latitude).draw();
                        // 2. Scroll ke bawah secara halus
                        document.getElementById('tabelRiwayat').scrollIntoView({ behavior: 'smooth', block: 'center' });
                        // 3. Highlight barisnya
                        $('.highlight-row').removeClass('highlight-row');
                        $('#row-' + titik.id).addClass('highlight-row');
                    }
                });
            });        

            // Fitur Auto-Bounds: Peta otomatis menyesuaikan zoom ke seluruh marker!
            if (adaTitik) {
                map.fitBounds(bounds);
            } else {
                // Default center jika database kosong
                map.setCenter({ lat: -6.5786, lng: 106.7354 });
                map.setZoom(14);
            }
        }

        // Fungsi Animasi Pindah Lokasi Berdasarkan Dropdown
        function pindahLokasi() {
            const wilayah = document.getElementById("filterWilayah").value;
            
            if (wilayah === "auto") {
                const bounds = new google.maps.LatLngBounds();
                semuaMarker.forEach(m => bounds.extend(m.getPosition()));
                if (semuaMarker.length > 0) map.fitBounds(bounds);
            } else if (wilayah === "indonesia") {
                map.panTo({ lat: -0.7893, lng: 113.9213 }); map.setZoom(5);
            } else if (wilayah === "jabar") {
                map.panTo({ lat: -6.9147, lng: 107.6098 }); map.setZoom(8);
            } else if (wilayah === "kab_bogor") {
                map.panTo({ lat: -6.5517, lng: 106.6291 }); map.setZoom(11);
            } else if (wilayah === "kota_bogor") {
                map.panTo({ lat: -6.5950, lng: 106.8166 }); map.setZoom(13);
            } else if (wilayah === "dramaga") {
                map.panTo({ lat: -6.5786, lng: 106.7354 }); map.setZoom(14);
            }
        }

        // Fungsi Interaktif TABEL -> MAP (Dipanggil saat baris tabel diklik)
        function fokusKeMap(idDatabase) {
            // 1. Cari marker yang sesuai di array semuaMarker
            const markerTujuan = semuaMarker.find(m => m.idData == idDatabase);
            
            if (markerTujuan) {
                // 2. Arahkan peta (Pan & Zoom) ke titik tersebut
                map.panTo(markerTujuan.getPosition());
                map.setZoom(19); // Zoom in sangat dekat
                
                // 3. Buka popup informasinya seolah-olah diklik
                google.maps.event.trigger(markerTujuan, 'click');
                
                // 4. Scroll layar perlahan ke atas (ke arah Peta)
                document.getElementById('map').scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // 5. Highlight baris tabel (Visual)
                $('.highlight-row').removeClass('highlight-row');
                $('#row-' + idDatabase).addClass('highlight-row');
            }
        }

        // Fungsi Filter Marker Berdasarkan Status
        function filterMarker() {
            const pilihan = document.getElementById("filterStatus").value;
            infoWindow.close(); // Tutup popup jika ada yang sedang terbuka

            semuaMarker.forEach(marker => {
                if (pilihan === "semua") {
                    marker.setVisible(true);
                } else if (pilihan === "menunggu") {
                    // Hanya tampilkan lubang yang menunggu perbaikan (Sembunyikan P. Tidur)
                    if (marker.statusKondisi === "Menunggu Perbaikan") {
                        marker.setVisible(true);
                    } else {
                        marker.setVisible(false);
                    }
                } else if (pilihan === "selesai") {
                    if (marker.statusKondisi === "Sudah Diperbaiki") {
                        marker.setVisible(true);
                    } else {
                        marker.setVisible(false);
                    }
                }
            });
        }
    </script>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        let tabelRiwayat;
        $(document).ready(function() {
            tabelRiwayat = $('#tabelRiwayat').DataTable({
                "pageLength": 10,
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json" 
                }
            });
        });
    </script>
        
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDuD7xelp7R7ObeAY-ZL7NRbxctfE8vORc&callback=initMap"></script>
    
</body>
</html>