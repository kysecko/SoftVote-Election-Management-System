<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_type = $_GET['type'] ?? 'student';

$_SESSION = [];

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

switch ($user_type) {
    case 'superadmin':
        header("Location: /softvote/auth/superadmin_login.php");
        break;
    case 'admin':
        header("Location: /softvote/auth/admin_login.php");
        break;
    default:
        header("Location: /softvote/auth/student_login.php");
        break;
}
exit;
