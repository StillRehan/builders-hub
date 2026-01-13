<?php
header('Content-Type: text/html; charset=utf-8');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password kosong
define('DB_NAME', 'db_perpustakaan');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

date_default_timezone_set('Asia/Jakarta');

function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit();
    }
}

function log_activity($user_id, $activity_type, $description) {
    global $conn;
    
    // Validasi user_id exists di database
    $check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    if (!$check) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $check->bind_param("i", $user_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows == 0) {
        error_log("User ID $user_id tidak ditemukan di tabel users");
        $check->close();
        return false;
    }
    $check->close();
    
    // Insert activity log
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description, ip_address) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("isss", $user_id, $activity_type, $description, $ip_address);
    
    try {
        $result = $stmt->execute();
        if (!$result) {
            error_log("Execute failed: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Log activity exception: " . $e->getMessage());
        $stmt->close();
        return false;
    }
}
?>