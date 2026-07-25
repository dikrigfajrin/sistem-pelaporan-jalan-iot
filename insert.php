<?php
// 1. KONEKSI KE DATABASE MYSQL
require 'koneksi.php';

// 2. TANGKAP DATA DARI ESP32 (HTTP POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $latitude  = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
    $kategori  = isset($_POST['kategori']) ? $_POST['kategori'] : '';

    // Validasi Dasar: Pastikan data koordinat dan kategori tidak kosong atau nol
    if ($latitude != 0 && $longitude != 0 && !empty($kategori)) {
        
        // --- TAMBAHAN: Mendapatkan Alamat (Reverse Geocoding OpenStreetMap) ---
        $alamat = "Sedang dicari...";
        try {
            $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=" . $latitude . "&lon=" . $longitude;
            $options = [
                "http" => [
                    "header" => "User-Agent: SkripsiIoT/1.0\r\n"
                ]
            ];
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['address'])) {
                    if (isset($data['address']['road'])) {
                        $alamat = $data['address']['road'];
                    } elseif (isset($data['address']['residential'])) {
                        $alamat = $data['address']['residential'];
                    } elseif (isset($data['address']['path'])) {
                        $alamat = $data['address']['path'];
                    } elseif (isset($data['address']['neighbourhood'])) {
                        $alamat = $data['address']['neighbourhood'];
                    } elseif (isset($data['address']['hamlet'])) {
                        $alamat = $data['address']['hamlet'];
                    } elseif (isset($data['address']['village'])) {
                        $alamat = $data['address']['village'];
                    } elseif (isset($data['address']['suburb'])) {
                        $alamat = $data['address']['suburb'];
                    } elseif (isset($data['display_name'])) {
                        $parts = explode(',', $data['display_name']);
                        $alamat = trim($parts[0]);
                    }
                } elseif (isset($data['display_name'])) {
                    $parts = explode(',', $data['display_name']);
                    $alamat = trim($parts[0]);
                }
            }
        } catch (Exception $e) {
            $alamat = "Gagal mengambil alamat";
        }
        // ----------------------------------------------------------------------

        try {
            
            // 3. IMPLEMENTASI ALGORITMA HAVERSINE (Radius Clustering)
            // Angka 6371000 adalah jari-jari bumi dalam satuan meter.
            // Query ini mencari apakah sudah ada titik dengan kategori yang sama dalam radius < 15 meter
            // dan mengabaikan lubang yang sudah berstatus 'Selesai' diperbaiki.
            $sql_cek = "SELECT id, (6371000 * acos(cos(radians(:lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians(:lon)) + sin(radians(:lat)) * sin(radians(latitude)))) AS jarak 
                        FROM titik_kerusakan 
                        WHERE kategori = :kategori AND status_perbaikan != 'Selesai'
                        HAVING jarak < 15 
                        ORDER BY jarak ASC 
                        LIMIT 1";

            $stmt_cek = $pdo->prepare($sql_cek);
            $stmt_cek->execute([
                'lat'      => $latitude,
                'lon'      => $longitude,
                'kategori' => $kategori
            ]);
            
            $titik_ada = $stmt_cek->fetch(PDO::FETCH_ASSOC);

            if ($titik_ada) {
                // === SKENARIO A: TITIK SUDAH ADA DALAM RADIUS 15 METER ===
                $id_terdekat = $titik_ada['id'];
                
                // TAMBAHAN REVISI DOSEN 2: Algoritma Titik Tengah Dinamis (Center of Gravity)
                // Mengambil nilai koordinat sebelumnya
                $stmt_lama = $pdo->prepare("SELECT latitude, longitude, total_terdeteksi FROM titik_kerusakan WHERE id = :id");
                $stmt_lama->execute(['id' => $id_terdekat]);
                $data_lama = $stmt_lama->fetch(PDO::FETCH_ASSOC);
                
                $lat_lama = floatval($data_lama['latitude']);
                $lon_lama = floatval($data_lama['longitude']);
                $total_lama = intval($data_lama['total_terdeteksi']);
                
                // Menghitung Rata-rata Gabungan (Weighted Average)
                $lat_baru = (($lat_lama * $total_lama) + $latitude) / ($total_lama + 1);
                $lon_baru = (($lon_lama * $total_lama) + $longitude) / ($total_lama + 1);

                $sql_update = "UPDATE titik_kerusakan 
                               SET total_terdeteksi = total_terdeteksi + 1, 
                                   latitude = :lat_baru,
                                   longitude = :lon_baru,
                                   terakhir_dideteksi = NOW() 
                               WHERE id = :id";
                
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    'lat_baru' => $lat_baru,
                    'lon_baru' => $lon_baru,
                    'id'       => $id_terdekat
                ]);
                
                echo "Berhasil diupdate (Radius Kluster). Jarak penanda terdekat: " . round($titik_ada['jarak'], 2) . " meter.";
            
            } else {
                // === SKENARIO B: TITIK BARU BENAR-BENAR DITEMUKAN ===
                // Menambahkan baris data baru ke dalam database
                $sql_insert = "INSERT INTO titik_kerusakan (latitude, longitude, kategori, total_terdeteksi, terakhir_dideteksi, status_perbaikan, alamat) 
                               VALUES (:lat, :lon, :kategori, 1, NOW(), 'Menunggu', :alamat)";
                
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    'lat'      => $latitude,
                    'lon'      => $longitude,
                    'kategori' => $kategori,
                    'alamat'   => $alamat
                ]);
                
                echo "Berhasil disimpan sebagai titik anomali baru.";
            }

        } catch (PDOException $e) {
            echo "Gagal memproses query spasial: " . $e->getMessage();
        }
    } else {
        echo "Error: Parameter koordinat atau kategori tidak valid.";
    }
} else {
    echo "Metode request ditolak. Hanya menerima HTTP POST dari perangkat IoT.";
}
?>