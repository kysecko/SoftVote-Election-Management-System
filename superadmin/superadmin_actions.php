<?php
require_once 'superadmin_auth_guard.php';
require_once '../config_db.php';
require_once 'includes/audit_helpers.php';

$action = $_POST['action'] ?? '';

switch ($action) {

    // CREATE ADMIN
    case 'create_admin':
        $name     = trim($_POST['name']     ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($name) || empty($username) || empty($password)) {
            redirect_error("All fields are required");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            redirect_error("Username can only contain letters, numbers, and underscores");
        }

        $check = $conn->prepare("SELECT id FROM administrators WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            redirect_error("Username already exists");
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("INSERT INTO administrators (name, username, password, is_active, is_deleted) VALUES (?, ?, ?, 1, 0)");
        $stmt->bind_param("sss", $name, $username, $hashed);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            writeAuditLog(
                $conn,
                'superadmin',
                $sa_id,
                $sa_name,
                'admin_created',
                'admin',
                $new_id,
                "Created admin account: $username ($name)"
            );
            redirect_success("Admin '$name' created successfully");
        } else {
            redirect_error("Failed to create admin. Please try again.");
        }
        break;

    // EDIT ADMIN
    case 'edit_admin':
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        $name     = trim($_POST['name']     ?? '');
        $username = trim($_POST['username'] ?? '');

        if ($admin_id <= 0 || empty($name) || empty($username)) {
            redirect_error("All fields are required");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            redirect_error("Username can only contain letters, numbers, and underscores");
        }

        $check = $conn->prepare("SELECT id FROM administrators WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $admin_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            redirect_error("Username already taken by another admin");
        }

        $stmt = $conn->prepare("UPDATE administrators SET name = ?, username = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $username, $admin_id);

        if ($stmt->execute()) {
            writeAuditLog(
                $conn,
                'superadmin',
                $sa_id,
                $sa_name,
                'admin_updated',
                'admin',
                $admin_id,
                "Updated admin profile: $username ($name)"
            );
            redirect_success("Admin profile updated successfully");
        } else {
            redirect_error("Failed to update admin");
        }
        break;

    // RESET PASSWORD
    case 'reset_password':
        $admin_id         = (int)($_POST['admin_id']        ?? 0);
        $new_password     = trim($_POST['new_password']     ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($admin_id <= 0 || empty($new_password)) {
            redirect_error("Invalid request");
        }

        if (strlen($new_password) < 6) {
            redirect_error("Password must be at least 6 characters");
        }

        if ($new_password !== $confirm_password) {
            redirect_error("Passwords do not match");
        }

        // Fixed — was corrupted in previous version
        $fetch = $conn->prepare("SELECT name, username FROM administrators WHERE id = ?");
        $fetch->bind_param("i", $admin_id);
        $fetch->execute();
        $row = $fetch->get_result()->fetch_assoc();

        if (!$row) redirect_error("Admin not found");

        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE administrators SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $admin_id);

        if ($stmt->execute()) {
            writeAuditLog(
                $conn,
                'superadmin',
                $sa_id,
                $sa_name,
                'password_reset',
                'admin',
                $admin_id,
                "Reset password for: {$row['username']} ({$row['name']})"
            );
            redirect_success("Password for '{$row['name']}' has been reset");
        } else {
            redirect_error("Failed to reset password");
        }
        break;

    // TOGGLE ACTIVE / SUSPEND
    case 'toggle_admin_status':
        $admin_id   = (int)($_POST['admin_id']   ?? 0);
        $new_status = (int)($_POST['new_status'] ?? 0);

        if ($admin_id <= 0) {
            redirect_error("Invalid admin ID");
        }

        $fetch = $conn->prepare("SELECT name, username FROM administrators WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
        $fetch->bind_param("i", $admin_id);
        $fetch->execute();
        $row = $fetch->get_result()->fetch_assoc();

        if (!$row) redirect_error("Admin not found or already deleted");

        $new_status = $new_status ? 1 : 0;

        $stmt = $conn->prepare("UPDATE administrators SET is_active = ? WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
        $stmt->bind_param("ii", $new_status, $admin_id);

        if ($stmt->execute()) {
            $verb = $new_status ? 'activated' : 'suspended';
            writeAuditLog(
                $conn,
                'superadmin',
                $sa_id,
                $sa_name,
                "admin_{$verb}",
                'admin',
                $admin_id,
                ucfirst($verb) . " admin account: {$row['username']} ({$row['name']})"
            );
            redirect_success("Admin '{$row['name']}' has been $verb");
        } else {
            redirect_error("Failed to update status");
        }
        break;

    // DELETE ADMIN — hard delete from database
    case 'delete_admin':
        $admin_id = (int)($_POST['admin_id'] ?? 0);

        if ($admin_id <= 0) {
            redirect_error("Invalid admin ID");
        }

        if ($admin_id === (int)$sa_id) {
            redirect_error("You cannot delete your own account");
        }

        // Fetch admin details for audit log before deleting
        $fetch = $conn->prepare("SELECT name, username FROM administrators WHERE id = ?");
        $fetch->bind_param("i", $admin_id);
        $fetch->execute();
        $row = $fetch->get_result()->fetch_assoc();

        if (!$row) redirect_error("Admin not found");

        // Hard delete — permanently removes from database
        $del = $conn->prepare("DELETE FROM administrators WHERE id = ?");
        $del->bind_param("i", $admin_id);

        if ($del->execute()) {
            writeAuditLog(
                $conn,
                'superadmin',
                $sa_id,
                $sa_name,
                'admin_deleted',
                'admin',
                $admin_id,
                "Permanently deleted admin account: {$row['username']} ({$row['name']})"
            );
            redirect_success("Admin '{$row['name']}' has been permanently deleted");
        } else {
            redirect_error("Failed to delete admin");
        }
        break;

    default:
        header("Location: pages/manage_admins.php");
        exit();
}


function redirect_success(string $msg): void
{
    header("Location: pages/manage_admins.php?success=" . urlencode($msg));
    exit();
}

function redirect_error(string $msg): void
{
    header("Location: pages/manage_admins.php?error=" . urlencode($msg));
    exit();
}
