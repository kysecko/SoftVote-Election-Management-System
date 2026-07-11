<?php
session_start();
include '../../config_db.php';

$name = trim($_POST['partylist_name'] ?? '');

if (empty($name)) {
    header("Location: ../pages/manage_candidates.php?error=empty_partylist");
    exit();
}

$stmt = $conn->prepare("INSERT INTO partylists (name) VALUES (?)");
$stmt->bind_param("s", $name);

if ($stmt->execute()) {
    header("Location: ../pages/manage_candidates.php?success=partylist_added");
} else {
    header("Location: ../pages/manage_candidates.php?error=partylist_exists");
}

$stmt->close();
exit();