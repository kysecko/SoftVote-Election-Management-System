<?php
session_start();

include '../../config_db.php';

header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = isset($_POST['id'])         ? intval($_POST['id'])            : 0;
    $student_id = isset($_POST['student_id']) ? trim($_POST['student_id'])      : '';
    $department = isset($_POST['department']) ? trim($_POST['department'])      : '';
    $email      = isset($_POST['email'])      ? trim($_POST['email'])           : '';
    $section    = isset($_POST['section'])    ? trim($_POST['section'])         : '';

    if (empty($id) || empty($student_id) || empty($department) || empty($email) || empty($section)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit();
    }

    $check_stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ? AND id != ?");
    $check_stmt->bind_param("si", $student_id, $id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Student ID already exists']);
        exit();
    }
    $check_stmt->close();

    $email_check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
    $email_check->bind_param("si", $email, $id);
    $email_check->execute();
    if ($email_check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit();
    }
    $email_check->close();

    $stmt = $conn->prepare("
        UPDATE students 
        SET student_id = ?, email = ?, department = ?, section = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $student_id, $email, $department, $section, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating student']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();