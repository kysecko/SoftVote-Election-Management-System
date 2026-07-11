<?php
session_start();
include '../../config_db.php';

if (isset($_POST['edit_candidate'])) {

    $candidate_id = intval($_POST['candidate_id']  ?? 0);
    $first_name   = trim($_POST['first_name']      ?? '');
    $middle_name  = trim($_POST['middle_name']     ?? '');
    $last_name    = trim($_POST['last_name']       ?? '');
    $position_id  = intval($_POST['position_id']   ?? 0);

    // USE partylist_id (INTEGER) INSTEAD OF partylist (TEXT)
    $partylist_id = isset($_POST['partylist_id']) && $_POST['partylist_id'] !== ''
        ? intval($_POST['partylist_id'])
        : null;   // if null or Independent

    // VALIDATION
    if (empty($first_name) || empty($last_name)) {
        header("Location: ../pages/manage_candidates.php?error=empty_fields");
        exit();
    }

    if ($position_id <= 0) {
        header("Location: ../pages/manage_candidates.php?error=invalid_position");
        exit();
    }

    // GET OLD PHOTO SO IT'S NOT LOST IF NO NEW UPLOAD
    $stmt = $conn->prepare("SELECT photo_url FROM candidates WHERE id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $row       = $stmt->get_result()->fetch_assoc();
    $old_photo = $row['photo_url'] ?? '';
    $stmt->close();

    $photo_path = $old_photo; // KEEP OLD PHOTO PATH UNLESS NEW ONE IS UPLOADED

    // CHECK IF NEW PHOTO WAS UPLOADED
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type    = mime_content_type($_FILES['photo']['tmp_name']);  

        if (!in_array($file_type, $allowedTypes)) {
            header("Location: ../pages/manage_candidates.php?error=Invalid image type. Only JPG and PNG allowed.");
            exit();
        }

        if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            header("Location: ../pages/manage_candidates.php?error=Image is too large. Max size is 2MB.");
            exit();
        }

        $uploadDir = "../../images/uploaded/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext        = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $fileName   = time() . '_' . rand(1000, 9999) . '.' . $ext;
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {

            // DELETE OLD LOCAL IMAGE FILE
            if (!empty($old_photo) && strpos($old_photo, 'images/uploaded/') === 0) {
                $oldPath = "../../" . $old_photo;
                if (file_exists($oldPath)) unlink($oldPath);
            }

            $photo_path = "images/uploaded/" . $fileName;
        } else {
            header("Location: ../pages/manage_candidates.php?error=upload_failed");
            exit();
        }
    }

    // FORMAT DISPLAY NAME
    $formatted_name = $last_name . ', ' . $first_name;
    if (!empty($middle_name)) {
        $formatted_name .= ' ' . strtoupper(substr($middle_name, 0, 1)) . '.';
    }

    // UPDATE / partylist_id  REPLACES partylist (TEXT) IN candidates TABLE
    $stmt = $conn->prepare("
        UPDATE candidates 
        SET name = ?, first_name = ?, middle_name = ?, last_name = ?,
            position_id = ?, photo_url = ?, partylist_id = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssissi",
        $formatted_name,
        $first_name,
        $middle_name,
        $last_name,
        $position_id,
        $photo_path,
        $partylist_id,
        $candidate_id
    );

    if ($stmt->execute()) {
        header("Location: ../pages/manage_candidates.php?success=updated&scroll_to=" . $position_id);
        exit();
    } else {
        header("Location: ../pages/manage_candidates.php?error=db_error");
        exit();
    }

    $stmt->close();
}
