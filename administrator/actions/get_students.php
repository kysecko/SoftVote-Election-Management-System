<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config_db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (isset($_GET['meta']) && $_GET['meta'] == '1') {
    // EXISTING CODE — get departments and sections
    $departments = [];
    $sections = [];

    $res = $conn->query("SELECT DISTINCT department FROM students WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
    while ($row = $res->fetch_assoc()) $departments[] = $row['department'];

    $res = $conn->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND section != '' ORDER BY section ASC");
    while ($row = $res->fetch_assoc()) $sections[] = $row['section'];

    // BAGONG CODE — dept_sections mapping
    $dept_sections = [];
    $res = $conn->query("SELECT DISTINCT department, section FROM students WHERE department IS NOT NULL AND department != '' AND section IS NOT NULL AND section != '' ORDER BY department ASC, section ASC");
    while ($row = $res->fetch_assoc()) {
        $dept = $row['department'];
        $sec  = $row['section'];
        if (!isset($dept_sections[$dept])) $dept_sections[$dept] = [];
        $dept_sections[$dept][] = $sec;
    }

    echo json_encode([
        'departments'  => $departments,
        'sections'     => $sections,
        'dept_sections' => $dept_sections   
    ]);
    exit;
}

// PAGKUHA NG ISANG ESTUDYANTE GAMIT ANG KANYANG ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT id, student_id, department, email, section FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Student not found']);
    }
    exit();
}

// PAGKUHA NG MGA ESTUDYANTE NA MAY SEARCH, FILTER, AT PAGINATION
$search  = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$page    = isset($_GET['page'])    ? max(1, intval($_GET['page'])) : 1;
$dept    = isset($_GET['dept'])    ? trim($_GET['dept'])    : '';
$section = isset($_GET['section']) ? trim($_GET['section']) : '';
$perPage = 8;
$offset  = ($page - 1) * $perPage;

// PAGBUO NG WHERE CLAUSE BATAY SA MGA IBINIGAY NA FILTER
$conditions = [];
$bindTypes  = '';
$bindVals   = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $conditions[] = '(student_id LIKE ? OR department LIKE ? OR section LIKE ?)';
    $bindTypes   .= 'sss';
    $bindVals[]   = $like;
    $bindVals[]   = $like;
    $bindVals[]   = $like;
}

if ($dept !== '') {
    $conditions[] = 'department = ?';
    $bindTypes   .= 's';
    $bindVals[]   = $dept;
}

if ($section !== '') {
    $conditions[] = 'section = ?';
    $bindTypes   .= 's';
    $bindVals[]   = $section;
}

$where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

// PAGBIBILANG NG KABUUANG RESULTA PARA SA PAGINATION
$countSql  = "SELECT COUNT(*) as total FROM students $where";
$countStmt = $conn->prepare($countSql);
if ($bindTypes) {
    $countStmt->bind_param($bindTypes, ...$bindVals);
}
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// PAGKUHA NG MGA ESTUDYANTE SA KASALUKUYANG PAGE
$dataSql   = "SELECT id, student_id, department, section FROM students $where ORDER BY student_id ASC LIMIT ? OFFSET ?";
$dataStmt  = $conn->prepare($dataSql);
$dataTypes = $bindTypes . 'ii';
$dataVals  = array_merge($bindVals, [$perPage, $offset]);
$dataStmt->bind_param($dataTypes, ...$dataVals);
$dataStmt->execute();
$result = $dataStmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$dataStmt->close();

echo json_encode([
    'students'    => $students,
    'total_pages' => $totalPages,
    'page'        => $page,
    'total_rows'  => $totalRows,
]);
