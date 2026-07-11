<?php
session_start();
require_once '../../config_db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    session_destroy();
    header("Location: ../auth/admin_login.php");
    exit();
}

// PAGSET NG ELECTION STATUS TO 'ENDED'
$stmt = $conn->prepare("
    UPDATE election_settings 
    SET setting_value='ended' 
    WHERE setting_key='election_status'
");
$stmt->execute();
$stmt->close();

$conn->close();

header("Location: ../pages/admin_dashboard.php");
exit();
?>
