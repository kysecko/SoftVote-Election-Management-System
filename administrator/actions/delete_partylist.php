<?php
session_start();
include '../../config_db.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: ../pages/manage_candidates.php?error=invalid_partylist");
    exit();
}

$stmt = $conn->prepare("DELETE FROM partylists WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: ../pages/manage_candidates.php?success=partylist_deleted");
exit();