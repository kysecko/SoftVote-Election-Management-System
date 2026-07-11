<?php
session_start();
include '../../config_db.php';

// AUTH CHECK
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/admin_login.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: ../pages/manage_candidates.php?error=db_error");
    exit();
}

// FETCH PHOTO PATH BEFORE DELETING SO WE CAN REMOVE THE FILE
$photo_url  = '';
$pos_id_for_scroll = 0;

$sel = $conn->prepare("SELECT photo_url, position_id FROM candidates WHERE id = ?");
$sel->bind_param("i", $id);
$sel->execute();
$sel->bind_result($photo_url, $pos_id_for_scroll);
$sel->fetch();
$sel->close();

// DELETE FROM DATABASE
$stmt = $conn->prepare("DELETE FROM candidates WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // DELETE LOCAL PHOTO FILE IF IT EXISTS
    if (!empty($photo_url) && strpos($photo_url, 'images/uploaded/') === 0) {
        $file_path = '../../' . $photo_url;
        if (file_exists($file_path)) unlink($file_path);
    }
    header("Location: ../pages/manage_candidates.php?success=candidate_deleted&scroll_to=" . $pos_id_for_scroll);
} else {
    header("Location: ../pages/manage_candidates.php?error=db_error");
}

$stmt->close();
exit();