<?php
// Paksa PHP menggunakan Zona Waktu Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Tangkap data 3 Sumbu Akselerometer
    $x         = isset($_POST['x']) ? floatval($_POST['x']) : 0.0;
    $y         = isset($_POST['y']) ? floatval($_POST['y']) : 0.0;
    $z         = isset($_POST['z']) ? floatval($_POST['z']) : 0.0;
    
    $resultan  = isset($_POST['resultan']) ? floatval($_POST['resultan']) : 0.0;
    $status    = isset($_POST['status']) ? $_POST['status'] : 'Mulus';
    
    // Tangkap data koordinat GPS
    $latitude  = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0.0;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0.0;

    // Susun array untuk format live.json
    $dataLive = [
        "x"         => $x,
        "y"         => $y,
        "z"         => $z,
        "resultan"  => $resultan,
        "status"    => $status,
        "latitude"  => $latitude,
        "longitude" => $longitude,
        "waktu"     => date('H:i:s')
    ];

    // Simpan data baru ke live.json
    file_put_contents('live.json', json_encode($dataLive));

    echo "Sinkronisasi Penuh Berhasil. File live.json telah diperbarui.";
    
} else {
    echo "Metode request ditolak.";
}
?>