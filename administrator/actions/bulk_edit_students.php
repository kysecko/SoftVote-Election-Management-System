<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config_db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// PAGBABASA NG JSON BODY MULA SA REQUEST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No student IDs provided.']);
    exit();
}

// PAGLILINIS AT PABISYO NG MGA IBINIGAY NA ID
$ids        = array_filter(array_map('intval', $input['ids']));
$newDept    = isset($input['department']) ? trim($input['department']) : '';
$newSection = isset($input['section'])    ? trim($input['section'])    : '';

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid student IDs.']);
    exit();
}

// KAILANGAN NG HINDI BABABA SA ISANG FIELD NA PUPUNUIN
if ($newDept === '' && $newSection === '') {
    echo json_encode(['success' => false, 'message' => 'Provide at least department or section to update.']);
    exit();
}

// PAGBUO NG DYNAMIC SET CLAUSE - IYONG MGA FIELD LANG ANG I-UPDATE NA MAY BAGONG HALAGA
$setParts  = [];
$bindTypes = '';
$bindVals  = [];

if ($newDept !== '') {
    $setParts[]  = 'department = ?';
    $bindTypes  .= 's';
    $bindVals[]  = $newDept;
}

if ($newSection !== '') {
    $setParts[]  = 'section = ?';
    $bindTypes  .= 's';
    $bindVals[]  = $newSection;
}

// PAGBUO NG IN CLAUSE PARA SA MGA NAPILING ESTUDYANTE
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$bindTypes   .= str_repeat('i', count($ids));
$bindVals     = array_merge($bindVals, $ids);

$sql  = "UPDATE students SET " . implode(', ', $setParts) . " WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit();
}

$stmt->bind_param($bindTypes, ...$bindVals);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "$affected student(s) updated successfully."
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $stmt->error]);
}

$stmt->close();
$conn->close();