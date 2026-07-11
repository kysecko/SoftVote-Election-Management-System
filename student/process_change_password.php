<?php
session_start();
require_once '../config_db.php';

$is_forgot = isset($_SESSION['otp_verified']) && isset($_SESSION['otp_student_id']);
$is_first  = isset($_SESSION['otp_verified']) && isset($_SESSION['pending_student_id']);

if (!$is_forgot && !$is_first && !isset($_GET['success'])) {
    header("Location: ../auth/student_login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: change_password.php");
    exit();
}

$new_password     = $_POST['new_password']     ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($new_password) || empty($confirm_password)) {
    header("Location: change_password.php?error=" . urlencode("Both password fields are required."));
    exit();
}

if ($new_password !== $confirm_password) {
    header("Location: change_password.php?error=" . urlencode("Passwords do not match. Please try again."));
    exit();
}

if (strlen($new_password) < 8) {
    header("Location: change_password.php?error=" . urlencode("Password must be at least 8 characters long."));
    exit();
}

if (!preg_match('/[a-z]/', $new_password)) {
    header("Location: change_password.php?error=" . urlencode("Password must contain at least one lowercase letter."));
    exit();
}

if (!preg_match('/[0-9]/', $new_password)) {
    header("Location: change_password.php?error=" . urlencode("Password must contain at least one number."));
    exit();
}

// Determine which column to use based on flow
if ($is_forgot) {
    $student_sid = $_SESSION['otp_student_id']; // string e.g. "11-1111"
    $lookup_col  = "student_id";
} else {
    $student_sid = (int)$_SESSION['pending_student_id']; // numeric DB id
    $lookup_col  = "id";
}

$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE students SET password = ?, is_new_user = 0 WHERE $lookup_col = ?");
if (!$stmt) {
    header("Location: change_password.php?error=" . urlencode("A database error occurred. Please try again."));
    exit();
}
$stmt->bind_param($is_forgot ? "ss" : "si", $hashed, $student_sid);
if (!$stmt->execute()) {
    header("Location: change_password.php?error=" . urlencode("Failed to update password. Please try again."));
    exit();
}

$sel = $conn->prepare("SELECT id, student_id, department, has_voted FROM students WHERE $lookup_col = ?");
$sel->bind_param($is_forgot ? "s" : "i", $student_sid);
$sel->execute();
$student = $sel->get_result()->fetch_assoc();

if (!$student) {
    header("Location: ../auth/student_login.php?error=" . urlencode("Session error. Please log in again."));
    exit();
}

// Clear ALL session vars from both flows
session_regenerate_id(true);
unset(
    $_SESSION['otp_student_id'],
    $_SESSION['otp_email'],
    $_SESSION['otp_verified'],
    $_SESSION['pending_student_id'],
    $_SESSION['pending_student_sid'],
    $_SESSION['pending_student_email'],
    $_SESSION['user_type']
);

if ($is_first) {
    // New user flow — set session and send to dashboard after success
    $_SESSION['student_id']   = $student['id'];
    $_SESSION['student_sid']  = $student['student_id'];
    $_SESSION['student_dept'] = $student['department'];
    $_SESSION['has_voted']    = (int)$student['has_voted'];
    $_SESSION['user_type']    = 'student';

    $redirect = $student['has_voted'] == 1 ? 'voted_already.php' : 'student_dashboard.php';
    header("Location: change_password.php?success=1&redirect=" . urlencode($redirect));
} else {
    // Forgot password flow — session already cleared, go to login
    header("Location: change_password.php?success=1&redirect=" . urlencode('../auth/student_login.php'));
}
exit();