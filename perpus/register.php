<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = clean_input($_POST['full_name']);
    
    if (strlen($username) < 4) {
        $error = "Username minimal 4 karakter!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email tidak valid!";
    } else {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username atau Email sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            
            if ($insert_stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            }
            
            $insert_stmt->close();
        }
        
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi | Perpustakaan </title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bg-decoration">
        <div class="bg-circle bg-circle-1"></div>
        <div class="bg-circle bg-circle-2"></div>
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <h1>&#128218; Perpustakaan </h1>
                <p>Buat Akun Baru</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    &#9888; <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    &#9989; <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="full_name">Nama Lengkap</label>
                    <input type="text" name="full_name" id="full_name" class="form-input" placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-input" placeholder="Minimal 4 karakter" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-input" placeholder="email@example.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Minimal 6 karakter" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Ulangi password" required>
                </div>

                <button type="submit" class="btn-primary">Daftar</button>
            </form>

            <div class="login-footer">
                <p>Sudah punya akun? <a href="index.php">Login di sini</a></p>
            </div>
        </div>
    </div>
</body>
</html>