<?php
require_once 'superadmin_auth_guard.php';
require_once '../config_db.php';
require_once 'includes/audit_helpers.php';

$action = $_POST['action'] ?? '';

switch ($action) {

    // SET ELECTION STATUS (ongoing / ended / not_started)
    case 'set_election_status':
        $new_status = trim($_POST['status'] ?? '');

        if (!in_array($new_status, ['ongoing', 'ended', 'not_started'])) {
            redirect_error("Invalid election status");
        }

        $stmt = $conn->prepare("
    INSERT INTO election_settings (setting_key, setting_value) 
    VALUES ('election_status', ?)
    ON DUPLICATE KEY UPDATE setting_value = ?
");
        $stmt->bind_param("ss", $new_status, $new_status);

        if ($stmt->execute()) {
            writeAuditLog(
                $conn,
                'superadmin',
                $sa_id,
                $sa_name,
                'election_status_changed',
                'election',
                0,
                "Election status set to: $new_status"
            );
            redirect_success("Election status updated to '$new_status'");
        } else {
            redirect_error("Failed to update election status");
        }
        break;

    // CLEAR ALL AUDIT LOGS
    case 'clear_logs':
        // Log the action before wiping — so there's at least one record
        writeAuditLog(
            $conn,
            'superadmin',
            $sa_id,
            $sa_name,
            'logs_cleared',
            'audit_logs',
            0,
            "All audit logs cleared by superadmin"
        );

        if ($conn->query("DELETE FROM audit_logs")) {
            redirect_success("All audit logs have been cleared");
        } else {
            redirect_error("Failed to clear audit logs");
        }
        break;

    // RESET FULL ELECTION CYCLE (wipe all votes)
    case 'reset_cycle':
        // Log before wiping
        writeAuditLog(
            $conn,
            'superadmin',
            $sa_id,
            $sa_name,
            'cycle_reset',
            'election',
            0,
            "Full election cycle reset by superadmin"
        );

        // Reset has_voted flag on all students and delete all votes
        $conn->query("UPDATE students SET has_voted = 0");
        $conn->query("DELETE FROM votes");

        // Reset election status back to not_started
        $conn->query("UPDATE election_settings SET setting_value = 'not_started' WHERE setting_key = 'election_status'");

        redirect_success("Election cycle has been reset. All votes cleared.");
        break;

    // CHANGE SUPERADMIN PASSWORD
    case 'change_sa_password':
        $new_password     = trim($_POST['new_password']     ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($new_password)) {
            redirect_error("Password cannot be empty");
        }

        if (strlen($new_password) < 8) {
            redirect_error("Password must be at least 8 characters");
        }

        if ($new_password !== $confirm_password) {
            redirect_error("Passwords do not match");
        }

        $hashed = password_hash($new_password, PASSWORD_BCRYPT);

        // Superadmin is stored in a separate table — adjust table name if different
        $stmt = $conn->prepare("UPDATE superadmins SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $sa_id);

        if ($stmt->execute()) {
            writeAuditLog(
                $conn,
                'superadmin',
                $sa_id,
                $sa_name,
                'sa_password_changed',
                'superadmin',
                $sa_id,
                "Superadmin changed their own password"
            );
            redirect_success("Your password has been updated successfully");
        } else {
            redirect_error("Failed to update password");
        }
        break;

    default:
        header("Location: /pages/settings.php");
        exit();
}


function redirect_success(string $msg): void
{
    header("Location: pages/settings.php?success=" . urlencode($msg));
    exit();
}

function redirect_error(string $msg): void
{
    header("Location: pages/settings.php?error=" . urlencode($msg));
    exit();
}
