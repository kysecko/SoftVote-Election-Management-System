<?php
session_start();
require_once '../../config_db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/admin_login.php");
    exit();
}
try {

    $conn->begin_transaction();

    $conn->query("DELETE FROM votes");
    $conn->query("UPDATE students SET has_voted = 0");
    $conn->query("UPDATE candidates SET is_winner = 0");
    $conn->query("UPDATE election_settings SET setting_value = 'not_started' WHERE setting_key = 'election_status'");

    $conn->commit();

    header("Location: ../pages/admin_dashboard.php");
    exit();
} catch (Exception $e) {

    $conn->rollback();

    header("Location: ../pages/admin_dashboard.php");
    exit();
}
