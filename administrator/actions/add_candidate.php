<?php
session_start();
include '../../config_db.php';

if (!isset($_POST['add_candidate'])) {
    header("Location: ../pages/manage_candidates.php");
    exit();
}

$first_name   = trim($_POST['first_name']  ?? '');
$middle_name  = trim($_POST['middle_name'] ?? '');
$last_name    = trim($_POST['last_name']   ?? '');
$position_id  = intval($_POST['position_id'] ?? 0);
$partylist_id = isset($_POST['partylist_id']) && $_POST['partylist_id'] !== ''
    ? intval($_POST['partylist_id'])
    : null;   // pag null or Independent

if (empty($first_name) || empty($last_name) || empty($position_id)) {
    header("Location: ../pages/manage_candidates.php?error=empty_fields");
    exit();
}

// Build formatted name
$formatted_name = $last_name . ', ' . $first_name;
if (!empty($middle_name)) {
    $formatted_name .= ' ' . strtoupper(substr($middle_name, 0, 1)) . '.';
}

// Handle photo upload
$photo_url = '';
if (!empty($_FILES['photo']['name'])) {
    $upload_dir = '../../uploads/candidates/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext          = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed      = ['jpg', 'jpeg', 'png'];
    $new_filename = uniqid('cand_') . '.' . $ext;
    $upload_path  = $upload_dir . $new_filename;

    if (in_array($ext, $allowed) && move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
        $photo_url = 'uploads/candidates/' . $new_filename;
    }
}

// Insert with partylist_id (integer FK) instead of partylist text
$stmt = $conn->prepare("
    INSERT INTO candidates (name, first_name, middle_name, last_name, position_id, partylist_id, photo_url)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "ssssiis",
    $formatted_name,
    $first_name,
    $middle_name,
    $last_name,
    $position_id,
    $partylist_id,     
    $photo_url
);

if ($stmt->execute()) {
    header("Location: ../pages/manage_candidates.php?success=candidate_added&scroll_to=" . $position_id);   
} else {
    header("Location: ../pages/manage_candidates.php?error=db_error");
}
$stmt->close();
exit();
