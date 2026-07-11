<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_SESSION_TIMEOUT', 3600);
define('ADMIN_LOGIN_URL', '../../auth/admin_login.php');

// KAILANGAN LOGGED IN AS ADMIN PARA MAACCESS ANG ADMIN PAGES
if (
    empty($_SESSION['user_type']) ||
    $_SESSION['user_type'] !== 'admin' ||
    empty($_SESSION['admin_id'])
) {
    header("Location: " . ADMIN_LOGIN_URL . "?error=" . urlencode("Please log in to continue"));
    exit();
}

// PAGCHECK NG SESSION TIMEOUT
if (!empty($_SESSION['last_activity'])) {
    $idle = time() - $_SESSION['last_activity'];
    if ($idle > ADMIN_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: " . ADMIN_LOGIN_URL . "?error=" . urlencode("Session expired. Please log in again."));
        exit();
    }
}
$_SESSION['last_activity'] = time();

// PAGREGENERATE NG SESSION ID EVERY 5 MINUTES TO PREVENT SESSION FIXATION
if (empty($_SESSION['last_regen']) || (time() - $_SESSION['last_regen']) > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

// PAGREGENERATE NG SESSION ID EVERY 5 MINUTES TO PREVENT SESSION FIXATION
$admin_id       = (int) $_SESSION['admin_id'];
$admin_name     =       $_SESSION['admin_name']     ?? 'Admin';
$admin_username =       $_SESSION['admin_username'] ?? '';

// UPDATE NG LAST ACTIVITY TIME IN DATABASE 
if (!isset($conn)) {
    require_once '../../config_db.php';
}

$conn->query("SET time_zone = '+00:00'");

$_stmt = $conn->prepare("UPDATE administrators SET last_activity = NOW() WHERE id = ?");
$_stmt->bind_param("i", $admin_id);
$_stmt->execute();
$_stmt->close();
unset($_stmt);
