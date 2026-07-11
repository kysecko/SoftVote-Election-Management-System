<?php
session_start();
require_once '../../config_db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

$department = isset($_GET['department']) ? trim($_GET['department']) : '';

if (empty($department)) {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT DISTINCT section FROM students
     WHERE department = ? AND section IS NOT NULL AND section != ''
     ORDER BY section"
);

$stmt->bind_param("s", $department);
$stmt->execute();
$result = $stmt->get_result();

$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row['section'];
}

$stmt->close();
echo json_encode($sections);