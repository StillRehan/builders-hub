<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config.php';
    
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT user_id, username, password, full_name, role FROM users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            
            log_activity($user['user_id'], 'login', 'User berhasil login');
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Perpustakaan </title>
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
                <p>Sistem Informasi Perpustakaan</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    &#9888; <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-input" placeholder="Masukkan username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Masukkan password" required>
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="login-footer">
                <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
            </div>
        </div>
    </div>
</body>
</html>