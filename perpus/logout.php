<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    require_once 'config.php';
    log_activity($_SESSION['user_id'], 'logout', 'User melakukan logout');
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect ke halaman login
header("Location: index.php");
exit();
?>