<?php
session_start();

// Matikan Cache agar browser HP selalu mengambil halaman terbaru
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Jika admin sudah login, langsung arahkan ke dasbor
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Verifikasi kredensial (Hardcoded untuk kemudahan skripsi)
    if ($username === 'admin' && $password === 'dikri123') {
        $_SESSION['is_admin'] = true;
        $_SESSION['nama_admin'] = 'Admin';
        header("Location: index.php");
        exit;
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Pelaporan Kerusakan Jalan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        .login-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 35px 20px 20px;
            text-align: center;
        }
        .login-header h4 {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .login-header p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
            background-color: #f8fafc;
        }
        .form-control:focus {
            background-color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }
        .btn-login {
            background: linear-gradient(to right, #3b82f6, #2563eb);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(37, 99, 235, 0.3);
            color: white;
        }
        .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .icon-lock {
            font-size: 2.8rem;
            margin-bottom: 10px;
            display: inline-block;
            background: -webkit-linear-gradient(#3b82f6, #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0px 5px 15px rgba(37, 99, 235, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card login-card">
                    <div class="login-header">
                        <div class="icon-lock">🛡️</div>
                        <h4>Portal Admin</h4>
                        <p>Sistem Pelaporan Kerusakan Jalan</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if($error): ?>
                            <div class="alert alert-danger py-2 border-0 text-center" style="border-radius: 8px; font-size: 0.9rem; font-weight: 500;">
                                ⚠️ <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" placeholder="Masukkan username admin" required autocomplete="off">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                            </div>
                            <button type="submit" class="btn btn-login w-100">Masuk ke Dasbor &rarr;</button>
                        </form>
                        
                        <div class="text-center mt-4 text-muted" style="font-size: 0.8rem; font-weight: 500;">
                            &copy; 2026 Universitas Binaniaga Indonesia<br>
                            Berbasis IoT dan Edge AI
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>