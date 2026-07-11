<?php
function writeAuditLog($conn, $actor_type, $actor_id, $actor_name, $action, $target_type, $target_id, $detail): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (actor_type, actor_id, actor_name, action, target_type, target_id, detail, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;
    $stmt->bind_param("sisssiis", $actor_type, $actor_id, $actor_name, $action, $target_type, $target_id, $detail, $ip);
    @$stmt->execute();
}