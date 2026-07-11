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
$ids = array_filter(array_map('intval', $input['ids']));

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid student IDs.']);
    exit();
}

// PAGBUO NG IN CLAUSE PARA SA MGA PIPINILIANG ESTUDYANTE
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$bindTypes    = str_repeat('i', count($ids));

$sql  = "DELETE FROM students WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit();
}

$stmt->bind_param($bindTypes, ...$ids);

if ($stmt->execute()) {
    $deleted = $stmt->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "$deleted student account(s) deleted successfully."
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $stmt->error]);
}

$stmt->close();
$conn->close();