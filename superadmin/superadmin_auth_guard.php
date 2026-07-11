<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SA_SESSION_TIMEOUT', 1800);
define('SA_LOGIN_URL', '/sofvote/auth/superadmin_login.php');

    if (
        empty($_SESSION['user_type']) ||
        $_SESSION['user_type'] !== 'superadmin' ||
        empty($_SESSION['superadmin_id'])
    ) {
        header("Location: " . SA_LOGIN_URL . "?error=" . urlencode("Please log in to continue"));
        exit();
    }

if (!empty($_SESSION['last_activity'])) {
    $idle = time() - $_SESSION['last_activity'];
    if ($idle > SA_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: " . SA_LOGIN_URL . "?error=" . urlencode("Session expired. Please log in again."));
        exit();
    }
}
$_SESSION['last_activity'] = time();

if (empty($_SESSION['last_regen']) || (time() - $_SESSION['last_regen']) > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

$sa_id       = (int) $_SESSION['superadmin_id'];
$sa_name     =       $_SESSION['superadmin_name']     ?? 'Super Admin';
$sa_username =       $_SESSION['superadmin_username'] ?? '';

if (!isset($conn)) {
    require_once __DIR__ . '/../config_db.php';
}

global $conn;

$_stmt = $conn->prepare("UPDATE superadmins SET last_activity = NOW() WHERE id = ?");
$_stmt->bind_param("i", $sa_id);
$_stmt->execute();
$_stmt->close();
unset($_stmt);
