<?php
session_start();
require_once '../config_db.php';

if (empty($_SESSION['pending_student_id']) && empty($_SESSION['otp_student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}

// Determine which flow we're in
$student_id = $_SESSION['pending_student_sid'] ?? $_SESSION['otp_student_id'] ?? '';
$email      = $_SESSION['pending_student_email'] ?? $_SESSION['otp_email'] ?? '';

if (empty($student_id) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Missing session data.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Rate limiting (1 resend per 60 seconds)
$rate_check = $conn->prepare("SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, created_at, NOW())) AS seconds_left 
                              FROM otp_tokens WHERE student_id = ? 
                              ORDER BY created_at DESC LIMIT 1");
$rate_check->bind_param("s", $student_id);
$rate_check->execute();
$rate = $rate_check->get_result()->fetch_assoc();

if ($rate && $rate['seconds_left'] > 0) {
    echo json_encode(['success' => false, 'message' => "Please wait {$rate['seconds_left']} seconds."]);
    exit;
}

// Send new OTP using the helper
require_once 'send_otp.php';
$sent = sendOTP($student_id, $email, isset($_SESSION['pending_student_id']) ? 'first_login' : 'forgot_password');

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'New OTP sent successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Try again later.']);
}