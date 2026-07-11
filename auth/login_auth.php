<?php
require_once '../config_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $password  = trim($_POST['password']  ?? '');
    $user_type = trim($_POST['user_type'] ?? 'student');

    // VALIDATE INPUTS
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
        redirectWithError($error, $user_type, $username);
    }

    session_start();
    session_unset();
    session_destroy();
    session_start();

    if ($user_type === 'superadmin') {
        authenticateSuperAdmin($username, $password, $conn);
    } elseif ($user_type === 'admin') {
        authenticateAdmin($username, $password, $conn);
    } else {
        authenticateStudent($username, $password, $conn);
    }
}

// SUPER ADMIN
function authenticateSuperAdmin($username, $password, $conn)
{
    $sql  = "SELECT id, username, password, name, is_active
             FROM superadmins
             WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        logFailedAttempt($conn, 'superadmin', $username);
        redirectWithError("Invalid superadmin credentials", 'superadmin');
    }

    $sa = $result->fetch_assoc();

    if (!$sa['is_active']) {    
        redirectWithError("This superadmin account has been disabled", 'superadmin');
    }

    if (!password_verify($password, $sa['password'])) {
        logFailedAttempt($conn, 'superadmin', $username);
        redirectWithError("Invalid superadmin credentials", 'superadmin');
    }

    $upd = $conn->prepare("UPDATE superadmins SET last_login = NOW(), last_activity = NOW() WHERE id = ?");
    $upd->bind_param("i", $sa['id']);
    $upd->execute();

    writeAuditLog($conn, 'superadmin', $sa['id'], $sa['name'], 'login', null, null, 'Superadmin logged in');

    session_regenerate_id(true);

    $_SESSION['superadmin_id']       = $sa['id'];
    $_SESSION['superadmin_name']     = $sa['name'];
    $_SESSION['superadmin_username'] = $sa['username'];
    $_SESSION['user_type']           = 'superadmin';
    $_SESSION['login_time']          = time();

    header("Location: ../superadmin/pages/dashboard.php");
    exit();
}

// ADMIN 
function authenticateAdmin($username, $password, $conn)
{
    $sql  = "SELECT id, username, password, name FROM administrators WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Unable to connect to database: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        redirectWithError("Invalid admin credentials", 'admin');
    }

    $admin = $result->fetch_assoc();

    if (!password_verify($password, $admin['password'])) {
        redirectWithError("Invalid admin credentials", 'admin');
    }

    // ← Add this
    $upd = $conn->prepare("UPDATE administrators SET last_login = NOW(), last_activity = NOW() WHERE id = ?");
    $upd->bind_param("i", $admin['id']);
    $upd->execute();
    $upd->close();

    writeAuditLog($conn, 'admin', $admin['id'], $admin['name'], 'login', null, null, 'Admin logged in');

    session_regenerate_id(true);

    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_name']     = $admin['name'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['user_type']      = 'admin';
    $_SESSION['login_time']     = time();

    header("Location: ../administrator/pages/admin_dashboard.php");
    exit();
}

// STUDENT 
function authenticateStudent($student_id, $password, $conn)
{
    $sql = "SELECT id, student_id, department, password, has_voted, is_new_user, email 
            FROM students WHERE student_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        logFailedAttempt($conn, 'student', $student_id);
        redirectWithError("Invalid Student ID or Password", 'student', $student_id);
    }

    $student = $result->fetch_assoc();

    if (!password_verify($password, $student['password'])) {
        logFailedAttempt($conn, 'student', $student_id);
        redirectWithError("Invalid Student ID or Password", 'student', $student_id);
    }

    // FIRST TIME LOGIN 
if ((int)$student['is_new_user'] === 1) {
    session_regenerate_id(true);
    
    $_SESSION['pending_student_id']    = $student['id'];
    $_SESSION['pending_student_sid']   = $student['student_id'];
    $_SESSION['pending_student_email'] = $student['email'];
    $_SESSION['user_type']             = 'student_pending';
    $_SESSION['otp_verified']          = true;  

    header("Location: ../student/change_password.php");
    exit();
}

    // NORMAL LOGIN 
    session_regenerate_id(true);
    
    $_SESSION['student_id']   = $student['id'];
    $_SESSION['student_sid']  = $student['student_id'];
    $_SESSION['student_dept'] = $student['department'];
    $_SESSION['has_voted']    = (int)$student['has_voted'];
    $_SESSION['user_type']    = 'student';

    header("Location: " . ($student['has_voted'] == 1 
        ? "../student/voted_already.php" 
        : "../student/student_dashboard.php"));
    exit();
}

// HELPERS  
function writeAuditLog(
    $conn,
    $actor_type,
    $actor_id,
    $actor_name,
    $action,
    $target_type,
    $target_id,
    $detail
) {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $sql = "INSERT INTO audit_logs
              (actor_type, actor_id, actor_name, action, target_type, target_id, detail, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    $stmt->bind_param(
        "sisssiss",
        $actor_type,
        $actor_id,
        $actor_name,
        $action,
        $target_type,
        $target_id,
        $detail,
        $ip
    );
    @$stmt->execute();
}

function logFailedAttempt($conn, $actor_type, $username)
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $sql = "INSERT INTO audit_logs
              (actor_type, actor_id, actor_name, action, detail, ip_address)
            VALUES (?, 0, ?, 'failed_login', ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    $detail = "Failed login attempt for: $username";
    $stmt->bind_param("ssss", $actor_type, $username, $detail, $ip);
    @$stmt->execute();
}

function redirectWithError($error, $user_type, $username = '')
{
    $pages = [
        'superadmin' => 'superadmin_login.php',
        'admin'      => 'admin_login.php',
        'student'    => 'student_login.php',
    ];

    $page     = $pages[$user_type] ?? 'student_login.php';
    $redirect = $page . '?error=' . urlencode($error);
    if ($username) {
        $redirect .= '&username=' . urlencode($username);
    }

    header("Location: $redirect");
    exit();
}
