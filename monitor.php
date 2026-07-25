<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Dashboard - Panel Indikator Real-Time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212; color: #ffffff; }
        .status-box { font-size: 4rem; font-weight: 900; text-align: center; padding: 30px; border-radius: 15px; transition: all 0.2s ease; }
        .bg-mulus { background-color: #198754; box-shadow: 0 0 30px rgba(25,135,84,0.4); }
        .bg-lubang { background-color: #dc3545; box-shadow: 0 0 30px rgba(220,53,69,0.4); }
        .bg-ptidur { background-color: #fd7e14; box-shadow: 0 0 30px rgba(253,126,20,0.4); color: #000 !important; }
        .angka-sensor { font-size: 2.5rem; font-family: monospace; font-weight: bold; }
        .gps-text { font-size: 1.1rem; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container py-5 text-center">
        <h1 class="fw-bold text-info mb-2">📡 PANEL INDIKATOR REAL-TIME</h1>
        <p class="text-light opacity-75 mb-5">Visualisasi Nirkabel Sistem Klasifikasi Getaran Jalan</p>

        <div class="row justify-content-center mb-5">
            <div class="col-md-8">
                <div id="kotakStatus" class="status-box bg-mulus">MULUS</div>
            </div>
        </div>

        <div class="row justify-content-center g-4">
            
            <div class="col-md-4">
                <div class="card bg-dark border-secondary p-4 h-100 text-start">
                    <h5 class="text-secondary text-center mb-3">Akselerasi Inersial (Raw)</h5>
                    <div class="angka-sensor text-warning text-center mb-1" id="tampilZ">0.0</div>
                    <div class="text-center text-light opacity-75 small mb-3">Sumbu Z (Vertikal)</div>
                    
                    <div style="font-family: monospace; border-top: 1px solid #343a40; padding-top: 10px;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Sumbu X (Lateral):</span>
                            <span id="tampilX" class="text-white fw-bold">0.0</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Sumbu Y (Longitd):</span>
                            <span id="tampilY" class="text-white fw-bold">0.0</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-dark border-secondary p-4 h-100">
                    <h5 class="text-secondary mb-3">Resultan Getaran</h5>
                    <div id="tampilR" class="angka-sensor text-success mt-2 mb-2">0.0</div>
                    <span class="text-light opacity-75 small">m/s² (Kombinasi 3 Sumbu)</span>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-dark border-secondary p-4 h-100 text-start">
                    <h5 class="text-secondary text-center mb-3">Geotagging Satelit</h5>
                    <div class="mt-2 gps-text">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-white">Latitude:</span>
                            <span id="tampilLat" class="text-info">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-white">Longitude:</span>
                            <span id="tampilLon" class="text-info">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-white">Sinyal:</span>
                            <span id="tampilSinyal" class="badge bg-danger">Mencari...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 text-light opacity-75 small">
            Waktu Pembaruan Alat: <span id="waktuUpdate" class="text-white fw-bold">-</span>
        </div>
    </div>

    <script>
        function hitLiveServer() {
            fetch('live.json?t=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    // Perbarui Nilai Sensor Akselerometer
                    document.getElementById('tampilZ').innerText = data.z.toFixed(1);
                    document.getElementById('tampilX').innerText = data.x.toFixed(1);
                    document.getElementById('tampilY').innerText = data.y.toFixed(1);
                    document.getElementById('tampilR').innerText = data.resultan.toFixed(1);
                    document.getElementById('waktuUpdate').innerText = data.waktu;

                    // Perbarui Status Lencana Kotak AI Utama
                    var box = document.getElementById('kotakStatus');
                    box.innerText = data.status.toUpperCase();

                    box.className = "status-box"; 
                    if (data.status === "Lubang") {
                        box.classList.add("bg-lubang");
                    } else if (data.status === "P. Tidur") {
                        box.classList.add("bg-ptidur");
                    } else {
                        box.classList.add("bg-mulus");
                    }

                    // Perbarui Indikator Aliran GPS
                    if (data.latitude && data.longitude && data.latitude !== 0) {
                        document.getElementById('tampilLat').innerText = data.latitude.toFixed(6);
                        document.getElementById('tampilLon').innerText = data.longitude.toFixed(6);
                        
                        var badgeSinyal = document.getElementById('tampilSinyal');
                        badgeSinyal.innerText = "TERKUNCI (Valid)";
                        badgeSinyal.className = "badge bg-success";
                    } else {
                        document.getElementById('tampilLat').innerText = "Searching...";
                        document.getElementById('tampilLon').innerText = "Searching...";
                        
                        var badgeSinyal = document.getElementById('tampilSinyal');
                        badgeSinyal.innerText = "No Fix (Blind)";
                        badgeSinyal.className = "badge bg-danger";
                    }
                })
                .catch(err => console.log("Menunggu transmisi alat..."));
        }
        
        // Refresh rate kilat 300ms untuk visualisasi real-time
        setInterval(hitLiveServer, 300);
    </script>
</body>
</html>