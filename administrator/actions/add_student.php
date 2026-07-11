<?php
include '../../config_db.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// CSV UPLOAD 
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {

    $file      = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $lineCount = 0;
    $inserted  = 0;
    $skipped   = 0;
    $reasons   = [];
 
    $ID_REGEX = '/^[0-9]{2,4}-[0-9]{3,5}$/';

    while (($line = fgetcsv($file)) !== false) {
        $lineCount++;

        if (empty($line) || (count($line) === 1 && trim($line[0]) === '')) continue;

        if ($lineCount === 1) {
            $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $line[0]);
        }

        if ($lineCount === 1 && stripos(trim($line[0] ?? ''), 'student_id') !== false) {
            $reasons[] = "Line 1: header row — skipped";
            continue;
        }

        $student_id = trim($line[0] ?? '');
        $raw_pass   = trim($line[1] ?? '');
        $department = trim($line[2] ?? '');
        $section    = trim($line[3] ?? '');
        $email      = trim($line[4] ?? '');

        $reasons[] = "Line $lineCount: id='$student_id' pass=" . (!empty($raw_pass) ? 'set' : 'empty') . " dept='$department'";

        // Validate student_id format
        if (!preg_match($ID_REGEX, $student_id)) {
            $reasons[] = "  → SKIPPED: invalid ID format '$student_id' (hex: " . bin2hex($student_id) . ")";
            $skipped++;
            continue;
        }

        // Required fields
        if (empty($department)) {
            $reasons[] = "  → SKIPPED: empty department";
            $skipped++;
            continue;
        }
        if (empty($raw_pass)) {
            $reasons[] = "  → SKIPPED: empty password";
            $skipped++;
            continue;
        }

        // Validate email format if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reasons[] = "  → SKIPPED: invalid email format '$email'";
            $skipped++;
            continue;
        }

        // Duplicate student_id check
        $id_check = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $id_check->bind_param("s", $student_id);
        $id_check->execute();
        $id_exists = $id_check->get_result()->num_rows > 0;
        $id_check->close();
        if ($id_exists) {
            $reasons[] = "  → SKIPPED: duplicate student_id";
            $skipped++;
            continue;
        }

        // Duplicate email check
        if (!empty($email)) {
            $email_check = $conn->prepare("SELECT id FROM students WHERE email = ?");
            $email_check->bind_param("s", $email);
            $email_check->execute();
            $email_exists = $email_check->get_result()->num_rows > 0;
            $email_check->close();
            if ($email_exists) {
                $reasons[] = "  → SKIPPED: duplicate email '$email'";
                $skipped++;
                continue;
            }
        }

        // Insert
        $hashed = password_hash($raw_pass, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("
            INSERT INTO students (student_id, password, department, section, email, is_new_user, has_voted)
            VALUES (?, ?, ?, ?, ?, 1, 0)
        ");
        $stmt->bind_param("sssss", $student_id, $hashed, $department, $section, $email);

        if ($stmt->execute()) {
            $inserted++;
            $reasons[] = "  → INSERTED";
        } else {
            $reasons[] = "  → INSERT FAILED: " . $conn->error;
            $skipped++;
        }
        $stmt->close();
    }

    fclose($file);

    // return correct success/failure based on actual results
    $dataRows = $lineCount - 1;  

    if ($inserted === 0 && $skipped === 0) {
        // Empty file or only a header row
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'The CSV file appears to be empty or contains only a header row.',
            'debug'   => $reasons,
        ]);
        exit();
    }

    if ($inserted === 0 && $skipped > 0) {
        // if file was read but nothing was saved — show a real error
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => false,
            'inserted' => 0,
            'skipped'  => $skipped,
            'message'  => "No students were saved. All $skipped row(s) were skipped. "
                        . "Common reasons: invalid ID format, duplicate IDs, or missing required fields. "
                        . "Check the browser console for row-by-row details.",
            'debug'    => $reasons,
        ]);
        exit();
    }

    // At least some rows inserted (partial or full success)
    header('Content-Type: application/json');
    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'debug'    => $reasons,
    ]);
    exit();
}

// SINGLE STUDENT ADD    
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_student'])) {
    $student_id = trim($_POST['student_id'] ?? '');
    $password   = trim($_POST['password']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $department = trim($_POST['department'] ?? '');
    $section    = trim($_POST['section']    ?? '');

    $error = '';

    if (!preg_match('/^[0-9]{2,4}-[0-9]{3,5}$/', $student_id)) {
        $error = 'Invalid Student ID format. Use XX-XXXX (e.g. 23-1234).';
    } elseif (empty($password)) {
        $error = 'Password cannot be empty.';
    } elseif (empty($department)) {
        $error = 'Department is required.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    }

    if (empty($error)) {
        $check = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $check->bind_param("s", $student_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $error = 'Student ID already exists.';
        $check->close();
    }

    if (empty($error) && !empty($email)) {
        $email_check = $conn->prepare("SELECT id FROM students WHERE email = ?");
        $email_check->bind_param("s", $email);
        $email_check->execute();
        if ($email_check->get_result()->num_rows > 0) $error = 'This email is already registered.';
        $email_check->close();
    }

    if (empty($error)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("
            INSERT INTO students (student_id, password, department, section, email, is_new_user, has_voted)
            VALUES (?, ?, ?, ?, ?, 1, 0)
        ");
        $stmt->bind_param("sssss", $student_id, $hashed, $department, $section, $email);

        if ($stmt->execute()) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            }
            header("Location: ../pages/manage_students.php?success=student_added");
            exit();
        }

        // DB insert actually failed
        $error = 'Database error: failed to save student. (' . $conn->error . ')';
        $stmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $error]);
    exit();
}

// FALLBACK    
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit();