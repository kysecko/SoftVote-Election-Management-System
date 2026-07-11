<?php
require_once 'superadmin_auth_guard.php';
require_once __DIR__ . '/../config_db.php';

$tablesRes = $conn->query("SHOW TABLES");
$backup    = [
    'meta' => [
        'generated_at' => date('Y-m-d H:i:s'),
        'generated_by' => 'Super Admin (ID: ' . $_SESSION['superadmin_id'] . ')',
        'system'       => 'SOFTVOTE',
    ],
    'tables' => [],
];

while ($row = $tablesRes->fetch_array()) {
    $table   = $row[0];
    $rowsRes = $conn->query("SELECT * FROM `$table`");
    $rows    = [];
    while ($dataRow = $rowsRes->fetch_assoc()) {
        $rows[] = $dataRow;
    }
    $backup['tables'][$table] = $rows;
}

// Log it
$saId = (int) $_SESSION['superadmin_id'];
$ip   = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
$conn->query("
    INSERT INTO activity_logs (admin_id, action, details, ip_address, created_at)
    VALUES ($saId, 'DATABASE_BACKUP_JSON', 'Super Admin exported a JSON database backup.', '$ip', NOW())
");

$filename = 'softvote_backup_' . date('Ymd_His') . '.json';
$json     = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
header('Cache-Control: no-cache');

echo $json;
exit();